<?php

declare(strict_types=1);

namespace App\Modules\Crm\Cuentas\Actividad;

/**
 * Lo que un módulo dice de las cuentas (plan 2.20, D11): Oportunidades, Actividades, Licitaciones, Cotizaciones,
 * Facturación. Se registra en ActividadDeCuentas desde su provider. Lee solo sus propias tablas.
 */
interface AporteActividad
{
    /**
     * Qué datos aporta: cada dato, un solo módulo.
     *
     * @return list<DatoActividad>
     */
    public function datos(): array;

    /**
     * Sus datos de una página de cuentas, en lote (nunca una consulta por fila). Una cuenta sin nada que decir puede
     * faltar.
     *
     * @param  list<int>  $cuentaIds
     * @return array<int, DatosActividadCuenta> por id de cuenta
     */
    public function leer(array $cuentaIds): array;

    /**
     * Lo que impide desactivar la cuenta (D9), ya con su número: «2 oportunidades abiertas». Vacío si nada.
     *
     * @return list<string>
     */
    public function impedimentosParaDesactivar(int $cuentaId): array;
}
