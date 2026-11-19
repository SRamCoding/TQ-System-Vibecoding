<?php

declare(strict_types=1);

namespace App\Modules\Erp\Proyectos\Services;

use App\Modules\Erp\Proyectos\Estados\DefinicionProyecto;
use App\Modules\Erp\Proyectos\Events\ProyectoCreado;
use App\Modules\Erp\Proyectos\Models\Proyecto;
use App\Modules\Nucleo\Enums\Catalogo;
use App\Modules\Nucleo\Models\Concerns\BusquedaSinAcentos;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Numeracion\FormatoNumeracion;
use App\Modules\Nucleo\Services\CatalogosService;
use App\Modules\Nucleo\Services\EmpresaService;
use App\Modules\Nucleo\Services\NumeracionService;
use App\Modules\Nucleo\Services\PartiesService;
use App\Modules\Nucleo\Services\TransicionesService;
use App\Modules\Nucleo\Services\UsuarioSistema;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * C2 sobre la base (plan 2.1, D4). El código (PRY-AAAA-NNNN) lo toma aquí, en la transacción que guarda: la columna
 * es `codigo`, no `numero`, así que la definición no declara numeración. Los estados los cambia solo
 * `TransicionesService`. El cliente se lee por `PartiesService` (codigo.md: fuera del núcleo nadie usa `Party`). Lo que
 * usa un solo método (la empresa, el usuario de sistema) se pide en ese método, no en el constructor (AGENTS.md).
 */
