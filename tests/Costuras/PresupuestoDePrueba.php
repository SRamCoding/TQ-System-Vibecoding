<?php

declare(strict_types=1);

namespace Tests\Costuras;

use App\Modules\Erp\Presupuestos\Models\Insumo;
use App\Modules\Erp\Presupuestos\Models\PartidaDePresupuesto;
use App\Modules\Erp\Presupuestos\Models\Presupuesto;
use App\Modules\Erp\Presupuestos\Services\ArmadoPresupuestoService;
use App\Modules\Erp\Presupuestos\Services\DatosInsumo;
use App\Modules\Erp\Presupuestos\Services\DatosLineaApu;
use App\Modules\Erp\Presupuestos\Services\DatosPresupuesto;
use App\Modules\Erp\Presupuestos\Services\InsumosService;
use App\Modules\Erp\Proyectos\Services\DatosProyecto;
use App\Modules\Erp\Proyectos\Services\ProyectosService;
use App\Modules\Nucleo\Models\Moneda;
use App\Modules\Nucleo\Models\Party;
use App\Modules\Nucleo\Models\UnidadMedida;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Services\EmpresaService;
use App\Modules\Nucleo\Services\ParametrosService;
use Carbon\CarbonImmutable;

/**
 * Presupuestos para las pruebas (plan 2.9). `flujo1()` arma el PRE de la Plaza de Armas del v4 (flujo 1, paso 3): cinco
 * partidas en tres títulos, costo directo 17,750.00. Los APU de 02.01 son los del v4; los demás son inventados para
 * dar sus P.U. y la lista del v4 (cable 630 m, técnico 80 h, ayudante 74 h). Cada partida lleva su rendimiento y las
 * líneas lo usan, salvo tres con el suyo (D12). Pide `NucleoSeeder` cargado.
 */
final class PresupuestoDePrueba
{
    /** @var array<string, int> */
    public array $partidas = [];

    /** @var array<string, int> */
    public array $titulos = [];

    private function __construct(public Presupuesto $presupuesto, public User $usuario) {}

    public static function nuevo(bool $usaApu = true, string $gg = '10', string $utilidad = '8', ?int $oportunidadId = null): self
    {
        $usuario = User::factory()->create();

        return new self(app(ArmadoPresupuestoService::class)->crear(new DatosPresupuesto(
            'Videovigilancia de plaza (prueba)',
            (int) Moneda::query()->where('codigo', 'PEN')->value('id'),
            $gg,
            $utilidad,
            usaApu: $usaApu,
            oportunidadId: $oportunidadId,
        ), $usuario), $usuario);
    }

    public static function flujo1(): self
    {
        $p = self::nuevo();
        $mat = fn (string $codigo, string $nombre, string $unidad): int => self::insumo($codigo, $nombre, Insumo::MATERIAL, $unidad);
        $tecnico = self::insumo('MO-TEC', 'Técnico', Insumo::MANO_OBRA, 'HUR');
        $ayudante = self::insumo('MO-AYU', 'Ayudante', Insumo::MANO_OBRA, 'HUR');
        // D12: el rendimiento va en la partida; `$hereda` lo usa y `$porHora` / `$porJornada` llevan uno propio.
        $hereda = fn (int $insumo, string $precio, string $cuadrilla = '1'): DatosLineaApu => new DatosLineaApu($insumo, cuadrilla: $cuadrilla, precio: $precio);
        $porHora = fn (int $insumo, string $rend, string $precio): DatosLineaApu => new DatosLineaApu($insumo, rendimiento: $rend, unidadRendimiento: 'hora', precio: $precio);
        $porJornada = fn (int $insumo, string $rend, string $precio): DatosLineaApu => new DatosLineaApu($insumo, rendimiento: $rend, unidadRendimiento: 'jornada', precio: $precio);
        $material = fn (int $insumo, string $neta, string $precio, string $desperdicio = '0'): DatosLineaApu => new DatosLineaApu($insumo, cantidadNeta: $neta, desperdicioPct: $desperdicio, precio: $precio);

        $p->titulo('01', 'CÁMARAS');
        $p->titulo('02', 'CABLEADO Y POSTES');
        $p->titulo('03', 'CENTRO DE MONITOREO');

        $p->partida('01', '01.01', 'Instalación de cámara domo IP', 'NIU', '10', [
            $material($mat('CAM-DOMO', 'Cámara domo IP', 'NIU'), '1', '583.00'),
            $hereda($tecnico, '18.00'),
            $hereda($ayudante, '12.00'),
        ], rendimiento: ['0.5', 'hora']);
        $p->partida('02', '02.01', 'Tendido de cable UTP', 'MTR', '600', [
            $material($mat('CAB-UTP6', 'Cable UTP Cat6', 'MTR'), '1', '2.40', '5'),
            $hereda($tecnico, '18.00'),
            $hereda($ayudante, '12.00'),
        ], rendimiento: ['20', 'hora']);
        $p->partida('02', '02.02', 'Instalación de poste metálico', 'NIU', '4', [
            $material($mat('POS-MET', 'Poste metálico', 'NIU'), '1', '900.00'),
            $hereda($tecnico, '18.00'),
            $hereda($ayudante, '12.00'),
            $porJornada(self::insumo('EQ-GRUA', 'Camión grúa', Insumo::EQUIPO, 'HUR'), '8', '70.00'),
        ], rendimiento: ['2', 'jornada']);
        $p->partida('03', '03.01', 'NVR con almacenamiento', 'NIU', '1', [
            $material($mat('NVR-16', 'NVR de 16 canales', 'NIU'), '1', '4116.00'),
            $hereda($tecnico, '18.00'),
            $porHora($ayudante, '0.25', '12.00'),
        ], rendimiento: ['0.125', 'hora']);
        $p->partida('03', '03.02', 'Pruebas y puesta en marcha', 'GLB', '1', [
            $hereda($tecnico, '18.00', '3'),
            $hereda($ayudante, '12.00', '2'),
            $porJornada(self::insumo('EQ-INST', 'Instrumental de pruebas', Insumo::EQUIPO, 'HUR'), '1', '10.50'),
        ], rendimiento: ['0.5', 'hora']);

        return $p;
    }

