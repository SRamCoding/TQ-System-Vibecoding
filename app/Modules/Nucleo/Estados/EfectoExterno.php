<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Estados;

use Illuminate\Database\Eloquent\Model;

/**
 * Un efecto que sale del sistema: correo, aviso, servicio externo (plan 1.5, D3, ajuste 3). Dentro de la transacción
 * solo se prepara y se guarda su fila; un job lo ejecuta después del commit, fuera de toda transacción, al menos una
 * vez (EfectosExternosService). Si falla, el estado no se deshace. Al anular, `revertir()` también corre por cola, si
 * salió o pudo salir.
 *
 * `$claveIdempotencia` es la misma en cada reintento: pásala al servicio externo para que descarte duplicados. Con
 * correo SMTP (un Message-ID estable) solo los reduce, no los elimina.
 */
interface EfectoExterno
{
    /**
     * Dentro de la transacción: toma lo que el job va a necesitar (ids, destinatarios, montos como texto decimal).
     * Se guarda en `efectos_aplicados.datos`. No envía nada.
     *
     * @return array<string, mixed>
     */
    public function preparar(Model $documento): array;

    /**
     * Después del commit, en el job y sin transacción: hace el envío con lo que se preparó.
     *
     * @param  array<string, mixed>  $datos
     */
    public function ejecutar(Model $documento, array $datos, string $claveIdempotencia): void;

    /**
     * Después de anular, en el job y sin transacción. Lo que no se deshace (un aviso ya leído) lo dice: avisa que se
     * anuló.
     *
     * @param  array<string, mixed>  $datos
     */
    public function revertir(Model $documento, array $datos, string $claveIdempotencia): void;
}
