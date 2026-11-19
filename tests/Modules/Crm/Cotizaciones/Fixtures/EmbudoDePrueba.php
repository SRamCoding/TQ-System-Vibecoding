<?php

declare(strict_types=1);

namespace Tests\Modules\Crm\Cotizaciones\Fixtures;

use App\Modules\Crm\Cuentas\Models\Cuenta;
use App\Modules\Crm\Oportunidades\Estados\DefinicionOportunidad;
use App\Modules\Crm\Oportunidades\Models\Oportunidad;
use App\Modules\Nucleo\Models\Estado;
use App\Modules\Nucleo\Models\HistorialEstado;
use App\Modules\Nucleo\Models\PartyContacto;
use App\Modules\Nucleo\Services\TransicionesService;
use Spatie\Permission\Models\Permission;

/**
 * Mover la oportunidad de una cotización de prueba por el embudo (plan 2.22, paso 6), con los permisos del ejecutivo y
 * cumpliendo lo que pide calificar. Una clase, no funciones globales: cada archivo de pruebas pasa solo.
 */
final class EmbudoDePrueba
{
    public static function permisos(CotizacionesDePrueba $cot): void
    {
        foreach (['crm.oportunidades.editar-propias', ...array_map(fn (string $c): string => DefinicionOportunidad::CLAVE.".{$c}", ['calificar', 'enviar_propuesta', 'negociar', 'ganar', 'perder', 'anular'])] as $permiso) {
            $cot->ejecutivo->givePermissionTo(Permission::findOrCreate($permiso));
        }
    }

    /** Cumple lo que pide calificar (plan 2.21, D9) y la pasa a Calificada. */
    public static function calificada(CotizacionesDePrueba $cot): Oportunidad
    {
        $o = $cot->oportunidad->refresh();
        $decisor = PartyContacto::factory()->create(['party_id' => Cuenta::query()->findOrFail($o->cuenta_id)->party_id]);
        $o->forceFill(['necesidad_confirmada' => true, 'presupuesto_confirmado' => true, 'decisor_contacto_id' => $decisor->id])->save();
        self::mover($cot, 'calificar');

        return $o->refresh();
    }

    public static function mover(CotizacionesDePrueba $cot, string $transicion): HistorialEstado
    {
        $o = $cot->oportunidad->refresh();

        return app(TransicionesService::class)->transicionar($o, $transicion, $o->version, $cot->ejecutivo);
    }

    public static function etapa(CotizacionesDePrueba $cot): string
    {
        return (string) Estado::query()->whereKey($cot->oportunidad->refresh()->estado_id)->value('codigo');
    }
}
