<?php

declare(strict_types=1);

namespace Tests\Modules\Crm\Oportunidades\Fixtures;

use App\Modules\Crm\Oportunidades\Models\Oportunidad;
use App\Modules\Nucleo\Models\Estado;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Services\ParametrosService;
use App\Modules\Nucleo\Services\SincronizadorEstados;
use App\Modules\Nucleo\Services\TransicionesService;

/**
 * Oportunidades reales para pruebas de otros módulos (Cuentas marca clientes con su id) y de este: nacen por el servicio
 * del núcleo, en Prospecto y con su número. Una clase, no una función global: cada archivo de pruebas pasa solo.
 */
final class OportunidadesDePrueba
{
    /** @param  array<string, mixed>  $datos */
    public static function crear(int $cuentaId, array $datos = [], ?User $quien = null): Oportunidad
    {
        if (! Estado::query()->where('documento_type', 'oportunidad')->exists()) {
            app(SincronizadorEstados::class)->sincronizar();
        }

        $oportunidad = Oportunidad::factory()->make(['cuenta_id' => $cuentaId, ...$datos]);

        return app(TransicionesService::class)->crear($oportunidad, $quien ?? User::factory()->create())->refresh();
    }

    /**
     * Apaga lo que Cotizaciones exige para enviar la propuesta y para ganar (plan 2.22, D11): las pruebas del embudo
     * prueban sus propias reglas; las de la cotización están en tests/Modules/Crm/Cotizaciones. Con ParametrosSeeder.
     */
    public static function sinExigirCotizacion(): void
    {
        $parametros = app(ParametrosService::class);

        foreach (['crm.cotizacion_exigir_emitida_para_propuesta', 'crm.cotizacion_exigir_aceptada_para_ganar'] as $nombre) {
            $parametros->cambiar($nombre, false, $parametros->ficha($nombre)['version']);
        }
    }

    public static function id(int $cuentaId): int
    {
        return self::crear($cuentaId)->id;
    }
}
