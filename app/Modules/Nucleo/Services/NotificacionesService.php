<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Services;

use App\Modules\Nucleo\Excepciones\AvisoRechazado;
use App\Modules\Nucleo\Jobs\EnviarCorreoDeAviso;
use App\Modules\Nucleo\Models\Notificacion;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Notificaciones\RegistroAvisos;
use App\Modules\Nucleo\Tiempo\Calendario;
use Filament\Actions\Action;
use Filament\Notifications\Notification as AvisoFilament;
use Illuminate\Database\Eloquent\Builder;

/**
 * M8 · La única forma de avisar a una persona (plan 1.8, D6 a D8). El aviso queda en la campana siempre y, si su grupo
 * lo tiene encendido en «Mis preferencias» o el aviso es obligatorio, también sale por correo, por cola y después del
 * commit. Con el mismo aviso y la misma clave única, un reintento no repite el aviso ni el correo. El usuario de
 * sistema y las cuentas inactivas no reciben avisos. El título y el texto viajan por correo, un servicio externo:
 * nunca llevan datos sensibles (sueldos, salud: regla 10); para eso, «Abrir» lleva al sistema, que revisa permisos.
 *
 * La clave única protege mientras el aviso está en la base: un aviso leído pasa al archivo histórico a los
 * `avisos.dias_leidos` días (plan 1.4y, D4), y entonces la misma clave podría avisar de nuevo. Por eso la clave lleva lo
 * que hace único al hecho (una fecha, una versión), no solo el registro: `orden:15:por-aprobar:v2`.
 *
 * Marcar leído o no leído es un `update` masivo: cambia `updated_at`, no `updated_by` (`codigo.md` lo admite fuera de
 * tablas sensibles; quién leyó qué no es un dato que se audite).
 */
final readonly class NotificacionesService
{
    public function __construct(
        private RegistroAvisos $avisos,
        private PreferenciasUsuarioService $preferencias,
        private ContextoAuditoria $auditoria,
    ) {}

    /**
     * @param  iterable<User>|User  $personas
     * @param  string  $claveUnica  lo que hace único este aviso para cada persona: `orden:15:por-aprobar`
     * @return list<Notificacion> los que se crearon ahora (no los que ya existían)
     */
    public function avisar(string $clave, iterable|User $personas, string $titulo, ?string $texto, ?string $enlace, string $claveUnica): array
    {
        $aviso = $this->avisos->aviso($clave);
        $grupo = $this->avisos->grupo($aviso->grupo);
        $creados = [];

        foreach ($personas instanceof User ? [$personas] : $personas as $persona) {
            if ($persona->es_sistema || ! $persona->es_activo) {
                continue;
            }

            $correo = $aviso->conCorreo && ($aviso->obligatorio || $this->preferencias->siNo($persona, "avisos.{$grupo->preferenciaCorreo()}"));

            $notificacion = new Notificacion([
                'type' => $clave,
                'notifiable_type' => $persona->getMorphClass(),
                'notifiable_id' => $persona->id,
                'data' => $this->paraLaCampana($titulo, $texto, $enlace, $aviso->icono),
                'clave_unica' => $claveUnica,
                'enviar_correo' => $correo,
            ]);
            $notificacion->setAttribute('created_by', $this->auditoria->idActor($persona->id));

            // La base decide si ya existía (clave única por persona): un reintento no crea otro.
            $insertada = Notificacion::query()->insertOrIgnore($notificacion->getAttributes() + ['created_at' => now()]) === 1;

            if (! $insertada) {
                continue;
            }

            $guardada = Notificacion::query()
                ->where('notifiable_type', $persona->getMorphClass())
                ->where('notifiable_id', $persona->id)
                ->where('type', $clave)
                ->where('clave_unica', $claveUnica)
                ->sole();
            $creados[] = $guardada;

            if ($correo) {
                EnviarCorreoDeAviso::dispatch($guardada->id);
            }
        }

        return $creados;
    }

    /**
     * La persona leyó uno de sus avisos (plan 1.4y, D1): sale de la campana y queda en «Mis avisos». Marcarlo dos veces no
     * cambia la hora en que se leyó.
     *
     * @throws AvisoRechazado
     */
    public function marcarLeido(User $persona, int $id): void
    {
        $this->suyo($persona, $id)->whereNull('read_at')->update(['read_at' => Calendario::ahora()]);
    }

    /** Todos sus avisos sin leer, de una vez (D1). Devuelve cuántos. */
    public function marcarTodosLeidos(User $persona): int
    {
        return $this->suyos($persona)->whereNull('read_at')->update(['read_at' => Calendario::ahora()]);
    }

    /**
     * Vuelve a la campana (D3): desde «Mis avisos», si lo marcó por error o quiere que se lo recuerde.
     *
     * @throws AvisoRechazado
     */
    public function marcarNoLeido(User $persona, int $id): void
    {
        $this->suyo($persona, $id)->update(['read_at' => null]);
    }

    /** @return Builder<Notificacion> */
    private function suyos(User $persona): Builder
    {
        return Notificacion::query()->where('notifiable_type', $persona->getMorphClass())->where('notifiable_id', $persona->id);
    }

    /**
     * Uno de sus avisos, o el mismo rechazo para uno ajeno y para uno que no existe.
     *
     * @return Builder<Notificacion>
     *
     * @throws AvisoRechazado
     */
    private function suyo(User $persona, int $id): Builder
    {
        $consulta = $this->suyos($persona)->whereKey($id);

        return $consulta->exists() ? $consulta : throw AvisoRechazado::noEncontrado();
    }

    /**
     * El aviso como lo guarda y lo dibuja la campana del panel.
     *
     * @return array<string, mixed>
     */
    private function paraLaCampana(string $titulo, ?string $texto, ?string $enlace, string $icono): array
    {
        $aviso = AvisoFilament::make()->title($titulo)->body($texto)->icon($icono);

        if ($enlace !== null) {
            $aviso->actions([Action::make('abrir')->label('Abrir')->url($enlace)->markAsRead()]);
        }

        return $aviso->getDatabaseMessage();
    }
}
