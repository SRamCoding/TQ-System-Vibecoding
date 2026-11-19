<?php

declare(strict_types=1);

namespace App\Modules\Erp\Presupuestos\Services;

use App\Modules\Erp\Proyectos\Services\ProyectosService;
use App\Modules\Nucleo\Imputacion\SaldoConLinea;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Services\AlertasService;
use App\Modules\Nucleo\Services\ParametrosService;

/**
 * La alerta «Partida excedida o al 90 %» (plan 2.2, D6; v4, parte 7), después de aprobar líneas. Una vez por cruce:
 * el hecho es partida + umbral + una huella del presupuestado, sin el monto (si una versión nueva del árbol cambia el presupuestado, puede volver a
 * salir). Si una sola aprobación salta de menos del umbral a excedida, sale solo la de excedida y la del umbral queda
 * dada por emitida. Llega al responsable de la partida y a los roles que tenga la alerta en Configuración → Alertas.
 */
final class AvisoDeSaldos
{
    public const string ALERTA = 'erp.presupuestos.partida-excedida';

    public const string PARAMETRO_UMBRAL = 'presupuestos.alerta_partida_porcentaje';

    /**
     * @param  list<SaldoConLinea>  $saldos
     */
    public function avisar(array $saldos, PartidasService $partidas, ProyectosService $proyectos): void
    {
        $alertas = app(AlertasService::class);
        $umbral = app(ParametrosService::class)->decimal(self::PARAMETRO_UMBRAL);

        foreach ($saldos as $saldo) {
            $resumen = $partidas->resumen($saldo->partidaId);

            if ($resumen === null || $resumen->esNoPresupuestada) {
                continue;
            }

            $porcentaje = $saldo->porcentajeDespues();
            // El hecho cambia si cambia el presupuestado (una versión nueva del árbol), sin guardar el monto (regla 10).
            $presupuesto = substr(hash('sha256', $saldo->presupuestado), 0, 12);
            $alUmbral = "partida:{$saldo->partidaId}|umbral|{$presupuesto}";

            if (! $saldo->excede() && ($porcentaje === null || bccomp($porcentaje, $umbral, 2) < 0)) {
                continue;
            }

            $datos = [
                'partida' => "{$resumen->codigo} {$resumen->nombre}",
                'proyecto' => $proyectos->resumen($resumen->proyectoId)->codigo,
                // Sin montos: los costos son datos sensibles y la alerta viaja por correo (regla 10).
                'porcentaje' => $porcentaje === null ? 'sin presupuesto' : "{$porcentaje} %",
            ];
            $responsable = User::query()->whereKey($resumen->responsableUserId)->get();

            if ($saldo->excede()) {
                $alertas->emitir(self::ALERTA, "partida:{$saldo->partidaId}|excedida|{$presupuesto}", $datos, ademas: $responsable);
                $alertas->darPorEmitida(self::ALERTA, $alUmbral);

                continue;
            }

            $alertas->emitir(self::ALERTA, $alUmbral, $datos, ademas: $responsable);
        }
    }
}
