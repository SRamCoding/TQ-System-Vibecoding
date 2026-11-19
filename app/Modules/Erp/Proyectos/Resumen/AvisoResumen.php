<?php

declare(strict_types=1);

namespace App\Modules\Erp\Proyectos\Resumen;

/** Algo que necesita atención hoy (plan 2.61, D6), con adónde ir a resolverlo. */
final readonly class AvisoResumen
{
    /** @param  string  $tono  `warning` o `danger` */
    public function __construct(
        public string $texto,
        public string $tono = 'warning',
        public ?string $url = null,
    ) {}
}
