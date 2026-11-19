<?php

declare(strict_types=1);

namespace App\Modules\Erp\Presupuestos\Services;

use App\Modules\Crm\Catalogo\Services\CatalogoRechazado;
use App\Modules\Crm\Catalogo\Services\CatalogoService;
use App\Modules\Erp\Presupuestos\Excepciones\PresupuestoRechazado;
use App\Modules\Erp\Presupuestos\Models\Insumo;
use App\Modules\Erp\Presupuestos\Models\LineaApu;
use App\Modules\Nucleo\Excepciones\VersionDesactualizada;
use App\Modules\Nucleo\Models\UnidadMedida;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * El catálogo de insumos del APU (plan 2.9, D8). Un material puede ir enlazado a un producto de C4: uno a uno y con la
 * misma unidad (lo pregunta a C4, sin tocar sus modelos). «Precio sin detallar» (D10) es del sistema: lo crea este
 * servicio la primera vez que hace falta, y nadie lo edita ni lo desactiva.
 */
final readonly class InsumosService
{
    public function crear(DatosInsumo $datos): Insumo
    {
        $datos = $this->validar($datos, null);

        return $this->guardar(fn (): Insumo => Insumo::query()->create([
            'codigo' => $datos->codigo,
            'nombre' => $datos->nombre,
            'tipo' => $datos->tipo,
            'unidad_medida_id' => $datos->unidadMedidaId,
            'precio_referencia' => $datos->precioReferencia,
            'producto_id' => $datos->productoId,
            'categoria_costo_hora_id' => $datos->categoriaCostoHoraId,
        ])->refresh(), $datos);
    }

    /** El código y el tipo no cambian (el código es inmutable, como en todo catálogo). */
    public function editar(int $insumoId, DatosInsumo $datos, int $versionLeida): Insumo
    {
        return DB::transaction(function () use ($insumoId, $datos, $versionLeida): Insumo {
            $insumo = $this->paraCambiar($insumoId, $versionLeida);

            if (strtoupper(trim($datos->codigo)) !== $insumo->codigo || $datos->tipo !== $insumo->tipo) {
                throw PresupuestoRechazado::conMotivo('El código y el tipo de un insumo no cambian: crea otro insumo.');
            }

            $datos = $this->validar($datos, $insumo->id);

            // Las líneas del APU no guardan la unidad: cambiarla dejaría cantidades viejas con la unidad nueva.
            if ($datos->unidadMedidaId !== $insumo->unidad_medida_id && LineaApu::query()->where('insumo_id', $insumo->id)->exists()) {
                throw PresupuestoRechazado::conMotivo("El insumo {$insumo->codigo} ya está en análisis de precios: su unidad no cambia (crea otro insumo).");
            }

            return $this->guardar(function () use ($insumo, $datos): Insumo {
                $insumo->fill([
                    'nombre' => $datos->nombre,
                    'unidad_medida_id' => $datos->unidadMedidaId,
                    'precio_referencia' => $datos->precioReferencia,
                    'producto_id' => $datos->productoId,
                    'categoria_costo_hora_id' => $datos->categoriaCostoHoraId,
                ]);
                $insumo->version++;
                $insumo->save();

                return $insumo;
            }, $datos);
        });
    }

    /** Desactivado, no se pone en APU nuevos; los que ya lo tienen lo siguen mostrando. */
    public function desactivar(int $insumoId, int $versionLeida): Insumo
    {
        return DB::transaction(function () use ($insumoId, $versionLeida): Insumo {
            $insumo = $this->paraCambiar($insumoId, $versionLeida);
            $insumo->es_activo = false;
            $insumo->version++;
            $insumo->save();

            return $insumo;
        });
    }

    /** «Precio sin detallar» (D10): subcontrato, unidad glb. Lo crea la primera vez; dos a la vez los separa la base. */
    public function sinDetallar(): Insumo
    {
        $existente = Insumo::query()->where('es_sistema', true)->first();

        if ($existente !== null) {
            return $existente;
        }

        $glb = UnidadMedida::query()->where('codigo', 'GLB')->value('id')
            ?? throw PresupuestoRechazado::conMotivo('Falta la unidad «Global» (GLB) en el catálogo de unidades.');

        try {
            return DB::transaction(function () use ($glb): Insumo {
                $insumo = new Insumo([
                    'codigo' => Insumo::CODIGO_SIN_DETALLAR,
                    'nombre' => 'Precio sin detallar',
                    'tipo' => Insumo::SUBCONTRATO,
                    'unidad_medida_id' => $glb,
                ]);
                $insumo->es_sistema = true;
                $insumo->save();

                return $insumo->refresh();
            });
        } catch (UniqueConstraintViolationException) {
            return Insumo::query()->where('es_sistema', true)->firstOrFail();
        }
    }

    /**
     * El precio con que entra el insumo a un APU (D1, D8): para la mano de obra con categoría, la sugerencia de C5 a
     * esa fecha; si no hay, el precio de referencia; si tampoco, null (lo escribe quien presupuesta).
     */
    public function precioInicial(Insumo $insumo, CarbonImmutable $fecha): ?string
    {
        if ($insumo->tipo === Insumo::MANO_OBRA && $insumo->categoria_costo_hora_id !== null) {
            $sugerido = app(PrecioSugeridoManoObra::class)->vigente($insumo->categoria_costo_hora_id, $fecha);

            if ($sugerido !== null) {
                return $sugerido;
            }
        }

        return $insumo->precio_referencia;
    }

    private function validar(DatosInsumo $datos, ?int $insumoId): DatosInsumo
    {
        $codigo = strtoupper(trim($datos->codigo));
        $nombre = trim($datos->nombre);

        if (preg_match('/^[A-Z0-9][A-Z0-9.\-_]{0,29}$/', $codigo) !== 1) {
            throw PresupuestoRechazado::conMotivo('El código del insumo va con letras, números, punto, guion o guion bajo, hasta 30.');
        }

        if ($insumoId === null && $codigo === Insumo::CODIGO_SIN_DETALLAR) {
            throw PresupuestoRechazado::conMotivo('Ese código es del insumo del sistema «Precio sin detallar».');
        }

        if ($nombre === '' || mb_strlen($nombre) > 200) {
            throw PresupuestoRechazado::conMotivo('El nombre del insumo va con texto, de hasta 200 caracteres.');
        }

        if (! in_array($datos->tipo, Insumo::TIPOS, true)) {
            throw PresupuestoRechazado::conMotivo('El tipo de insumo es material, mano de obra, equipo o subcontrato.');
        }

        if (! UnidadMedida::query()->whereKey($datos->unidadMedidaId)->where('es_activa', true)->exists()
            && ! Insumo::query()->whereKey($insumoId)->where('unidad_medida_id', $datos->unidadMedidaId)->exists()) {
            throw PresupuestoRechazado::conMotivo('Esa unidad de medida no existe o está desactivada.');
        }

        $precio = $datos->precioReferencia === null || trim($datos->precioReferencia) === '' ? null : self::precio($datos->precioReferencia);

        if ($datos->productoId !== null) {
            if ($datos->tipo !== Insumo::MATERIAL) {
                throw PresupuestoRechazado::conMotivo('Solo un material se enlaza a un producto del catálogo.');
            }

            try {
                $producto = app(CatalogoService::class)->resumen($datos->productoId);
            } catch (CatalogoRechazado) {
                throw PresupuestoRechazado::conMotivo('Ese producto no existe en el catálogo.');
            }

            if ($producto->unidadMedidaId !== $datos->unidadMedidaId) {
                throw PresupuestoRechazado::conMotivo("El producto {$producto->codigo} se mide en {$producto->unidad}: el insumo va en la misma unidad, para comparar lo presupuestado con lo comprado.");
            }
        }

        if ($datos->categoriaCostoHoraId !== null && $datos->tipo !== Insumo::MANO_OBRA) {
            throw PresupuestoRechazado::conMotivo('Solo la mano de obra lleva categoría de costo por hora.');
        }

        return new DatosInsumo($codigo, $nombre, $datos->tipo, $datos->unidadMedidaId, $precio, $datos->productoId, $datos->categoriaCostoHoraId);
    }

    /**
     * @param  callable(): Insumo  $guardar
     */
    private function guardar(callable $guardar, DatosInsumo $datos): Insumo
    {
        try {
            return DB::transaction($guardar(...));
        } catch (UniqueConstraintViolationException $e) {
            throw PresupuestoRechazado::conMotivo(str_contains($e->getMessage(), 'insumos_producto_id_unique')
                ? 'Ese producto ya tiene su insumo: un producto tiene a lo sumo uno.'
                : "Ya hay un insumo con el código {$datos->codigo}.");
        }
    }

    private function paraCambiar(int $insumoId, int $versionLeida): Insumo
    {
        $insumo = Insumo::query()->lockForUpdate()->find($insumoId) ?? throw PresupuestoRechazado::conMotivo('Ese insumo no existe.');

        if ($insumo->es_sistema) {
            throw PresupuestoRechazado::conMotivo('«Precio sin detallar» es del sistema: no se edita ni se desactiva.');
        }

        if ($insumo->version !== $versionLeida) {
            throw VersionDesactualizada::de("el insumo {$insumo->codigo}");
        }

        return $insumo;
    }

    public static function precio(string $precio): string
    {
        $precio = trim($precio);

        if (preg_match('/^\d{1,10}(\.\d{1,4})?$/', $precio) !== 1) {
            throw PresupuestoRechazado::conMotivo('El precio va en números, sin signo y con hasta cuatro decimales.');
        }

        return bcadd($precio, '0', 4);
    }
}
