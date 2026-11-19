<?php

declare(strict_types=1);

namespace App\Modules\Erp\Presupuestos\Services;

use App\Modules\Erp\Presupuestos\Estados\DefinicionPresupuesto;
use App\Modules\Erp\Presupuestos\Models\Insumo;
use App\Modules\Erp\Presupuestos\Models\LineaApu;
use App\Modules\Erp\Presupuestos\Models\PartidaDePresupuesto;
use App\Modules\Erp\Presupuestos\Models\Presupuesto;
use App\Modules\Nucleo\Models\AuditoriaCambio;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * Lo que lee la portada de la lista de presupuestos (plan 2.10, D1), con el patrón de Cuentas: pestañas por estado, el
 * aviso de lo que hay que revisar con «Ver cuáles» y los últimos cambios. Todo filtra en la base.
 */
final readonly class PortadaPresupuestos
{
    /** Pestañas, en su orden. «Vigentes»: los aprobados que mandan hoy en su proyecto. */
    public const array PESTANAS = ['todos', 'borradores', 'ofertados', 'vigentes', 'reemplazados', 'anulados'];

    /** Avisos, en su orden; cada uno es también un valor del filtro «Revisar» de la tabla. */
    public const array AVISOS = ['sin-producto'];

    /**
     * @param  Builder<Presupuesto>  $consulta
     * @return Builder<Presupuesto>
     */
    public function enPestana(Builder $consulta, string $pestana): Builder
    {
        return match ($pestana) {
            'borradores' => $this->enEstados($consulta, [DefinicionPresupuesto::BORRADOR]),
            'ofertados' => $this->enEstados($consulta, [DefinicionPresupuesto::OFERTADO]),
            'vigentes' => $this->enEstados($consulta, [DefinicionPresupuesto::APROBADO])->where('presupuestos.es_vigente', true),
            'reemplazados' => $this->enEstados($consulta, [DefinicionPresupuesto::REEMPLAZADO]),
            'anulados' => $this->enEstados($consulta, [DefinicionPresupuesto::ANULADO]),
            default => $consulta,
        };
    }

    /**
     * D13 de la 2.9: los presupuestos en curso (borrador, ofertado o vigente) con materiales sin producto, que no se
     * podrán comparar con lo comprado. Avisa; no bloquea.
     *
     * @param  Builder<Presupuesto>  $consulta
     * @return Builder<Presupuesto>
     */
    public function enAviso(Builder $consulta, string $aviso): Builder
    {
        if ($aviso !== 'sin-producto') {
            return $consulta;
        }

        $conMaterialSinProducto = PartidaDePresupuesto::query()->select('presupuesto_id')
            ->whereIn('id', LineaApu::query()->select('presupuesto_partida_id')
                ->whereIn('insumo_id', Insumo::query()->select('id')->where('tipo', Insumo::MATERIAL)->whereNull('producto_id')));

        return $consulta->whereIn('presupuestos.id', $conMaterialSinProducto)
            ->where(fn (Builder $q): Builder => $this->enEstados($q, [DefinicionPresupuesto::BORRADOR, DefinicionPresupuesto::OFERTADO])
                ->orWhere(fn (Builder $v): Builder => $this->enEstados($v, [DefinicionPresupuesto::APROBADO])->where('presupuestos.es_vigente', true)));
    }

    /**
     * @return array<string, int>
     */
    public function avisos(): array
    {
        $avisos = [];

        foreach (self::AVISOS as $aviso) {
            $cantidad = $this->enAviso(Presupuesto::query(), $aviso)->count();

            if ($cantidad > 0) {
                $avisos[$aviso] = $cantidad;
            }
        }

        return $avisos;
    }

    /**
     * Los últimos cambios de presupuestos (su encabezado y estado), de la bitácora.
     *
     * @return list<array{quien: string, accion: string, ficha: string, presupuesto_id: ?int, cuando: CarbonImmutable}>
     */
    public function ultimosCambios(int $cuantos = 10): array
    {
        $cambios = AuditoriaCambio::query()
            ->where('auditable_type', (new Presupuesto)->getMorphClass())
            ->with('usuario:id,name')
            ->orderByDesc('fecha')->orderByDesc('id')
            ->limit($cuantos)
            ->get();
        $nombres = Presupuesto::query()->whereKey($cambios->pluck('auditable_id')->unique()->values()->all())
            ->get(['id', 'numero', 'numero_version', 'nombre'])
            ->mapWithKeys(fn (Presupuesto $p): array => [$p->id => "{$p->numero} v{$p->numero_version} · {$p->nombre}"]);

        return $cambios->map(fn (AuditoriaCambio $c): array => [
            'quien' => $c->usuario->name ?? 'Alguien',
            'accion' => $c->evento->etiqueta(),
            'ficha' => (string) ($nombres[$c->auditable_id] ?? 'un presupuesto'),
            'presupuesto_id' => isset($nombres[$c->auditable_id]) ? $c->auditable_id : null,
            'cuando' => $c->fecha,
        ])->values()->all();
    }

    /**
     * @param  Builder<Presupuesto>  $consulta
     * @param  list<string>  $codigos
     * @return Builder<Presupuesto>
     */
    private function enEstados(Builder $consulta, array $codigos): Builder
    {
        return $consulta->whereHas('estado', fn (Builder $q): Builder => $q->whereIn('codigo', $codigos));
    }
}
