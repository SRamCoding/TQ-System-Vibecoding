<?php

declare(strict_types=1);

namespace App\Modules\Erp\Compras\Services;

use App\Modules\Erp\Compras\Models\Herramienta;
use App\Modules\Nucleo\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * MV-028: el control de las herramientas propias: qué herramienta está en qué obra, quién la tiene y cuándo debe volver.
 * Solo se controla dónde está; si su uso cuesta al proyecto queda pendiente de Sebastián.
 */
final class HerramientasService
{
    public function registrar(string $nombre, string $tipo, ?string $marcaModelo, ?string $serie): Herramienta
    {
        if (! isset(Herramienta::TIPOS[$tipo])) {
            throw new RuntimeException('Elige el tipo de herramienta.');
        }

        return DB::transaction(function () use ($nombre, $tipo, $marcaModelo, $serie): Herramienta {
            $n = (int) Herramienta::query()->count() + 1;

            return Herramienta::query()->create(['codigo' => sprintf('HER-%03d', $n), 'nombre' => trim($nombre), 'tipo' => $tipo, 'marca_modelo' => $marcaModelo, 'serie' => $serie]);
        });
    }

    public function enviarAObra(int $herramientaId, int $proyectoId, int $colaboradorId, CarbonImmutable $fecha, CarbonImmutable $debeVolver, ?User $quien, ?string $nota = null): void
    {
        DB::transaction(function () use ($herramientaId, $proyectoId, $colaboradorId, $fecha, $debeVolver, $quien, $nota): void {
            $h = Herramienta::query()->lockForUpdate()->findOrFail($herramientaId);

            if ($h->estado !== 'en_almacen') {
                throw new RuntimeException('La herramienta no está en el almacén: primero que vuelva.');
            }

            if ($debeVolver->lt($fecha)) {
                throw new RuntimeException('La fecha de vuelta no puede ser antes de la salida.');
            }

            $h->update(['estado' => 'en_obra', 'proyecto_id' => $proyectoId, 'colaborador_id' => $colaboradorId, 'salio' => $fecha, 'debe_volver' => $debeVolver]);
            $h->movimientos()->create(['tipo' => 'salida', 'fecha' => $fecha, 'proyecto_id' => $proyectoId, 'colaborador_id' => $colaboradorId, 'debe_volver' => $debeVolver, 'nota' => $nota, 'user_id' => $quien?->id]);
        });
    }

    /** Vuelve al almacén (o a mantenimiento si se indica). */
    public function devolver(int $herramientaId, CarbonImmutable $fecha, bool $aMantenimiento, ?User $quien, ?string $nota = null): void
    {
        DB::transaction(function () use ($herramientaId, $fecha, $aMantenimiento, $quien, $nota): void {
            $h = Herramienta::query()->lockForUpdate()->findOrFail($herramientaId);

            if (! in_array($h->estado, ['en_obra', 'en_mantenimiento'], true)) {
                throw new RuntimeException('La herramienta ya está en el almacén.');
            }

            $h->movimientos()->create(['tipo' => $aMantenimiento ? 'mantenimiento' : 'devolucion', 'fecha' => $fecha, 'proyecto_id' => $h->proyecto_id, 'colaborador_id' => $h->colaborador_id, 'nota' => $nota, 'user_id' => $quien?->id]);
            $h->update(['estado' => $aMantenimiento ? 'en_mantenimiento' : 'en_almacen', 'proyecto_id' => null, 'colaborador_id' => null, 'salio' => null, 'debe_volver' => null]);
        });
    }
}
