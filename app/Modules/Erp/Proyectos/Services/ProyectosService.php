<?php

declare(strict_types=1);

namespace App\Modules\Erp\Proyectos\Services;

use App\Modules\Nucleo\Models\User;

/**
 * C2 · El proyecto (ADR 0020; plan 2.1, D4). La real es `ProyectosEnBase`; quien la usa en pruebas puede poner
 * `App\Modules\Erp\Proyectos\Services\EnMemoria\ProyectosEnMemoria` con `$this->app->instance()`. Las dos pasan las mismas pruebas del contrato.
 */
interface ProyectosService
{
    /** @throws ProyectoRechazado si no existe */
    public function resumen(int $proyectoId): ResumenProyecto;

    /**
     * Los que aceptan costos, por código o nombre (sin acentos ni mayúsculas), para los selectores.
     *
     * @return list<ResumenProyecto>
     */
    public function activos(string $texto = '', int $limite = 20): array;

    /** Un proyecto interno, «en preparación», con Techquk como cliente. */
    /**
     * Los ids de todos los proyectos abiertos (que aceptan costos), sin límite: para que otro módulo acote un cálculo
     * por proyecto a los que siguen vivos (plan 2.61, revisión de arquitectura).
     *
     * @return list<int>
     */
    public function idsAbiertos(): array;

    public function crear(DatosProyecto $datos, User $usuario): ResumenProyecto;

    /**
     * El proyecto «en preparación» que nace con un contrato (lo usa Contratos). Lo crea el sistema; `$originadoPor`
     * es la persona cuya acción lo generó, si la hubo.
     */
    public function crearDesdeContrato(int $contratoId, DatosProyecto $datos, ?User $originadoPor = null): ResumenProyecto;

    /**
     * MVP rápido (t1-puente-erp, MV-006): el proyecto «en preparación» que nace al ganar una oportunidad, en la misma
     * transacción. Lo crea el sistema; `$originadoPor` es quien la ganó. Una oportunidad, un proyecto.
     */
    public function crearDesdeOportunidad(int $oportunidadId, DatosProyecto $datos, ?User $originadoPor = null): ResumenProyecto;

    /** El proyecto que nació de esa oportunidad, si lo hay y no está anulado. */
    public function deOportunidad(int $oportunidadId): ?int;

    /** La oportunidad de la que nació el proyecto, si nació de una. */
    public function oportunidadDe(int $proyectoId): ?int;

    /**
     * Anula el proyecto de esa oportunidad (al anular la ganada). Solo si sigue en preparación y sin costos: si no,
     * lo rechaza y la ganada no se anula.
     */
    public function anularDesdeOportunidad(int $oportunidadId, string $motivo): void;

    /**
     * Pasa el proyecto a «en ejecución» (lo llama Presupuestos al aprobar el árbol; no es acción de usuario). Si ya
     * está en ejecución, no hace nada.
     *
     * @throws ProyectoRechazado si está cerrado o anulado
     */
    public function iniciarEjecucion(int $proyectoId): void;
}
