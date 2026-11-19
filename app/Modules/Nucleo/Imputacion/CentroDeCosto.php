<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Imputacion;

use App\Modules\Nucleo\Excepciones\ImputacionRechazada;
use App\Modules\Nucleo\Models\CentroCosto;

/** El gasto directo de la empresa, con su centro de costo (plan 1.9). Solo uno activo. */
final readonly class CentroDeCosto implements DestinoImputacion
{
    private CentroCosto $centro;

    public function __construct(int $centroCostoId)
    {
        $this->centro = CentroCosto::query()->whereKey($centroCostoId)->where('es_activo', true)->first()
            ?? throw ImputacionRechazada::conMotivo('Ese centro de costo no existe o está desactivado.');
    }

    public static function porCodigo(string $codigo): self
    {
        $id = CentroCosto::query()->where('codigo', $codigo)->value('id')
            ?? throw ImputacionRechazada::conMotivo("No existe el centro de costo «{$codigo}».");

        return new self((int) $id);
    }

    public function columnas(): array
    {
        return ['centro_costo_id' => $this->centro->id];
    }

    public function revisarAlDestinar(): void
    {
        if (! CentroCosto::query()->whereKey($this->centro->id)->where('es_activo', true)->sharedLock()->exists()) {
            throw ImputacionRechazada::conMotivo('Ese centro de costo no existe o está desactivado.');
        }
    }

    public function descripcion(): string
    {
        return "el centro de costo {$this->centro->nombre}";
    }
}
