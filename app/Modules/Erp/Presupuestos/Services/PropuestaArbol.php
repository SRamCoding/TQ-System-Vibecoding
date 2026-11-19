<?php

declare(strict_types=1);

namespace App\Modules\Erp\Presupuestos\Services;

/**
 * Lo que se propone para llenar el árbol de un proyecto (plan 2.9b; ADR 0022): sus partidas, el costo presupuestado y
 * el responsable por omisión. Viene de cualquier origen (el presupuesto aprobado o una plantilla, 2.11) y no
 * crea nada: la persona la revisa y la confirma `ArbolPartidasService::crearDesdePropuesta()`.
 */
final readonly class PropuestaArbol
{
    /**
     * @param  list<NodoPropuesto>  $nodos  en orden de código
     */
    public function __construct(
        public int $proyectoId,
        /** El borrador vacío que se llena; nulo si el proyecto todavía no tiene árbol (D3). */
        public ?int $arbolId,
        public string $costoPresupuestado,
        /** El jefe del proyecto (D4); nulo si no puede responder, y entonces se elige antes de confirmar. */
        public ?int $responsableUserId,
        public array $nodos,
        /** El presupuesto aprobado del que salió (D6); nulo desde otro origen. */
        public ?int $presupuestoId = null,
        /** La versión de la plantilla de la que salió (2.11, D3); nula desde otro origen. */
        public ?int $plantillaVersionId = null,
    ) {}

    /** La misma propuesta con lo que pone su origen al final: la versión de la plantilla y el costo elegido (2.11, D8). */
    public function con(?int $plantillaVersionId = null, ?string $costoPresupuestado = null): self
    {
        return new self(
            $this->proyectoId,
            $this->arbolId,
            $costoPresupuestado ?? $this->costoPresupuestado,
            $this->responsableUserId,
            $this->nodos,
            $this->presupuestoId,
            $plantillaVersionId ?? $this->plantillaVersionId,
        );
    }

    /** La suma de las hojas propuestas. */
    public function totalHojas(): string
    {
        return array_reduce($this->nodos, fn (string $suma, NodoPropuesto $n): string => $n->esHoja ? bcadd($suma, $n->monto ?? '0', 2) : $suma, '0.00');
    }
}
