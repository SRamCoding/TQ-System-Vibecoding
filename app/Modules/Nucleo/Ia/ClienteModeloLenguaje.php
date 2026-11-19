<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Ia;

/**
 * Todo acceso a un modelo de lenguaje pasa por aquí (ia.md, plan 1.11 D5 y D7). Cada proveedor es una implementación
 * registrada en ClientesModeloLenguaje con su código (`ia_proveedores.implementacion`). Una llamada, sin reintentos: los
 * decide IaService, que registra cada intento en `ia_llamadas`. Nunca lanza por un error del proveedor: lo devuelve
 * como resultado. Nunca dentro de una transacción de base de datos.
 */
interface ClienteModeloLenguaje
{
    /** @param  string  $clave  la clave del proveedor, leída de su variable de entorno */
    public function llamar(PedidoModeloIa $pedido, string $clave): RespuestaModeloIa;
}
