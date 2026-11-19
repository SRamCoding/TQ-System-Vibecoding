<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Imputacion;

/** Una opción del selector (un proyecto o una partida): su id y cómo se muestra. */
final readonly class OpcionDestino
{
    public function __construct(
        public int $id,
        public string $etiqueta,
    ) {}
}
