<?php

declare(strict_types=1);

namespace App\Modules\Crm\Catalogo\Services\EnMemoria;

use App\Modules\Crm\Catalogo\Models\Producto;
use App\Modules\Crm\Catalogo\Services\CatalogoRechazado;
use App\Modules\Crm\Catalogo\Services\CatalogoService;
use App\Modules\Crm\Catalogo\Services\PrecioVigente;
use App\Modules\Crm\Catalogo\Services\ResumenProducto;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * C4 en memoria (plan 2.60, D1), para quien prueba su módulo sin crear productos de verdad:
 * `$this->app->instance(CatalogoService::class, $falsa = new CatalogoEnMemoria)` y luego `$falsa->agregar(...)`. Pasa
 * las mismas pruebas del contrato que `CatalogoEnBase` (`tests/Modules/Crm/Catalogo/ContratoCatalogoTest.php`). No
 * revisa que la unidad exista: eso lo prueba la real.
 */
final class CatalogoEnMemoria implements CatalogoService
{
    /** @var array<int, ResumenProducto> */
    private array $productos = [];

    /** Carga un producto como la prueba lo necesite. */
    public function agregar(
        string $codigo,
        string $descripcion,
        ?string $descripcionCorta = null,
        int $unidadMedidaId = 1,
        string $unidad = 'UND',
        bool $requiereSerie = false,
        bool $esServicio = false,
        bool $esActivo = true,
    ): ResumenProducto {
        // Como la real: en mayúsculas (D6) y único (en la base); la prueba que lo repite falla igual con las dos.
        $codigo = Producto::normalizarCodigo($codigo);

        foreach ($this->productos as $existente) {
            if ($existente->codigo === $codigo) {
                throw new InvalidArgumentException("El código «{$codigo}» ya está en el catálogo.");
            }
        }

        $id = count($this->productos) + 1;

        return $this->productos[$id] = new ResumenProducto($id, $codigo, $descripcionCorta ?? $descripcion, $descripcion, $unidadMedidaId, $unidad, $requiereSerie, $esServicio, $esActivo);
    }

    public function buscar(string $texto = ''): array
    {
        $texto = self::normalizar($texto);
        $encontrados = array_values(array_filter(
            $this->productos,
            fn (ResumenProducto $p): bool => $p->esActivo
                && ($texto === '' || str_contains(self::normalizar($p->codigo), $texto) || str_contains(self::normalizar($p->descripcion), $texto) || str_contains(self::normalizar($p->nombre), $texto)),
        ));
        // D3: el código exacto primero; después, por código byte a byte (como `COLLATE "C"` en la real).
        $exacto = fn (ResumenProducto $p): bool => $texto !== '' && self::normalizar($p->codigo) === $texto;
        usort($encontrados, fn (ResumenProducto $a, ResumenProducto $b): int => ($exacto($b) <=> $exacto($a)) ?: strcmp($a->codigo, $b->codigo));

        return array_slice($encontrados, 0, self::LIMITE);
    }

    public function resumen(int $productoId): ResumenProducto
    {
        return $this->productos[$productoId] ?? throw CatalogoRechazado::noExiste();
    }

    private static function normalizar(string $texto): string
    {
        return mb_strtolower(Str::ascii(trim($texto)));
    }

    public function precioVigente(int $productoId): ?PrecioVigente
    {
        return null;
    }
}
