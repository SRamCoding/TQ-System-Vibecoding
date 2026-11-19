<?php

declare(strict_types=1);

namespace App\Modules\Erp\Proyectos\Resumen;

/** Una fila del desglose de un dato del Resumen (plan 2.61, D5): lo que suma, hasta el documento (`url`). */
final readonly class FilaDesglose
{
    public function __construct(
        public string $etiqueta,
        public string $valor,
        public ?string $url = null,
    ) {}
}
