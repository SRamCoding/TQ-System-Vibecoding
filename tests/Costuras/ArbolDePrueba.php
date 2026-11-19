<?php

declare(strict_types=1);

namespace Tests\Costuras;

use App\Modules\Erp\Presupuestos\Models\ArbolPartidas;
use App\Modules\Erp\Presupuestos\Models\PartidaControl;
use App\Modules\Erp\Presupuestos\Services\ArbolPartidasService;
use App\Modules\Erp\Proyectos\Services\DatosProyecto;
use App\Modules\Erp\Proyectos\Services\ProyectosService;
use App\Modules\Nucleo\Models\Party;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Services\EmpresaService;
use App\Modules\Nucleo\Services\ParametrosService;
use App\Modules\Nucleo\Services\TransicionesService;
use Carbon\CarbonImmutable;

/**
 * Un proyecto interno con su árbol de partidas real (plan 2.1), para quien necesita imputar de verdad (la base exige
 * que la partida exista). Pide la base sembrada (`NucleoSeeder`). Cada partida: `[código, nombre, monto o null si es
 * padre, código del padre o null]`, padres antes que hijos. Sin `$aprobar`, el árbol queda en borrador.
 *
 * `$arbol = ArbolDePrueba::armar([['PC-01', 'Cámaras', '1000.00', null]]);` → `$arbol->partidas['PC-01']`.
 */
final class ArbolDePrueba
{
    /**
     * @param  array<string, int>  $partidas  código => id (con la PC-99 si se aprobó)
     */
    private function __construct(
        public readonly int $proyectoId,
        public readonly int $arbolId,
        public readonly array $partidas,
        public readonly User $responsable,
    ) {}

    /**
     * @param  list<array{0: string, 1: string, 2: ?string, 3: ?string}>  $partidas
     */
    public static function armar(array $partidas, bool $aprobar = true, ?string $costo = null): self
    {
        $responsable = User::factory()->create();
        self::asegurarFichaDeTechquk();

        $proyecto = app(ProyectosService::class)->crear(new DatosProyecto(
            'Proyecto de prueba '.fake()->unique()->numerify('####'),
            $responsable->id,
            CarbonImmutable::parse('2026-11-10'),
            CarbonImmutable::parse('2027-02-28'),
        ), $responsable);

        $suma = array_reduce($partidas, fn (string $suma, array $p): string => bcadd($suma, $p[2] ?? '0', 2), '0');
        $servicio = app(ArbolPartidasService::class);
        $arbol = $servicio->crear($proyecto->id, $costo ?? $suma, $responsable);
        $ids = [];

        foreach ($partidas as [$codigo, $nombre, $monto, $padre]) {
            $partida = $servicio->agregarPartida($arbol->id, $padre === null ? null : $ids[$padre], $codigo, $nombre, $responsable->id);
            $ids[$codigo] = $partida->id;
        }

        foreach ($partidas as [$codigo, , $monto]) {
            if ($monto !== null) {
                $servicio->fijarPresupuestado($ids[$codigo], $monto, PartidaControl::query()->findOrFail($ids[$codigo])->version);
            }
        }

        if ($aprobar) {
            self::aprobar($arbol->id);
            $ids += PartidaControl::query()->where('proyecto_id', $proyecto->id)->where('es_no_presupuestado', true)->pluck('id', 'codigo')->all();
        }

        return new self($proyecto->id, $arbol->id, $ids, $responsable);
    }

    /** Aprueba el árbol con alguien que tiene el permiso. */
    public static function aprobar(int $arbolId): void
    {
        $arbol = ArbolPartidas::query()->findOrFail($arbolId);
        $jefatura = User::factory()->create()->givePermissionTo('erp.presupuestos.arbol.aprobar');
        app(TransicionesService::class)->transicionar($arbol, 'aprobar', $arbol->version, $jefatura);
    }

    private static function asegurarFichaDeTechquk(): void
    {
        if (app(EmpresaService::class)->datos() !== null) {
            return;
        }

        $techquk = Party::factory()->juridica()->create(['razon_social' => 'Techquk S.A.C.']);
        $parametros = app(ParametrosService::class);
        $parametros->cambiarDesdeTexto(EmpresaService::PARAMETRO, $techquk->numero_documento, $parametros->ficha(EmpresaService::PARAMETRO)['version']);
    }
}
