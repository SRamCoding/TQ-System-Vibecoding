<?php

declare(strict_types=1);

namespace App\Modules\Erp\Presupuestos\Services;

use App\Modules\Nucleo\Excepciones\ImputacionRechazada;
use App\Modules\Nucleo\Imputacion\DestinoImputacion;

/**
 * C1 · La partida como destino de costo (ADR 0020; plan 2.1, D1, D6 y D7). Nadie escribe en `partidas_control` ni
 * calcula saldos fuera de Presupuestos. La real es `PartidasEnBase`; en pruebas, `App\Modules\Erp\Presupuestos\Services\EnMemoria\PartidasEnMemoria`
 * con `$this->app->instance()`. Las dos pasan las mismas pruebas del contrato.
 */
interface PartidasService
{
    /**
     * Las hojas activas de un árbol aprobado de un proyecto que acepta costos, por código o nombre en todo el árbol
     * (también en los padres), con su ruta. Sin la PC-99: esa se pide con `noPresupuestada()`.
     *
     * @return list<ResumenPartida>
     */
    public function buscar(int $proyectoId, string $texto = '', int $limite = 20): array;

    /**
     * Una partida por su id, aunque ya no esté activa: para mostrar lo que una línea ya tiene guardado (plan 2.2, D2).
     * Null si no existe.
     */
    public function resumen(int $partidaId): ?ResumenPartida;

    /**
     * Con `$bloquear`, bloquea la fila de la partida (`lockForUpdate`) dentro de la transacción que la pide: para
     * revisar el exceso al aprobar en la misma transacción que sube el comprometido (plan 2.2, D3 y D4). Quien bloquea
     * varias lo hace en orden de id ascendente.
     *
     * @throws ImputacionRechazada si la partida no existe
     */
    public function saldo(int $partidaId, bool $bloquear = false): SaldoPartida;

    /**
     * Los saldos de varias partidas de una vez, sin bloquear: los mismos números que `saldo()` una por una, para las
     * pantallas que muestran muchas (la portada de Proyectos, el Resumen). Las que no existen no aparecen.
     *
     * @param  list<int>  $partidaIds
     * @return array<int, SaldoPartida> por id de partida
     */
    public function saldos(array $partidaIds): array;

    /**
     * El destino para `ImputacionService::destinar()`. Rechaza (con `ImputacionRechazada`) una partida inactiva, que
     * no es hoja, de un proyecto que no acepta costos o de un árbol no aprobado. La PC-99 exige un motivo de «gasto
     * no presupuestado» y un comentario; su centro de costo de referencia es opcional.
     */
    public function destino(int $partidaId, ?int $motivoId = null, ?string $comentario = null, ?int $centroCostoId = null): DestinoImputacion;

    /** La PC-99 del proyecto, o null si su árbol todavía no se aprobó. */
    public function noPresupuestada(int $proyectoId): ?ResumenPartida;
}
