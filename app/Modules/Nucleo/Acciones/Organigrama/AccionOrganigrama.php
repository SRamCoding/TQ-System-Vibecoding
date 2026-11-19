<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Acciones\Organigrama;

use App\Modules\Nucleo\Enums\TipoUnidadOrganizativa;
use App\Modules\Nucleo\Organigrama\DatosUnidad;
use App\Modules\Nucleo\RegistroAcciones\Accion;
use App\Modules\Nucleo\RegistroAcciones\Campo;
use App\Modules\Nucleo\Services\OrganigramaService;

/**
 * Base de las acciones sobre el organigrama (plan 1.4, D5). Clave `nucleo.organigrama.<operacion>`, permiso
 * `organigrama.gestionar`. Las reglas están en OrganigramaService.
 */
abstract class AccionOrganigrama extends Accion
{
    public function __construct(protected readonly OrganigramaService $organigrama) {}

    abstract protected function operacion(): string;

    public function clave(): string
    {
        return 'nucleo.organigrama.'.$this->operacion();
    }

    public function permiso(): string
    {
        return 'organigrama.gestionar';
    }

    protected static function campoUnidad(string $nombre = 'unidad_id', string $etiqueta = 'Unidad'): Campo
    {
        return Campo::entero($nombre, $etiqueta)->conReglas('exists:unidades_organizativas,id');
    }

    protected static function campoPuesto(): Campo
    {
        return Campo::entero('puesto_id', 'Puesto')->conReglas('exists:puestos,id');
    }

    protected static function campoVersion(): Campo
    {
        return Campo::entero('version', 'Versión')->conReglas('min:1')->deControl();
    }

    /**
     * @return list<Campo>
     */
    protected static function camposDeUnidad(bool $edicion = false): array
    {
        $superior = self::campoUnidad('superior_id', 'Depende de')->opcional();
        $orden = Campo::entero('orden', 'Orden')->conReglas('min:0', 'max:32767');

        // Al editar se reemplaza todo: un campo omitido (sin pantalla) movería la unidad a la raíz o su orden a 0.
        return [
            Campo::texto('codigo', 'Código', maximo: 20),
            Campo::texto('nombre', 'Nombre', maximo: 120),
            Campo::opcion('tipo', 'Tipo', TipoUnidadOrganizativa::class),
            $edicion ? $superior->conReglas('present') : $superior,
            $edicion ? $orden : $orden->opcional(),
        ];
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    protected static function datosUnidad(array $datos): DatosUnidad
    {
        return new DatosUnidad(
            $datos['codigo'],
            $datos['nombre'],
            $datos['tipo'],
            superiorId: $datos['superior_id'] ?? null,
            orden: $datos['orden'] ?? 0,
        );
    }
}
