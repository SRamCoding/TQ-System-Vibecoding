<?php

declare(strict_types=1);

namespace App\Modules\Erp\Proyectos\Services\EnMemoria;

use App\Modules\Erp\Proyectos\Estados\DefinicionProyecto;
use App\Modules\Erp\Proyectos\Services\DatosProyecto;
use App\Modules\Erp\Proyectos\Services\ProyectoRechazado;
use App\Modules\Erp\Proyectos\Services\ProyectosService;
use App\Modules\Erp\Proyectos\Services\ResumenProyecto;
use App\Modules\Nucleo\Models\User;
use Illuminate\Support\Str;

/**
 * C2 en memoria (plan 2.1, D1; vive en la app desde el plan 2.2, D8, solo para pruebas y la página de modo desarrollo
 * del selector), para quien prueba su módulo sin crear proyectos de verdad:
 * `$this->app->instance(ProyectosService::class, $falsa = new ProyectosEnMemoria)` y luego `$falsa->agregar(...)`.
 * Pasa las mismas pruebas del contrato que `ProyectosEnBase` (`tests/Modules/Erp/Proyectos/ContratoProyectosTest.php`).
 * No revisa lo que vive en otras tablas (que el jefe o el cliente existan): eso lo prueba la real.
 */
final class ProyectosEnMemoria implements ProyectosService
{
    private const array NOMBRES = [
        DefinicionProyecto::EN_PREPARACION => 'En preparación',
        DefinicionProyecto::EN_EJECUCION => 'En ejecución',
        DefinicionProyecto::CERRADO => 'Cerrado',
        DefinicionProyecto::ANULADO => 'Anulado',
    ];

    /** @var array<int, ResumenProyecto> */
    private array $proyectos = [];

    public function __construct(private int $techqukPartyId = 1) {}

    /** Carga un proyecto en el estado que la prueba necesite. */
    public function agregar(
        string $nombre = 'Proyecto de prueba',
        string $estado = DefinicionProyecto::EN_EJECUCION,
        bool $esInterno = true,
        ?int $contratoId = null,
        int $clientePartyId = 1,
        int $jefeProyectoUserId = 1,
    ): ResumenProyecto {
        $id = count($this->proyectos) + 1;

        return $this->proyectos[$id] = new ResumenProyecto(
            id: $id,
            codigo: sprintf('PRY-%s-%04d', date('Y'), $id),
            nombre: $nombre,
            clientePartyId: $clientePartyId,
            clienteNombre: "Cliente {$clientePartyId}",
            contratoId: $contratoId,
            jefeProyectoUserId: $jefeProyectoUserId,
            estado: $estado,
            estadoNombre: self::NOMBRES[$estado],
            esInterno: $esInterno,
            aceptaCostos: in_array($estado, DefinicionProyecto::ACEPTAN_COSTOS, true),
        );
    }

    public function resumen(int $proyectoId): ResumenProyecto
    {
        return $this->proyectos[$proyectoId] ?? throw ProyectoRechazado::conMotivo('Ese proyecto no existe.');
    }

    public function idsAbiertos(): array
    {
        $ids = array_map(fn (ResumenProyecto $p): int => $p->id, array_filter($this->proyectos, fn (ResumenProyecto $p): bool => $p->aceptaCostos));
        sort($ids);

        return $ids;
    }

    public function activos(string $texto = '', int $limite = 20): array
    {
        $texto = self::normalizar($texto);
        $encontrados = array_filter(
            $this->proyectos,
            fn (ResumenProyecto $p): bool => $p->aceptaCostos
                && ($texto === '' || str_contains(self::normalizar($p->codigo), $texto) || str_contains(self::normalizar($p->nombre), $texto)),
        );
        usort($encontrados, fn (ResumenProyecto $a, ResumenProyecto $b): int => strcmp($a->codigo, $b->codigo));

        return array_slice($encontrados, 0, max(1, min($limite, 50)));
    }

    public function crear(DatosProyecto $datos, User $usuario): ResumenProyecto
    {
        $this->validar($datos);

        return $this->agregar(trim($datos->nombre), DefinicionProyecto::EN_PREPARACION, true, null, $this->techqukPartyId, $datos->jefeProyectoUserId);
    }

    public function crearDesdeContrato(int $contratoId, DatosProyecto $datos, ?User $originadoPor = null): ResumenProyecto
    {
        if ($contratoId < 1) {
            throw ProyectoRechazado::conMotivo('Falta el contrato del proyecto.');
        }

        $cliente = $datos->clientePartyId ?? throw ProyectoRechazado::conMotivo('Falta el cliente del proyecto.');
        $this->validar($datos);

        return $this->agregar(trim($datos->nombre), DefinicionProyecto::EN_PREPARACION, false, $contratoId, $cliente, $datos->jefeProyectoUserId);
    }

    public function crearDesdeOportunidad(int $oportunidadId, DatosProyecto $datos, ?User $originadoPor = null): ResumenProyecto
    {
        return $this->crearDesdeContrato(800000 + $oportunidadId, $datos, $originadoPor);
    }

    public function deOportunidad(int $oportunidadId): ?int
    {
        return null;
    }

    public function oportunidadDe(int $proyectoId): ?int
    {
        return null;
    }

    public function anularDesdeOportunidad(int $oportunidadId, string $motivo): void {}

    public function iniciarEjecucion(int $proyectoId): void
    {
        $actual = $this->resumen($proyectoId);

        if ($actual->estado === DefinicionProyecto::EN_EJECUCION) {
            return;
        }

        if ($actual->estado !== DefinicionProyecto::EN_PREPARACION) {
            throw ProyectoRechazado::conMotivo("El proyecto {$actual->codigo} está ".mb_strtolower($actual->estadoNombre).': no puede pasar a ejecución.');
        }

        $this->proyectos[$proyectoId] = new ResumenProyecto(
            ...[...get_object_vars($actual), 'estado' => DefinicionProyecto::EN_EJECUCION, 'estadoNombre' => self::NOMBRES[DefinicionProyecto::EN_EJECUCION], 'aceptaCostos' => true],
        );
    }

    /** Pone un proyecto en otro estado, como si lo hubiera cerrado o anulado su dueño. */
    public function ponerEnEstado(int $proyectoId, string $estado): void
    {
        $actual = $this->resumen($proyectoId);
        $this->proyectos[$proyectoId] = new ResumenProyecto(
            ...[...get_object_vars($actual), 'estado' => $estado, 'estadoNombre' => self::NOMBRES[$estado], 'aceptaCostos' => in_array($estado, DefinicionProyecto::ACEPTAN_COSTOS, true)],
        );
    }

    private function validar(DatosProyecto $datos): void
    {
        $nombre = trim($datos->nombre);

        if ($nombre === '' || mb_strlen($nombre) > 200) {
            throw ProyectoRechazado::conMotivo('El nombre del proyecto va con texto, de hasta 200 caracteres.');
        }

        if ($datos->fechaFinPrevista->lt($datos->fechaInicio)) {
            throw ProyectoRechazado::conMotivo('La fecha de fin prevista no puede ser anterior a la de inicio.');
        }

        if ($datos->moneda !== 'PEN') {
            throw ProyectoRechazado::conMotivo('Por ahora un proyecto va en soles: la moneda extranjera llega en la etapa 3.');
        }
    }

    private static function normalizar(string $texto): string
    {
        return mb_strtolower(Str::ascii(trim($texto)));
    }
}
