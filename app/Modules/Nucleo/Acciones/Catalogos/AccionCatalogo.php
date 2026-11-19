<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Acciones\Catalogos;

use App\Modules\Nucleo\Enums\Catalogo;
use App\Modules\Nucleo\RegistroAcciones\Accion;
use App\Modules\Nucleo\RegistroAcciones\Campo;
use App\Modules\Nucleo\Services\EdicionCatalogosService;

/**
 * Base de las acciones de edición de catálogos (plan 1.2b, E5): una clase por operación, una instancia
 * registrada por catálogo. Clave `nucleo.<catalogo>.<operacion>` (p. ej. `nucleo.formas-pago.borrar`).
 * Las reglas están en EdicionCatalogosService.
 */
abstract class AccionCatalogo extends Accion
{
    public function __construct(
        protected readonly Catalogo $catalogo,
        protected readonly EdicionCatalogosService $edicion,
    ) {}

    /** Segmento final de la clave: «crear», «editar»… */
    abstract protected function operacion(): string;

    public function clave(): string
    {
        return 'nucleo.'.str_replace('_', '-', $this->catalogo->value).'.'.$this->operacion();
    }

    public function permiso(): string
    {
        return 'catalogos.editar';
    }

    /**
     * Crear, desactivar y borrar en monedas y tipos de documento piden el permiso de campos de dinero: el
     * sistema usa esos elementos por dentro (moneda local, factura) y el seeder los fija (ADR 0013).
     */
    protected function permisoSiMueveDinero(): string
    {
        return $this->catalogo->tieneSensibles() ? 'catalogos.editar-sensibles' : 'catalogos.editar';
    }

    /** El elemento sobre el que se actúa: la pantalla lo fija desde la fila. */
    protected function campoCodigo(): Campo
    {
        return $this->catalogo->campoCodigo();
    }

    /** La versión leída al abrir (bloqueo optimista). */
    protected function campoVersion(): Campo
    {
        return Campo::entero('version', 'Versión')->conReglas('min:1')->deControl();
    }
}
