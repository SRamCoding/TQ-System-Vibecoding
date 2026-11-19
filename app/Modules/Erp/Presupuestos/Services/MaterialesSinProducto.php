<?php

declare(strict_types=1);

namespace App\Modules\Erp\Presupuestos\Services;

/**
 * El aviso de los materiales del presupuesto sin producto enlazado (plan 2.9, D13). No bloquea ofertar ni traer al
 * proyecto: hay materiales que Techquk no compra (los del subcontratista) o genéricos. Lo muestra la confirmación de
 * esas dos transiciones; el enlace se completa en el catálogo de insumos, aun con el presupuesto aprobado.
 */
final readonly class MaterialesSinProducto
{
    public const string POR_QUE = 'Compras no podrá comparar cantidades presupuestadas contra compradas para estos.';

    /**
     * @param  list<array{insumoId: int, codigo: string, nombre: string}>  $materiales
     */
    public function __construct(public array $materiales) {}

    public function hayAviso(): bool
    {
        return $this->materiales !== [];
    }

    public function mensaje(): ?string
    {
        if (! $this->hayAviso()) {
            return null;
        }

        $lista = implode(', ', array_map(fn (array $m): string => "{$m['codigo']} {$m['nombre']}", $this->materiales));

        return "Materiales sin producto enlazado: {$lista}. ".self::POR_QUE;
    }
}
