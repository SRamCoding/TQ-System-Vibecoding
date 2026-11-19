<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Ia;

use App\Modules\Nucleo\Models\IaProveedor;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;

/**
 * Las implementaciones de proveedor (plan 1.11, D5): `ia_proveedores.implementacion` dice cuál usa cada proveedor del
 * catálogo. Y la clave de cada uno, que vive en una variable `IA_CLAVE_…` (config `nucleo.ia.claves`), nunca en la base.
 */
final class ClientesModeloLenguaje
{
    /** @var array<string, class-string<ClienteModeloLenguaje>> */
    private array $implementaciones = [];

    public function __construct(private readonly Container $contenedor) {}

    /** @param class-string<ClienteModeloLenguaje> $clase */
    public function registrar(string $codigo, string $clase): void
    {
        if (isset($this->implementaciones[$codigo])) {
            throw new InvalidArgumentException("La implementación de IA «{$codigo}» ya está registrada.");
        }

        $this->implementaciones[$codigo] = $clase;
    }

    public function existe(string $codigo): bool
    {
        return isset($this->implementaciones[$codigo]);
    }

    /** @return list<string> */
    public function codigos(): array
    {
        return array_keys($this->implementaciones);
    }

    public function para(IaProveedor $proveedor): ClienteModeloLenguaje
    {
        $clase = $this->implementaciones[$proveedor->implementacion]
            ?? throw new InvalidArgumentException("El proveedor «{$proveedor->codigo}» usa la implementación «{$proveedor->implementacion}», que no está registrada.");

        return $this->contenedor->make($clase);
    }

    /**
     * La clave del proveedor, o nulo si no tiene o TI todavía no la puso: entonces, camino manual sin error (D7). Un
     * proveedor sin variable (un modelo local) no la necesita: cadena vacía.
     */
    public function clave(IaProveedor $proveedor): ?string
    {
        if ($proveedor->variable_clave === null) {
            return '';
        }

        $clave = config('nucleo.ia.claves')[$proveedor->variable_clave] ?? null;

        return is_string($clave) && $clave !== '' ? $clave : null;
    }
}
