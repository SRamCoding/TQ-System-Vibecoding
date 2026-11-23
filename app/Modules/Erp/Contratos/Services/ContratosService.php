<?php

declare(strict_types=1);

namespace App\Modules\Erp\Contratos\Services;

use App\Modules\Erp\Compras\Services\ComprasService;
use App\Modules\Erp\Contratos\Models\AdendaContrato;
use App\Modules\Erp\Contratos\Models\Contrato;
use App\Modules\Erp\Contratos\Models\GarantiaContrato;
use App\Modules\Erp\Contratos\Models\PenalidadContrato;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Numeracion\FormatoNumeracion;
use App\Modules\Nucleo\Services\NumeracionService;
use App\Modules\Nucleo\Services\UsuarioSistema;
use App\Modules\Nucleo\Tiempo\Calendario;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * MVP rápido (ficha t2-contratos): el contrato del flujo 1. Nace en borrador al ganar (con cliente, monto y condiciones
 * de la cotización aceptada); Administración de Contratos completa lo que falta y lo activa. La penalidad diaria no se
 * escribe, se calcula: 0.10 × monto ÷ (F × plazo), con F = 0.40 hasta 60 días y 0.25 después, y un tope del 10 %.
 */
final readonly class ContratosService
{
    public const string PERMISO_VER = 'erp.contratos.ver';

    public const string PERMISO_GESTIONAR = 'erp.contratos.gestionar';

    public const array TIPOS_GARANTIA = ['fiel_cumplimiento' => 'Fiel cumplimiento', 'adelanto' => 'Adelanto', 'seriedad' => 'Seriedad de oferta', 'monto_diferencial' => 'Monto diferencial'];

    public const array ESTADOS_GARANTIA = ['solicitada' => 'Solicitada', 'vigente' => 'Vigente', 'renovada' => 'Renovada', 'liberada' => 'Liberada', 'devuelta' => 'Devuelta'];

    public function crearAlGanar(int $proyectoId, ?int $oportunidadId, int $clientePartyId, string $objeto, bool $esPublico, string $montoConIgv, CarbonImmutable $inicio, CarbonImmutable $fin, ?string $formaPago, ?int $garantiaMeses): Contrato
    {
        $plazo = max(1, (int) $inicio->diffInDays($fin));

        return Contrato::query()->create([
            'codigo' => app(NumeracionService::class)->siguiente('contrato', new FormatoNumeracion('CT'), Calendario::ahora()),
            'proyecto_id' => $proyectoId, 'oportunidad_id' => $oportunidadId, 'cliente_party_id' => $clientePartyId,
            'objeto' => mb_substr($objeto, 0, 300), 'es_publico' => $esPublico, 'monto' => $montoConIgv,
            'fecha_inicio' => $inicio->toDateString(), 'plazo_dias' => $plazo, 'forma_pago' => $formaPago, 'garantia_meses' => $garantiaMeses,
            'penalidad_factor' => self::factor($plazo), 'estado' => 'borrador',
        ]);
    }

    /** F = 0.40 para plazos de hasta 60 días; 0.25 para los mayores (v4; POR VALIDAR contra la Ley 32069). */
    public static function factor(int $plazoDias): string
    {
        return $plazoDias <= 60 ? '0.40' : '0.25';
    }

    /** El monto vigente con IGV: el original más las adendas. */
    public function montoVigente(Contrato $c): string
    {
        return bcadd((string) $c->monto, (string) ($c->adendas()->sum('monto_adicional') ?: '0'), 2);
    }

    public function plazoVigente(Contrato $c): int
    {
        return $c->plazo_dias + (int) $c->adendas()->sum('dias_adicionales');
    }

    public function fin(Contrato $c): CarbonImmutable
    {
        return $c->fecha_inicio->addDays($this->plazoVigente($c));
    }

    /** 0.10 × monto ÷ (F × plazo), a dos decimales. CT del v4: 0.10 × 24,715.10 ÷ (0.40 × 30) = 205.96. */
    public function penalidadDiaria(Contrato $c): string
    {
        $divisor = bcmul((string) $c->penalidad_factor, (string) $this->plazoVigente($c), 6);

        return bcadd(bcdiv(bcmul('0.10', $this->montoVigente($c), 6), $divisor, 6), '0.005', 2);
    }

    /** El tope: el porcentaje del monto vigente (10 % → 2,471.51 en el v4). */
    public function tope(Contrato $c): string
    {
        return bcadd(bcdiv(bcmul($this->montoVigente($c), (string) $c->penalidad_tope_pct, 6), '100', 6), '0.005', 2);
    }

    public function penalidadAplicada(Contrato $c): string
    {
        return bcadd((string) ($c->penalidades()->sum('monto') ?: '0'), '0', 2);
    }

    /**
     * Observación 6.11: lo valorizado del contrato, sin IGV: la suma del avance de sus valorizaciones aprobadas o
     * facturadas (el mismo «avance» que arma Valorizaciones con el precio contractual de cada partida).
     */
    public function valorizado(Contrato $c): string
    {
        return bcadd((string) (DB::table('valorizaciones')->where('contrato_id', $c->id)->whereIn('estado', ['aprobada', 'facturada'])->sum('avance') ?: '0'), '0', 2);
    }

    /**
     * El avance según lo valorizado: valorizado ÷ monto vigente sin IGV × 100, a un decimal (la misma base que usa
     * Valorizaciones: monto vigente ÷ 1.18). Null si el contrato no tiene valorizaciones aprobadas.
     */
    public function avancePct(Contrato $c): ?string
    {
        $valorizado = $this->valorizado($c);

        if (bccomp($valorizado, '0', 2) === 0) {
            return null;
        }

        $base = bcdiv($this->montoVigente($c), bcadd('1', bcdiv((string) $c->igv_pct, '100', 6), 6), 6);

        return bccomp($base, '0', 2) === 0 ? null : bcadd(bcdiv(bcmul($valorizado, '100', 6), $base, 6), '0.05', 1);
    }

    /** Días de hoy al fin vigente: negativos si ya venció. */
    public function diasRestantes(Contrato $c): int
    {
        return (int) CarbonImmutable::parse(Calendario::hoy())->diffInDays($this->fin($c), false);
    }

    /** El plazo transcurrido: días pasados desde el inicio ÷ plazo vigente × 100, entre 0 y 100, a un decimal. */
    public function tiempoPct(Contrato $c): string
    {
        $plazo = max(1, $this->plazoVigente($c));
        $pasados = min($plazo, max(0, (int) $c->fecha_inicio->diffInDays(CarbonImmutable::parse(Calendario::hoy()), false)));

        return bcadd(bcdiv((string) ($pasados * 100), (string) $plazo, 6), '0.05', 1);
    }

    /**
     * Observación 6.11: lo que costaría el atraso si hoy se aplicara: días vencidos × penalidad diaria, recortado a lo
     * que falta del tope. Cero si el contrato no está vigente o no ha vencido.
     */
    public function penalidadEnRiesgo(Contrato $c): string
    {
        $dias = -$this->diasRestantes($c);

        if ($c->estado !== 'vigente' || $dias < 1) {
            return '0.00';
        }

        $disponible = bcsub($this->tope($c), $this->penalidadAplicada($c), 2);
        $monto = bcmul($this->penalidadDiaria($c), (string) $dias, 2);

        return bccomp($disponible, '0', 2) <= 0 ? '0.00' : (bccomp($monto, $disponible, 2) > 0 ? $disponible : $monto);
    }

    /** La carta fianza que manda: la vigente o renovada que vence primero. */
    public function fianzaVigente(Contrato $c): ?GarantiaContrato
    {
        return $c->garantias()->whereIn('estado', ['vigente', 'renovada'])->orderBy('vencimiento')->first();
    }

    /** Completa lo que la cotización no trae y lo activa. */
    public function activar(int $contratoId, string $numeroEntidad, CarbonImmutable $firma, ?string $formaPago, ?int $garantiaMeses, string $topePct, ?string $factor): Contrato
    {
        return DB::transaction(function () use ($contratoId, $numeroEntidad, $firma, $formaPago, $garantiaMeses, $topePct, $factor): Contrato {
            $c = Contrato::query()->lockForUpdate()->findOrFail($contratoId);

            if ($c->estado !== 'borrador') {
                throw new RuntimeException("El contrato {$c->codigo} ya está ".mb_strtolower(Contrato::ESTADOS[$c->estado]).'.');
            }

            if (trim($numeroEntidad) === '') {
                throw new RuntimeException('Falta el número de contrato de la entidad.');
            }

            $c->update(['numero_entidad' => trim($numeroEntidad), 'fecha_firma' => $firma->toDateString(), 'forma_pago' => $formaPago, 'garantia_meses' => $garantiaMeses,
                'penalidad_tope_pct' => $topePct, 'penalidad_factor' => $factor ?? self::factor($c->plazo_dias), 'estado' => 'vigente']);

            if (! $c->hitos()->exists()) {
                $fin = $this->fin($c);
                $hitos = [['Firma del contrato', $firma, $firma], ['Entrega de equipos', $c->fecha_inicio->addDays((int) round($c->plazo_dias * 0.45)), null],
                    ['Instalación', $c->fecha_inicio->addDays((int) round($c->plazo_dias * 0.85)), null], ['Conformidad', $fin, null]];

                foreach ($hitos as $i => [$nombre, $fecha, $real]) {
                    $c->hitos()->create(['nombre' => $nombre, 'fecha_prevista' => $fecha->toDateString(), 'fecha_real' => $real?->toDateString(), 'orden' => $i + 1]);
                }
            }

            return $c->refresh();
        });
    }

    /**
     * La garantía con sus dos caras (v4 flujo 1, paso 2): el monto garantizado y su vencimiento, y la comisión del banco,
     * que es costo del proyecto y va a sus gastos generales.
     */
    public function registrarGarantia(int $contratoId, string $tipo, string $banco, ?string $numero, string $monto, CarbonImmutable $emision, CarbonImmutable $vencimiento, ?string $comision, string $estado = 'vigente'): GarantiaContrato
    {
        if (! isset(self::TIPOS_GARANTIA[$tipo]) || ! isset(self::ESTADOS_GARANTIA[$estado])) {
            throw new RuntimeException('Tipo o estado de garantía desconocido.');
        }

        if ($vencimiento->lt($emision)) {
            throw new RuntimeException('El vencimiento no puede ser anterior a la emisión.');
        }

        return DB::transaction(function () use ($contratoId, $tipo, $banco, $numero, $monto, $emision, $vencimiento, $comision, $estado): GarantiaContrato {
            $c = Contrato::query()->findOrFail($contratoId);
            $g = $c->garantias()->create(['tipo' => $tipo, 'banco' => $banco, 'numero' => $numero, 'monto' => $monto, 'emision' => $emision->toDateString(),
                'vencimiento' => $vencimiento->toDateString(), 'comision' => $comision, 'estado' => $estado]);

            if ($comision !== null && bccomp($comision, '0', 2) > 0) {
                // La comisión es un servicio y se imputa a los gastos generales del proyecto (v4 flujo 1, paso 2): una OS.
                $gg = DB::table('partidas_control as p')->join('partida_arboles as a', 'a.id', '=', 'p.arbol_alta_id')->join('estados as e', 'e.id', '=', 'a.estado_id')
                    ->where('p.proyecto_id', $c->proyecto_id)->where('p.es_gastos_generales', true)->where('p.es_hoja', true)->where('e.codigo', 'aprobado')->orderBy('p.codigo')->value('p.id');

                if ($gg !== null) {
                    $compras = app(ComprasService::class);
                    $os = $compras->ordenDirecta('garantia_financiera', null, $banco, $c->proyecto_id, 'Cargo en cuenta', [[
                        'descripcion' => 'Comisión de carta fianza '.self::TIPOS_GARANTIA[$tipo]." {$numero}", 'unidad_medida_id' => (int) DB::table('unidades_medida')->where('codigo', 'GLB')->value('id'),
                        'cantidad' => '1', 'precio_unitario' => $comision, 'partida_control_id' => (int) $gg,
                    ]], $emision);
                    $quien = auth()->user() instanceof User ? auth()->user() : User::query()->findOrFail(app(UsuarioSistema::class)->id());
                    $compras->aprobarOrden($os->id, $compras->excesos($os->load('lineas')) === [] ? null : 'Comisión del banco por la carta fianza que exige el contrato.', $quien);
                    $compras->recibir($os->id, [$os->lineas()->value('id') => '1'], $emision, null);
                    $compras->pagar($os->id);
                    $g->update(['gasto_id' => $os->id]);
                }
            }

            return $g;
        });
    }

    public function cambiarEstadoGarantia(int $garantiaId, string $estado, ?CarbonImmutable $nuevoVencimiento = null): void
    {
        $g = GarantiaContrato::query()->findOrFail($garantiaId);
        $g->update(['estado' => $estado, 'vencimiento' => $nuevoVencimiento?->toDateString() ?? $g->vencimiento->toDateString()]);
    }

    public function registrarAdenda(int $contratoId, string $numero, CarbonImmutable $fecha, string $motivo, string $montoAdicional, int $diasAdicionales): AdendaContrato
    {
        $c = Contrato::query()->findOrFail($contratoId);

        if ($c->estado !== 'vigente') {
            throw new RuntimeException('Solo un contrato vigente recibe adendas.');
        }

        return $c->adendas()->create(['numero' => $numero, 'fecha' => $fecha->toDateString(), 'motivo' => $motivo, 'monto_adicional' => $montoAdicional, 'dias_adicionales' => $diasAdicionales]);
    }

    /** Días de atraso × penalidad diaria, sin pasar el tope (lo que ya se aplicó cuenta). */
    public function aplicarPenalidad(int $contratoId, int $diasAtraso, CarbonImmutable $fecha, string $motivo): PenalidadContrato
    {
        $c = Contrato::query()->findOrFail($contratoId);

        if ($diasAtraso < 1) {
            throw new RuntimeException('Los días de atraso van desde 1.');
        }

        $disponible = bcsub($this->tope($c), $this->penalidadAplicada($c), 2);

        if (bccomp($disponible, '0', 2) <= 0) {
            throw new RuntimeException('Ya se aplicó el tope de penalidad del contrato.');
        }

        $monto = bcmul($this->penalidadDiaria($c), (string) $diasAtraso, 2);
        $monto = bccomp($monto, $disponible, 2) > 0 ? $disponible : $monto;

        return $c->penalidades()->create(['fecha' => $fecha->toDateString(), 'dias_atraso' => $diasAtraso, 'monto' => $monto, 'motivo' => $motivo]);
    }

    /** Al anular la ganada: el contrato en borrador se anula; uno ya vigente lo impide. */
    public function anularDeOportunidad(int $oportunidadId): void
    {
        $c = Contrato::query()->where('oportunidad_id', $oportunidadId)->where('estado', '!=', 'anulado')->first();

        if ($c === null) {
            return;
        }

        if ($c->estado !== 'borrador') {
            throw new RuntimeException("Su contrato {$c->codigo} ya está ".mb_strtolower(Contrato::ESTADOS[$c->estado]).': no se puede anular la ganada.');
        }

        $c->update(['estado' => 'anulado']);
    }

    public function culminar(int $contratoId): void
    {
        Contrato::query()->whereKey($contratoId)->where('estado', 'vigente')->update(['estado' => 'culminado']);
    }

    /**
     * Lo vendido del proyecto, sin IGV y descontadas las penalidades: (monto + adendas) ÷ (1 + IGV) − penalidades.
     * Null si el proyecto no tiene contrato.
     */
    public function ventaNeta(int $proyectoId): ?string
    {
        $c = Contrato::query()->where('proyecto_id', $proyectoId)->where('estado', '!=', 'anulado')->first();

        if ($c === null) {
            return null;
        }

        $sinIgv = bcadd(bcdiv($this->montoVigente($c), bcadd('1', bcdiv((string) $c->igv_pct, '100', 6), 6), 6), '0.005', 2);
        $neto = bcsub($sinIgv, $this->penalidadAplicada($c), 2);

        // MV-014: en consorcio por participación, lo vendido de Techquk es su porcentaje.
        return $c->modo_consorcio === 'participacion' ? bcadd(bcdiv(bcmul($neto, (string) $c->participacion_pct, 6), '100', 6), '0.005', 2) : $neto;
    }

    /**
     * MVP rápido (MV-014): la liquidación del contrato: monto final (vendido neto más reajustes) contra lo facturado. No se
     * presenta si queda una valorización en borrador.
     *
     * @return array{monto_final: string, reajustes: string, facturado: string, saldo: string, bloqueo: ?string}
     */
    public function liquidacion(Contrato $c): array
    {
        $reajustes = (string) (DB::table('valorizaciones')->where('contrato_id', $c->id)->whereIn('estado', ['aprobada', 'facturada'])->sum('reajuste') ?: '0');
        $final = bcadd((string) $this->ventaNeta($c->proyecto_id), $reajustes, 2);
        $facturado = (string) (DB::table('comprobantes')->where('contrato_id', $c->id)->where('clase', 'venta')->where('estado', '!=', 'anulado')->sum('subtotal') ?: '0');

        return ['monto_final' => $final, 'reajustes' => bcadd($reajustes, '0', 2), 'facturado' => bcadd($facturado, '0', 2), 'saldo' => bcsub($final, $facturado, 2),
            'bloqueo' => DB::table('valorizaciones')->where('contrato_id', $c->id)->where('estado', 'borrador')->exists() ? 'Hay una valorización en borrador: apruébala antes de liquidar.' : null];
    }

    public function liquidar(int $contratoId, CarbonImmutable $fecha): array
    {
        $c = Contrato::query()->findOrFail($contratoId);

        if (! in_array($c->estado, ['vigente', 'culminado'], true)) {
            throw new RuntimeException('Solo se liquida un contrato vigente o culminado.');
        }

        $l = $this->liquidacion($c);

        if ($l['bloqueo'] !== null) {
            throw new RuntimeException($l['bloqueo']);
        }

        $c->update(['estado' => 'liquidado', 'saldo_liquidacion' => $l['saldo'], 'liquidado_el' => $fecha->toDateString()]);

        return $l;
    }
}
