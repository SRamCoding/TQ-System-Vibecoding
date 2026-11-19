<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\RegistroAcciones;

use App\Modules\Nucleo\Excepciones\AccionDuplicada;
use App\Modules\Nucleo\Excepciones\AccionNoRegistrada;
use Illuminate\Contracts\Container\Container;

/**
 * Catálogo de acciones de usuario de todos los módulos. Cada módulo registra las suyas desde su
 * provider. Es código, no tabla (decisión D4 del plan 1.1).
 *
 * Se registra una clase (el contenedor la arma al pedirla) o una instancia ya configurada: una misma
 * clase parametrizada da varias acciones con claves distintas (plan 1.2b, E5: `nucleo.monedas.crear`).
 */
final class RegistroAcciones
{
    /** @var array<string, class-string<Accion>> */
    private array $clases = [];

    /** @var array<string, Accion> */
    private array $instancias = [];

    public function __construct(private readonly Container $contenedor) {}

    /**
     * @param  class-string<Accion>|Accion  $accion
     */
    public function registrar(string|Accion $accion): void
    {
        $instancia = is_string($accion) ? $this->contenedor->make($accion) : $accion;
        $clave = $instancia->clave();
        $clase = $instancia::class;
        $existente = $this->clases[$clave] ?? null;

        if ($existente !== null && $existente !== $clase) {
            throw AccionDuplicada::clave($clave, $existente, $clase);
        }

        $this->clases[$clave] = $clase;

        if ($accion instanceof Accion) {
            $this->instancias[$clave] = $accion;
        }
    }

    public function obtener(string $clave): Accion
    {
        $clase = $this->clases[$clave] ?? throw AccionNoRegistrada::clave($clave);

        return $this->instancias[$clave] ?? $this->contenedor->make($clase);
    }

    /**
     * Clave → clase. Una clase parametrizada aparece con cada una de sus claves y el contenedor no la
     * puede armar sola: para usar una acción, pedirla siempre con obtener($clave).
     *
     * @return array<string, class-string<Accion>>
     */
    public function todas(): array
    {
        return $this->clases;
    }
}
