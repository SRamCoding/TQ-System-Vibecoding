<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Saludo;

use App\Modules\Nucleo\Desarrollo\ModoDesarrollo;
use App\Modules\Nucleo\Enums\IdiomaSaludo;
use App\Modules\Nucleo\Enums\SesionSaludo;
use App\Modules\Nucleo\Models\AuditoriaCambio;
use App\Modules\Nucleo\Models\SaludoMostrado;
use App\Modules\Nucleo\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * La historia de saludos de cada persona (plan 1.4aa, D7 y D8): único lugar que lee y escribe `saludos_mostrados`.
 * Lee también la bitácora de cambios (`auditoria_cambios`, del núcleo) para saber si la persona ya hizo algo hoy.
 *
 * Lo anterior a una sesión ya guardada son las filas guardadas antes que la suya: así la clase de sesión y el idioma no
 * cambian mientras dura. Sin sesión (texto vacío) no se guarda nada y se mira toda la historia.
 */
final class HistorialSaludos
{
    public function contexto(User $usuario, string $sesion, CarbonImmutable $ahora): ContextoSesion
    {
        $propia = $sesion === '' ? null : SaludoMostrado::query()->where('user_id', $usuario->id)->where('sesion', $sesion)->first();
        $anteriores = fn (): Builder => SaludoMostrado::query()->where('user_id', $usuario->id)
            ->when($propia !== null, fn (Builder $query): Builder => $query->where('id', '<', $propia?->id));
        $inicioDelDia = $ahora->startOfDay();
        $hasta = $propia->mostrado_at ?? $ahora;

        $ultima = $anteriores()->orderByDesc('id')->first();
        $idiomasDeHoy = $anteriores()->where('mostrado_at', '>=', $inicioDelDia)->where('mostrado_at', '<', $inicioDelDia->addDay())
            ->pluck('idioma')->map(fn (mixed $idioma): string => $idioma instanceof IdiomaSaludo ? $idioma->value : (string) $idioma);
        $ultimoOtro = $anteriores()->where('idioma', '<>', IdiomaSaludo::Espanol->value)->orderByDesc('id')->first();

        $clase = match (true) {
            $idiomasDeHoy->isEmpty() => SesionSaludo::PrimeraDelDia,
            AuditoriaCambio::query()->where('user_id', $usuario->id)->where('fecha', '>=', $inicioDelDia)->where('fecha', '<', $hasta)->exists() => SesionSaludo::VuelveConActividad,
            default => SesionSaludo::Vuelve,
        };

        return new ContextoSesion(
            sesion: $clase,
            idiomaGuardado: $propia?->idioma,
            puedeOtroIdioma: ($ultima === null || $ultima->idioma === IdiomaSaludo::Espanol)
                && ! $idiomasDeHoy->contains(fn (string $idioma): bool => $idioma !== IdiomaSaludo::Espanol->value),
            ultimoOtroIdioma: $ultimoOtro?->idioma,
        );
    }

    /**
     * Guarda el saludo de la sesión, una sola vez: si otra petición de la misma sesión llegó antes, se queda la suya.
     * Con una fecha simulada del modo desarrollo no se guarda nada: esa fecha solo mueve lo decorativo (`interfaz.md`,
     * modo desarrollo) y no debe quedar en la historia de la persona.
     */
    public function registrar(User $usuario, string $sesion, IdiomaSaludo $idioma, CarbonImmutable $ahora): void
    {
        if ($sesion === '' || ModoDesarrollo::fechaSimulada() !== null) {
            return;
        }

        SaludoMostrado::query()->createOrFirst(
            ['user_id' => $usuario->id, 'sesion' => $sesion],
            ['idioma' => $idioma, 'mostrado_at' => $ahora],
        );
    }
}
