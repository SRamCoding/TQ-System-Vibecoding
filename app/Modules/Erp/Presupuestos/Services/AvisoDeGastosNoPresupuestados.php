<?php

declare(strict_types=1);

namespace App\Modules\Erp\Presupuestos\Services;

use App\Modules\Erp\Presupuestos\Events\PosibleAdicionalRegistrado;
use App\Modules\Erp\Proyectos\Services\ProyectosService;
use App\Modules\Nucleo\Imputacion\LineaNoPresupuestada;
use App\Modules\Nucleo\Models\Motivo;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Services\AlertasService;

/**
 * Los avisos del gasto no presupuestado (plan 2.6, D6; v4, parte 7), una vez por línea. Al enviar, «Gasto no
 * presupuestado registrado». Al aprobar, si el motivo es un cambio pedido por el cliente (`motivos.es_cambio_cliente`,
 * D3), «Posible adicional detectado» y el evento C11. Las dos llegan al responsable de la PC-99 (el jefe del proyecto)
 * y a los roles que tenga cada alerta en Configuración → Alertas. Sin montos: viajan por correo (regla 10).
 */
final class AvisoDeGastosNoPresupuestados
{
    public const string ALERTA_REGISTRADO = 'erp.presupuestos.gasto-no-presupuestado';

    public const string ALERTA_POSIBLE_ADICIONAL = 'erp.presupuestos.posible-adicional';

    /** @param  list<LineaNoPresupuestada>  $lineas */
    public function alEnviar(array $lineas, PartidasService $partidas, ProyectosService $proyectos): void
    {
        foreach ($lineas as $linea) {
            // Si una falla, las demás líneas igual avisan.
            rescue(fn () => $this->emitir(self::ALERTA_REGISTRADO, $linea, $partidas, $proyectos));
        }
    }

    /** @param  list<LineaNoPresupuestada>  $lineas */
    public function alAprobar(array $lineas, PartidasService $partidas, ProyectosService $proyectos): void
    {
        foreach ($lineas as $linea) {
            if (! $this->esCambioDelCliente($linea->motivoId)) {
                continue;
            }

            // El evento sale primero y aunque la alerta esté apagada o falle: Contratos no depende de ella. La alerta sale
            // una vez por línea; el evento, en cada aprobación (Contratos reconoce la línea por su imputación).
            PosibleAdicionalRegistrado::dispatch($linea->proyectoId, $linea->imputacionId, $linea->montoSoles);
            rescue(fn () => $this->emitir(self::ALERTA_POSIBLE_ADICIONAL, $linea, $partidas, $proyectos));
        }
    }

    private function emitir(string $alerta, LineaNoPresupuestada $linea, PartidasService $partidas, ProyectosService $proyectos): void
    {
        $responsable = $partidas->resumen($linea->partidaId)?->responsableUserId;

        app(AlertasService::class)->emitir($alerta, "imputacion:{$linea->imputacionId}", [
            'proyecto' => $proyectos->resumen($linea->proyectoId)->codigo,
            'motivo' => $linea->motivoId === null ? 'sin motivo' : (string) Motivo::query()->whereKey($linea->motivoId)->value('nombre'),
        ], ademas: User::query()->whereKey($responsable)->get());
    }

    private function esCambioDelCliente(?int $motivoId): bool
    {
        return $motivoId !== null && Motivo::query()->whereKey($motivoId)->where('es_cambio_cliente', true)->exists();
    }
}
