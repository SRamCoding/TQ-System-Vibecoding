<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Services;

use App\Modules\Nucleo\ConfiguracionInicial\DefinicionPaso;
use App\Modules\Nucleo\ConfiguracionInicial\RegistroPasosConfiguracion;
use App\Modules\Nucleo\Excepciones\PasoConfiguracionRechazado;
use App\Modules\Nucleo\Models\ConfiguracionBienvenida;
use App\Modules\Nucleo\Models\ConfiguracionPaso;
use App\Modules\Nucleo\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * El avance del asistente de configuración inicial (plan 1.13, D3). Es lo único propio del asistente: lo que se configura
 * pasa por las acciones de cada pantalla. «Listo» lo decide el administrador: hay pasos sin un «hecho» medible (las
 * monedas y las alertas ya traen valores de fábrica).
 */
final readonly class ConfiguracionInicialService
{
    public const string PERMISO = 'configuracion.inicial';

    public function __construct(private RegistroPasosConfiguracion $registro) {}

    public function marcarListo(string $paso): void
    {
        $this->cambiar($paso, true);
    }

    /** Reabrir uno que nunca se marcó no hace nada: no queda una fila ni un «reabierto» en el historial. */
    public function reabrir(string $paso): void
    {
        $this->cambiar($paso, false);
    }

    /** @return list<string> las claves de los pasos registrados que están listos */
    public function listos(): array
    {
        return array_values(array_intersect(
            array_keys($this->registro->pasos()),
            ConfiguracionPaso::query()->where('es_listo', true)->pluck('paso')->all(),
        ));
    }

    /**
     * @param  list<string>|null  $listos  los ya leídos, para no volver a consultarlos
     * @return list<DefinicionPaso> los obligatorios que faltan, en orden: mientras haya, el inicio avisa (D6)
     */
    public function faltan(?array $listos = null): array
    {
        $listos ??= $this->listos();

        return array_values(array_filter($this->registro->pasos(), fn (DefinicionPaso $paso): bool => $paso->obligatorio && ! in_array($paso->clave, $listos, true)));
    }

    /**
     * Si la bienvenida sale al entrar (plan 1.13b, D1–D2): a quien puede configurar, mientras falten obligatorios, hasta
     * que la apague con «Seguir después». Los pasos listos son de todos; apagarla, de cada persona. La ✕ y el gran momento
     * del ingreso se deciden en la pantalla: duran una sesión.
     */
    public function muestraBienvenida(User $usuario): bool
    {
        return $usuario->can(self::PERMISO)
            && ! $this->bienvenidaApagada($usuario)
            && $this->faltan() !== [];
    }

    public function bienvenidaApagada(User $usuario): bool
    {
        return ConfiguracionBienvenida::query()->where('user_id', $usuario->id)->where('es_apagada', true)->exists();
    }

    public function apagarBienvenida(User $usuario): void
    {
        $this->cambiarBienvenida($usuario, true);
    }

    /** Encender una que nunca se apagó no hace nada: no queda una fila ni un cambio en el historial. */
    public function encenderBienvenida(User $usuario): void
    {
        $this->cambiarBienvenida($usuario, false);
    }

    private function cambiar(string $paso, bool $listo): void
    {
        if ($this->registro->paso($paso) === null) {
            throw PasoConfiguracionRechazado::noExiste($paso);
        }

        // Dos clics a la vez no chocan: la fila es única por paso y se cambia bajo candado.
        DB::transaction(function () use ($paso, $listo): void {
            $fila = ConfiguracionPaso::query()->where('paso', $paso)->lockForUpdate()->first();

            if ($fila === null) {
                if (! $listo) {
                    return;
                }

                // La primera vez la crea; si otro la creó al mismo tiempo, la toma (createOrFirst, con su punto de
                // guardado: el choque no aborta la transacción).
                $creada = ConfiguracionPaso::query()->createOrFirst(['paso' => $paso], ['es_listo' => $listo]);
                $fila = ConfiguracionPaso::query()->whereKey($creada->id)->lockForUpdate()->firstOrFail();
            }

            if ($fila->es_listo !== $listo) {
                $fila->update(['es_listo' => $listo]);
            }
        });
    }

    /** Como `cambiar()`: la fila es única por persona y se cambia bajo candado. */
    private function cambiarBienvenida(User $usuario, bool $apagada): void
    {
        DB::transaction(function () use ($usuario, $apagada): void {
            $fila = ConfiguracionBienvenida::query()->where('user_id', $usuario->id)->lockForUpdate()->first();

            if ($fila === null) {
                if (! $apagada) {
                    return;
                }

                $creada = ConfiguracionBienvenida::query()->createOrFirst(['user_id' => $usuario->id], ['es_apagada' => $apagada]);
                $fila = ConfiguracionBienvenida::query()->whereKey($creada->id)->lockForUpdate()->firstOrFail();
            }

            if ($fila->es_apagada !== $apagada) {
                $fila->update(['es_apagada' => $apagada]);
            }
        });
    }
}