    public function titulo(string $codigo, string $nombre, ?string $padre = null): int
    {
        $titulo = app(ArmadoPresupuestoService::class)->agregarTitulo($this->presupuesto->id, $padre === null ? null : $this->titulos[$padre], $codigo, $nombre);

        return $this->titulos[$codigo] = $titulo->id;
    }

    /**
     * @param  list<DatosLineaApu>  $lineas
     * @param  array{0: string, 1: string}|null  $rendimiento  el de la partida, con su unidad (D12)
     */
    public function partida(string $titulo, string $codigo, string $descripcion, string $unidad, string $metrado, array $lineas = [], ?string $precioUnitario = null, ?array $rendimiento = null): int
    {
        $servicio = app(ArmadoPresupuestoService::class);
        $partida = $servicio->agregarPartida($this->presupuesto->id, $this->titulos[$titulo], $codigo, $descripcion, self::unidad($unidad), $metrado, $precioUnitario, $rendimiento[0] ?? null, $rendimiento[1] ?? null);

        foreach ($lineas as $linea) {
            $servicio->agregarLineaApu($partida->id, $linea, PartidaDePresupuesto::query()->findOrFail($partida->id)->version);
        }

        return $this->partidas[$codigo] = $partida->id;
    }

    public function fresco(): Presupuesto
    {
        return $this->presupuesto->refresh();
    }

    public static function insumo(string $codigo, string $nombre, string $tipo, string $unidad, ?string $precio = null): int
    {
        return Insumo::query()->where('codigo', $codigo)->value('id')
            ?? app(InsumosService::class)->crear(new DatosInsumo($codigo, $nombre, $tipo, self::unidad($unidad), $precio))->id;
    }

    public static function unidad(string $codigo): int
    {
        return (int) UnidadMedida::query()->where('codigo', $codigo)->value('id');
    }

    /** Un proyecto en preparación, que acepta costos. */
    public static function proyecto(): int
    {
        $responsable = User::factory()->create();

        if (app(EmpresaService::class)->datos() === null) {
            $techquk = Party::factory()->juridica()->create(['razon_social' => 'Techquk S.A.C.']);
            $parametros = app(ParametrosService::class);
            $parametros->cambiarDesdeTexto(EmpresaService::PARAMETRO, $techquk->numero_documento, $parametros->ficha(EmpresaService::PARAMETRO)['version']);
        }

        return app(ProyectosService::class)->crear(new DatosProyecto(
            'Proyecto de prueba '.fake()->unique()->numerify('####'),
            $responsable->id,
            CarbonImmutable::parse('2026-11-10'),
            CarbonImmutable::parse('2027-02-28'),
        ), $responsable)->id;
    }

    /** Alguien con los permisos de las transiciones del presupuesto. */
    public static function jefatura(): User
    {
        return User::factory()->create()->givePermissionTo([
            'erp.presupuestos.presupuesto.ofertar',
            'erp.presupuestos.presupuesto.traer_al_proyecto',
            'erp.presupuestos.presupuesto.anular',
        ]);
    }
}
