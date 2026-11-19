<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Estados;

/**
 * Un motivo con el que nace una lista (plan 2.6, D3). El sincronizador lo crea una sola vez, si su código no está en
 * la lista: después se renombra, desactiva o completa en Configuración → Motivos, y el código no lo pisa.
 */
final readonly class MotivoInicial
{
    public function __construct(
        public string $codigo,
        public string $nombre,
        /** Si elegirlo marca un cambio pedido por el cliente (`motivos.es_cambio_cliente`). */
        public bool $esCambioCliente = false,
    ) {}
}