final readonly class ProyectosEnBase implements ProyectosService
{
    public const int LIMITE_MAXIMO = 50;

    public function __construct(
        private TransicionesService $transiciones,
        private NumeracionService $numeracion,
        private CatalogosService $catalogos,
        private PartiesService $parties,
    ) {}

    public function resumen(int $proyectoId): ResumenProyecto
    {
        $proyecto = Proyecto::query()->with('estado')->find($proyectoId)
            ?? throw ProyectoRechazado::conMotivo('Ese proyecto no existe.');

        return $this->resumir($proyecto);
    }

    public function idsAbiertos(): array
    {
        return Proyecto::query()->whereHas('estado', fn ($query) => $query->whereIn('codigo', DefinicionProyecto::ACEPTAN_COSTOS))
            ->orderBy('id')->pluck('id')->map(fn (mixed $id): int => (int) $id)->all();
    }

    public function activos(string $texto = '', int $limite = 20): array
    {
        $consulta = Proyecto::query()
            ->with('estado')
            ->whereHas('estado', fn ($query) => $query->whereIn('codigo', DefinicionProyecto::ACEPTAN_COSTOS));

        BusquedaSinAcentos::aplicar($consulta, $texto, ['codigo', 'nombre']);

        return $consulta
            ->orderBy('codigo')
            ->limit(max(1, min($limite, self::LIMITE_MAXIMO)))
            ->get()
            ->map(fn (Proyecto $proyecto): ResumenProyecto => $this->resumir($proyecto))
            ->values()
            ->all();
    }

    public function crear(DatosProyecto $datos, User $usuario): ResumenProyecto
    {
        $techquk = app(EmpresaService::class)->datos()->partyId
            ?? throw ProyectoRechazado::conMotivo('Primero hay que elegir la ficha de Techquk (Configuración): es el cliente de un proyecto interno.');

        return $this->guardar($datos, $techquk, true, null, $usuario, null);
    }

    public function crearDesdeContrato(int $contratoId, DatosProyecto $datos, ?User $originadoPor = null): ResumenProyecto
    {
        if ($contratoId < 1) {
            throw ProyectoRechazado::conMotivo('Falta el contrato del proyecto.');
        }

        $cliente = $datos->clientePartyId ?? throw ProyectoRechazado::conMotivo('Falta el cliente del proyecto.');

        return $this->guardar($datos, $cliente, false, $contratoId, User::query()->findOrFail(app(UsuarioSistema::class)->id()), $originadoPor);
    }

    public function iniciarEjecucion(int $proyectoId): void
    {
        DB::transaction(function () use ($proyectoId): void {
            $proyecto = Proyecto::query()->with('estado')->lockForUpdate()->find($proyectoId)
                ?? throw ProyectoRechazado::conMotivo('Ese proyecto no existe.');

            match ($proyecto->estado->codigo) {
                DefinicionProyecto::EN_EJECUCION => null,
                DefinicionProyecto::EN_PREPARACION => $this->transiciones->transicionarComoSistema($proyecto, 'iniciar_ejecucion'),
                default => throw ProyectoRechazado::conMotivo("El proyecto {$proyecto->codigo} está {$this->enMinuscula($proyecto->estado->nombre)}: no puede pasar a ejecución."),
            };
        });
    }

    private function guardar(DatosProyecto $datos, int $clientePartyId, bool $esInterno, ?int $contratoId, User $usuario, ?User $originadoPor): ResumenProyecto
    {
        $this->validar($datos, $clientePartyId);
        $monedaId = $this->catalogos->idPorCodigo(Catalogo::Monedas, $datos->moneda);

        $proyecto = DB::transaction(function () use ($datos, $clientePartyId, $esInterno, $contratoId, $usuario, $originadoPor, $monedaId): Proyecto {
            $proyecto = new Proyecto([
                'codigo' => $this->numeracion->siguiente('proyecto', new FormatoNumeracion('PRY'), CarbonImmutable::now('America/Lima')),
                'nombre' => trim($datos->nombre),
                'party_id' => $clientePartyId,
                'es_interno' => $esInterno,
                'contrato_id' => $contratoId,
                'jefe_proyecto_user_id' => $datos->jefeProyectoUserId,
                'fecha_inicio' => $datos->fechaInicio->toDateString(),
                'fecha_fin_prevista' => $datos->fechaFinPrevista->toDateString(),
                'moneda_id' => $monedaId,
            ]);

            $this->transiciones->crear($proyecto, $usuario, $originadoPor);
            ProyectoCreado::dispatch($proyecto->id, $proyecto->codigo, $contratoId);

            return $proyecto;
        });

        return $this->resumen($proyecto->id);
    }

    private function validar(DatosProyecto $datos, int $clientePartyId): void
    {
        $nombre = trim($datos->nombre);

        if ($nombre === '' || mb_strlen($nombre) > 200) {
            throw ProyectoRechazado::conMotivo('El nombre del proyecto va con texto, de hasta 200 caracteres.');
        }

        if ($datos->fechaFinPrevista->lt($datos->fechaInicio)) {
            throw ProyectoRechazado::conMotivo('La fecha de fin prevista no puede ser anterior a la de inicio.');
        }

        // Plan 2.1, D7: en la etapa 2, la moneda del proyecto es soles. La de cada imputación puede ser otra.
        if ($datos->moneda !== 'PEN') {
            throw ProyectoRechazado::conMotivo('Por ahora un proyecto va en soles: la moneda extranjera llega en la etapa 3.');
        }

        $jefe = User::query()->find($datos->jefeProyectoUserId);

        if ($jefe === null || ! $jefe->es_activo || $jefe->es_sistema) {
            throw ProyectoRechazado::conMotivo('El jefe de proyecto debe ser un usuario activo.');
        }

        if ($this->parties->etiquetaDe($clientePartyId) === null) {
            throw ProyectoRechazado::conMotivo('Ese cliente no existe.');
        }
    }

    private function enMinuscula(string $texto): string
    {
        return mb_strtolower($texto);
    }

    private function resumir(Proyecto $proyecto): ResumenProyecto
    {
        return new ResumenProyecto(
            id: $proyecto->id,
            codigo: $proyecto->codigo,
            nombre: $proyecto->nombre,
            clientePartyId: $proyecto->party_id,
            clienteNombre: (string) $this->parties->etiquetaDe($proyecto->party_id),
            contratoId: $proyecto->contrato_id,
            jefeProyectoUserId: $proyecto->jefe_proyecto_user_id,
            estado: $proyecto->estado->codigo,
            estadoNombre: $proyecto->estado->nombre,
            esInterno: $proyecto->es_interno,
            aceptaCostos: in_array($proyecto->estado->codigo, DefinicionProyecto::ACEPTAN_COSTOS, true),
        );
    }
}
