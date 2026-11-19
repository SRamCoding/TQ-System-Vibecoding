<?php

declare(strict_types=1);

namespace App\Modules\Crm\Catalogo\Services;

use App\Modules\Crm\Catalogo\Models\PrecioProducto;
use App\Modules\Crm\Catalogo\Models\Producto;
use App\Modules\Nucleo\Models\Concerns\BusquedaSinAcentos;

/** C4 sobre la tabla `productos` (plan 2.60, D1). */
final readonly class CatalogoEnBase implements CatalogoService
{
    public function buscar(string $texto = ''): array
    {
        $consulta = Producto::query()->with('unidadMedida')->where('es_activo', true);
        BusquedaSinAcentos::aplicar($consulta, $texto, ['codigo', 'descripcion', 'descripcion_corta']);

        return $consulta
            // D3: el código exacto primero; después, por código byte a byte (como la versión en memoria).
            ->orderByRaw('lower(unaccent(codigo)) = lower(unaccent(?)) DESC', [trim($texto)])
            ->orderByRaw('codigo COLLATE "C"')
            ->limit(self::LIMITE)
            ->get()
            ->map(fn (Producto $producto): ResumenProducto => $this->resumir($producto))
            ->values()
            ->all();
    }

    public function resumen(int $productoId): ResumenProducto
    {
        $producto = Producto::query()->with('unidadMedida')->find($productoId) ?? throw CatalogoRechazado::noExiste();

        return $this->resumir($producto);
    }

    public function precioVigente(int $productoId): ?PrecioVigente
    {
        $p = PrecioProducto::query()->where('producto_id', $productoId)->where('es_vigente', true)->first();

        return $p === null ? null : new PrecioVigente((string) $p->precio, $p->costo_referencial === null ? null : (string) $p->costo_referencial, $p->margen_objetivo === null ? null : (string) $p->margen_objetivo);
    }

    private function resumir(Producto $producto): ResumenProducto
    {
        return new ResumenProducto(
            id: $producto->id,
            codigo: $producto->codigo,
            nombre: $producto->descripcion_corta ?? $producto->descripcion,
            descripcion: $producto->descripcion,
            unidadMedidaId: $producto->unidad_medida_id,
            unidad: $producto->unidadMedida->abreviatura,
            requiereSerie: $producto->requiere_serie,
            esServicio: $producto->es_servicio,
            esActivo: $producto->es_activo,
        );
    }
}
