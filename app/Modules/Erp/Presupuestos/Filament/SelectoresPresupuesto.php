<?php

declare(strict_types=1);

namespace App\Modules\Erp\Presupuestos\Filament;

use App\Modules\Crm\Catalogo\Services\CatalogoRechazado;
use App\Modules\Crm\Catalogo\Services\CatalogoService;
use App\Modules\Crm\Catalogo\Services\ResumenProducto;
use App\Modules\Erp\Presupuestos\Models\Insumo;
use App\Modules\Nucleo\Models\UnidadMedida;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Select;

/**
 * Selectores de las pantallas de Presupuestos (plan 2.10). Solo presentación: las reglas (la unidad del producto, el
 * insumo activo) las valida el servicio. Todo selector incluye el valor que el registro ya tiene (AGENTS.md).
 */
final class SelectoresPresupuesto
{
    /** @var array<string, string> */
    public const array TIPOS = [
        Insumo::MATERIAL => 'Material',
        Insumo::MANO_OBRA => 'Mano de obra',
        Insumo::EQUIPO => 'Equipo',
        Insumo::SUBCONTRATO => 'Subcontrato',
    ];

    public static function tipo(string $nombre = 'tipo'): Field
    {
        return Select::make($nombre)->options(self::TIPOS)->native(false);
    }

    /** Las unidades activas y, si el valor actual se desactivó, también esa. */
    public static function unidad(string $nombre = 'unidad_medida_id'): Field
    {
        return Select::make($nombre)->searchable()->options(function (mixed $state): array {
            $consulta = UnidadMedida::query()->where('es_activa', true);

            if (is_numeric($state)) {
                $consulta->orWhere('id', (int) $state);
            }

            return $consulta->orderBy('codigo')->get()
                ->mapWithKeys(fn (UnidadMedida $u): array => [$u->id => "{$u->codigo} · {$u->nombre}"])->all();
        });
    }

    /**
     * Un producto del catálogo de C4 (D8 de la 2.9): se busca por código o descripción; el ya enlazado se muestra
     * aunque esté descontinuado.
     */
    public static function producto(string $nombre = 'producto_id'): Field
    {
        $etiqueta = fn (ResumenProducto $p): string => "{$p->codigo} · {$p->nombre} ({$p->unidad})";

        return Select::make($nombre)->searchable()
            ->getSearchResultsUsing(fn (string $search): array => collect(app(CatalogoService::class)->buscar($search))
                ->mapWithKeys(fn (ResumenProducto $p): array => [$p->id => $etiqueta($p)])->all())
            ->getOptionLabelUsing(function (mixed $value) use ($etiqueta): ?string {
                try {
                    return is_numeric($value) ? $etiqueta(app(CatalogoService::class)->resumen((int) $value)) : null;
                } catch (CatalogoRechazado) {
                    return null;
                }
            });
    }
}
