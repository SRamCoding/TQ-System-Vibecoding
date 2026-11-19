<?php

declare(strict_types=1);

namespace App\Modules\Crm\Catalogo\Services;

/**
 * C4 · El catálogo (ADR 0020; plan 2.60). Un solo catálogo para el CRM y el ERP: presupuesto, compras, almacén y
 * cotizaciones leen sus productos solo por aquí. La real es `CatalogoEnBase`; quien la usa en pruebas puede poner
 * `App\Modules\Crm\Catalogo\Services\EnMemoria\CatalogoEnMemoria` con `$this->app->instance()`. Las dos pasan las mismas
 * pruebas del contrato (`tests/Modules/Crm/Catalogo/ContratoCatalogoTest.php`).
 */
interface CatalogoService
{
    /** El tope de `buscar()`. */
    public const int LIMITE = 20;

    /**
     * Los productos activos por código o descripción, sin tildes ni mayúsculas, para los selectores (D3). El de código
     * exacto sale primero; después, el resto por código. Hasta `LIMITE`.
     *
     * @return list<ResumenProducto>
     */
    public function buscar(string $texto = ''): array;

    /**
     * Un producto, aunque esté descontinuado (`esActivo` lo dice): para mostrar lo que ya está guardado (D3).
     *
     * @throws CatalogoRechazado si no existe
     */
    public function resumen(int $productoId): ResumenProducto;

    /** MVP rápido (t1-catalogo): el precio referencial vigente, o null si no tiene. */
    public function precioVigente(int $productoId): ?PrecioVigente;
}
