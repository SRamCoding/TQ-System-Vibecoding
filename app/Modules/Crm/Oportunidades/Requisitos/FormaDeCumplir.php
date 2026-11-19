<?php

declare(strict_types=1);

namespace App\Modules\Crm\Oportunidades\Requisitos;

/**
 * Cómo se cumple un requisito ahí mismo, en la compuerta (plan 2.21d, D8): el botón que la muestra y la **acción
 * registrada del módulo dueño** que lo cumple, con sus datos completos. Una sola lógica (2.21c, D5): la compuerta no sabe
 * nada del requisito, solo ejecuta su acción. Cuando la acción pide más de lo que se sabe (aceptar una cotización pide
 * la fecha y el documento de respaldo con su archivo), la forma es un enlace a la pantalla del documento (`url`).
 */
final readonly class FormaDeCumplir
{
    /** @param  array<string, mixed>  $datos */
    public function __construct(
        public string $etiqueta,
        public ?string $accion = null,
        public array $datos = [],
        public ?string $url = null,
    ) {}

    /** @param  array<string, mixed>  $datos */
    public static function conAccion(string $etiqueta, string $accion, array $datos): self
    {
        return new self($etiqueta, $accion, $datos);
    }

    public static function enlace(string $etiqueta, string $url): self
    {
        return new self($etiqueta, url: $url);
    }
}
