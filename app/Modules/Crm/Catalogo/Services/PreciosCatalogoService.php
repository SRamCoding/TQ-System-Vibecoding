<?php

declare(strict_types=1);

namespace App\Modules\Crm\Catalogo\Services;

use App\Modules\Crm\Catalogo\Models\PrecioProducto;
use App\Modules\Crm\Catalogo\Models\Producto;
use App\Modules\Nucleo\Tiempo\Calendario;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * MVP rápido (ficha t1-catalogo): el catálogo comercial (CRM-06): altas de ítems, precios con vigencia (cada cambio
 * crea un registro; nunca se sobrescribe), código del Estado y la carga masiva de precios pegada desde Excel. Es el
 * mismo catálogo que usan cotizaciones, presupuestos, compras y almacén.
 */
final readonly class PreciosCatalogoService
{
    public const string PERMISO_VER = 'crm.catalogo.ver';

    public const string PERMISO_GESTIONAR = 'crm.catalogo.gestionar';

    /** @param  array{codigo: string, descripcion: string, descripcion_corta?: ?string, unidad_medida_id: int, familia_producto_id?: ?int, linea_solucion_id?: ?int, marca?: ?string, modelo?: ?string, cubso_id?: ?int, es_servicio?: bool, requiere_serie?: bool}  $datos */
    public function crear(array $datos, ?string $precio = null, ?string $costo = null, ?string $margen = null): Producto
    {
        $codigo = Producto::normalizarCodigo($datos['codigo']);

        if ($codigo === '' || trim($datos['descripcion']) === '') {
            throw new RuntimeException('El código y la descripción son obligatorios.');
        }

        if (Producto::query()->where('codigo', $codigo)->exists()) {
            throw new RuntimeException("Ya existe un ítem con el código {$codigo}.");
        }

        return DB::transaction(function () use ($datos, $codigo, $precio, $costo, $margen): Producto {
            $producto = Producto::query()->create([...$datos, 'codigo' => $codigo, 'es_activo' => true]);

            if ($precio !== null) {
                $this->cambiarPrecio($producto->id, $precio, $costo, $margen, CarbonImmutable::parse(Calendario::hoy()));
            }

            return $producto;
        });
    }

    /** Un precio nuevo desde una fecha: el vigente se cierra el día anterior y queda en la historia. */
    public function cambiarPrecio(int $productoId, string $precio, ?string $costo, ?string $margen, CarbonImmutable $desde, string $origen = 'manual'): PrecioProducto
    {
        if (bccomp($precio, '0', 4) <= 0) {
            throw new RuntimeException('El precio va mayor que cero.');
        }

        if ($margen !== null && (bccomp($margen, '0', 2) < 0 || bccomp($margen, '100', 2) >= 0)) {
            throw new RuntimeException('El margen objetivo va de 0 a 99.99 %.');
        }

        return DB::transaction(function () use ($productoId, $precio, $costo, $margen, $desde, $origen): PrecioProducto {
            Producto::query()->lockForUpdate()->findOrFail($productoId);
            PrecioProducto::query()->where('producto_id', $productoId)->where('es_vigente', true)
                ->update(['es_vigente' => false, 'vigente_hasta' => $desde->subDay()->toDateString()]);

            return PrecioProducto::query()->create([
                'producto_id' => $productoId, 'moneda_id' => (int) DB::table('monedas')->where('codigo', 'PEN')->value('id'),
                'precio' => $precio, 'costo_referencial' => $costo, 'margen_objetivo' => $margen,
                'vigente_desde' => $desde->toDateString(), 'es_vigente' => true, 'origen' => $origen,
            ]);
        });
    }

    public function descontinuar(int $productoId, bool $descontinuado): void
    {
        Producto::query()->whereKey($productoId)->update(['es_activo' => ! $descontinuado]);
    }

    /**
     * Carga masiva pegada desde Excel: una fila por ítem, «código, precio, costo, margen» separados por tabulador,
     * punto y coma o coma (la primera fila puede ser el encabezado). Lo que no se entiende no se aplica y se informa.
     *
     * @return array{aplicados: int, errores: list<string>}
     */
    public function cargarPrecios(string $texto, CarbonImmutable $desde): array
    {
        $aplicados = 0;
        $errores = [];

        foreach (preg_split('/\R/', trim($texto)) ?: [] as $n => $linea) {
            if (trim($linea) === '') {
                continue;
            }

            $celdas = array_map('trim', str_contains($linea, "\t") ? explode("\t", $linea) : (str_contains($linea, ';') ? explode(';', $linea) : explode(',', $linea)));
            [$codigo, $precio, $costo, $margen] = array_pad($celdas, 4, '');
            $fila = $n + 1;

            if ($n === 0 && ! is_numeric(str_replace(',', '', $precio))) {
                continue;
            }

            $producto = Producto::query()->where('codigo', Producto::normalizarCodigo($codigo))->first();

            if ($producto === null) {
                $errores[] = "Fila {$fila}: no hay ningún ítem con el código «{$codigo}».";

                continue;
            }

            $num = fn (string $v): ?string => $v === '' ? null : (is_numeric($v) ? $v : null);

            if ($num($precio) === null) {
                $errores[] = "Fila {$fila}: el precio «{$precio}» no es un número.";

                continue;
            }

            try {
                $this->cambiarPrecio($producto->id, $precio, $num($costo), $num(rtrim($margen, ' %')), $desde, 'carga');
                $aplicados++;
            } catch (RuntimeException $e) {
                $errores[] = "Fila {$fila}: {$e->getMessage()}";
            }
        }

        return ['aplicados' => $aplicados, 'errores' => $errores];
    }
}
