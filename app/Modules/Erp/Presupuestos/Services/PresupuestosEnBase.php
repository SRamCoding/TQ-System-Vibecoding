<?php

declare(strict_types=1);

namespace App\Modules\Erp\Presupuestos\Services;

use App\Modules\Erp\Presupuestos\Estados\DefinicionPresupuesto;
use App\Modules\Erp\Presupuestos\Excepciones\PresupuestoRechazado;
use App\Modules\Erp\Presupuestos\Models\Presupuesto;
use App\Modules\Nucleo\Excepciones\TransicionRechazada;
use App\Modules\Nucleo\Models\Moneda;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Services\TransicionesService;
use Illuminate\Support\Facades\DB;

/** C8 con la base (plan 2.9, D7). */
final readonly class PresupuestosEnBase implements PresupuestosService
{
    public function deOportunidad(int $oportunidadId): array
    {
        return Presupuesto::query()
            ->where('oportunidad_id', $oportunidadId)
            ->whereHas('estado', fn ($q) => $q->whereIn('codigo', [DefinicionPresupuesto::BORRADOR, DefinicionPresupuesto::OFERTADO]))
            ->orderBy('numero')
            ->orderByDesc('numero_version')
            ->pluck('id')
            ->map(fn (int $id): ResumenPresupuesto => $this->resumen($id))
            ->all();
    }

    public function ofertar(int $presupuestoId, User $usuario): ResumenPresupuesto
    {
        DB::transaction(function () use ($presupuestoId, $usuario): void {
            $presupuesto = Presupuesto::query()->with('estado')->lockForUpdate()->find($presupuestoId)
                ?? throw PresupuestoNoEncontrado::id($presupuestoId);

            match ($presupuesto->estado->codigo) {
                DefinicionPresupuesto::OFERTADO, DefinicionPresupuesto::APROBADO => null,
                DefinicionPresupuesto::BORRADOR => $this->ofertarBorrador($presupuesto, $usuario),
                default => throw PresupuestoRechazado::conMotivo("El presupuesto {$presupuesto->numero} v{$presupuesto->numero_version} está ".mb_strtolower($presupuesto->estado->nombre).': no se oferta.'),
            };
        });

        return $this->resumen($presupuestoId);
    }

    private function ofertarBorrador(Presupuesto $presupuesto, User $usuario): void
    {
        try {
            app(TransicionesService::class)->transicionarComoSistema($presupuesto, DefinicionPresupuesto::OFERTAR_CON_COTIZACION, $usuario);
        } catch (TransicionRechazada $e) {
            // La costura habla en sus términos: quien emite la cotización no conoce las transiciones del presupuesto.
            throw PresupuestoRechazado::conMotivo($e->getMessage());
        }
    }

    public function resumen(int $presupuestoId): ResumenPresupuesto
    {
        $presupuesto = Presupuesto::query()->with(['estado', 'partidas.unidadMedida'])->find($presupuestoId)
            ?? throw PresupuestoNoEncontrado::id($presupuestoId);
        $titulos = $presupuesto->titulos()->pluck('nombre', 'codigo')->all();
        $tituloDe = $presupuesto->titulos()->pluck('codigo', 'id')->all();

        $partidas = $presupuesto->partidas->sortBy('codigo', SORT_NATURAL)->values()->map(fn ($p): PartidaEnResumen => new PartidaEnResumen(
            $p->codigo,
            (string) $tituloDe[$p->titulo_id],
            (string) $titulos[$tituloDe[$p->titulo_id]],
            $p->descripcion,
            $p->unidadMedida->abreviatura,
            $p->metrado,
            CalculoPresupuesto::redondear($p->costo_unitario, $presupuesto->decimales_precio_unitario),
            $p->parcial,
        ))->all();

        return new ResumenPresupuesto(
            $presupuesto->id,
            $presupuesto->numero,
            $presupuesto->numero_version,
            $presupuesto->nombre,
            $presupuesto->estado->codigo,
            $presupuesto->estado->nombre,
            $presupuesto->es_vigente,
            $presupuesto->proyecto_id,
            $presupuesto->oportunidad_id,
            (string) Moneda::query()->whereKey($presupuesto->moneda_id)->value('codigo'),
            $presupuesto->usa_apu,
            $presupuesto->gastos_generales_pct,
            $presupuesto->utilidad_pct,
            $presupuesto->igv_pct,
            $presupuesto->costo_directo,
            $presupuesto->gastos_generales,
            $presupuesto->utilidad,
            $presupuesto->igv,
            $presupuesto->total,
            app(ConsultaPresupuestos::class)->sinDetallar($presupuesto->id),
            $partidas,
        );
    }
}
