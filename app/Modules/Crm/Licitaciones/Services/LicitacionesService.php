<?php

declare(strict_types=1);

namespace App\Modules\Crm\Licitaciones\Services;

use App\Modules\Crm\Cuentas\Services\CuentasService;
use App\Modules\Crm\Licitaciones\Enums\EstadoLicitacion;
use App\Modules\Crm\Licitaciones\Enums\TipoProcedimiento;
use App\Modules\Crm\Licitaciones\Models\DocumentoLicitacion;
use App\Modules\Crm\Licitaciones\Models\HitoLicitacion;
use App\Modules\Crm\Licitaciones\Models\Licitacion;
use App\Modules\Crm\Oportunidades\Enums\GanadorConocido;
use App\Modules\Crm\Oportunidades\Models\Oportunidad;
use App\Modules\Crm\Oportunidades\Services\DatosOportunidad;
use App\Modules\Crm\Oportunidades\Services\OportunidadesService;
use App\Modules\Nucleo\Estados\MotivoElegido;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Numeracion\FormatoNumeracion;
use App\Modules\Nucleo\Services\NumeracionService;
use App\Modules\Nucleo\Tiempo\Calendario;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * MVP rápido (ficha t1-licitaciones): el recorrido de una licitación (CRM-04): se registra la convocatoria, se decide
 * participar (nace su oportunidad), se arma el expediente como lista de verificación, se presenta y se registra el
 * resultado gane quien gane, que alimenta el histórico de la entidad. Si se pierde o queda desierta, su oportunidad se
 * cierra como perdida con el ganador y su precio.
 */
final readonly class LicitacionesService
{
    public const string PERMISO_VER = 'crm.licitaciones.ver';

    public const string PERMISO_GESTIONAR = 'crm.licitaciones.gestionar';

    /** Las etapas del cronograma de un procedimiento público, en orden (v4 parte 2, paso 4). */
    public const array ETAPAS = ['Convocatoria', 'Consultas y observaciones', 'Absolución', 'Integración de bases', 'Presentación de ofertas', 'Buena pro'];

    /**
     * Lo que la IA propone leyendo las bases (en el MVP, una lista fija según el tipo; la lectura real del PDF llega
     * con la IA configurada). Queda «por confirmar» hasta que una persona la acepta.
     */
    public const array EXPEDIENTE_SUGERIDO = [
        'Declaración jurada de datos del postor',
        'Promesa de consorcio o carta de presentación',
        'Ficha técnica de los equipos ofertados',
        'Carta fianza de seriedad de oferta',
        'Certificados y experiencia del personal clave',
        'Constancia de experiencia del postor (contratos y conformidades)',
        'Oferta económica',
    ];

    /**
     * @param  list<array{0: string, 1: string}>  $cronograma  [etapa, fecha Y-m-d]
     */
    public function registrar(int $cuentaId, string $nomenclatura, TipoProcedimiento $tipo, string $objeto, ?int $lineaSolucionId, ?string $valorReferencial, CarbonImmutable $fechaConvocatoria, ?string $urlBases, array $cronograma, User $quien): Licitacion
    {
        if (trim($objeto) === '' || trim($nomenclatura) === '') {
            throw new RuntimeException('Escribe la nomenclatura y el objeto de la convocatoria.');
        }

        return DB::transaction(function () use ($cuentaId, $nomenclatura, $tipo, $objeto, $lineaSolucionId, $valorReferencial, $fechaConvocatoria, $urlBases, $cronograma, $quien): Licitacion {
            $cuenta = app(CuentasService::class)->exigirUsable($cuentaId);
            $licitacion = Licitacion::query()->create([
                'codigo' => app(NumeracionService::class)->siguiente('licitacion', new FormatoNumeracion('LIC'), Calendario::ahora()),
                'nomenclatura' => trim($nomenclatura),
                'cuenta_id' => $cuentaId,
                'es_publica' => $tipo !== TipoProcedimiento::ConvocatoriaPrivada && $cuenta->esPublica,
                'tipo_procedimiento' => $tipo,
                'objeto' => trim($objeto),
                'linea_solucion_id' => $lineaSolucionId,
                'valor_referencial' => $valorReferencial,
                'fecha_convocatoria' => $fechaConvocatoria->toDateString(),
                'url_bases' => $urlBases,
                'estado' => EstadoLicitacion::Registrada,
                'responsable_user_id' => $quien->id,
            ]);

            foreach (array_values($cronograma) as $i => [$etapa, $fecha]) {
                HitoLicitacion::query()->create(['licitacion_id' => $licitacion->id, 'etapa' => $etapa, 'fecha' => $fecha, 'orden' => $i + 1]);
            }

            return $licitacion;
        });
    }

    /** Decide participar: nace su oportunidad (calificada no; en prospecto, con el valor referencial) y la IA propone el expediente. */
    public function participar(int $licitacionId, int $responsableUserId, User $quien): Licitacion
    {
        return DB::transaction(function () use ($licitacionId, $responsableUserId, $quien): Licitacion {
            $l = $this->bloquear($licitacionId, [EstadoLicitacion::Registrada]);
            $buenaPro = $l->hitos()->where('etapa', 'Buena pro')->value('fecha');
            $lineaId = $l->linea_solucion_id ?? (int) DB::table('lineas_solucion')->orderBy('id')->value('id');
            $origen = (int) DB::table('origenes_comerciales')->where('codigo', $l->es_publica ? 'CONVOCATORIA_PUBLICA' : 'CONVOCATORIA_PRIVADA')->value('id');

            $oportunidad = app(OportunidadesService::class)->crear($l->cuenta_id, new DatosOportunidad(
                mb_substr($l->objeto, 0, 200),
                "Licitación {$l->codigo} · {$l->nomenclatura}",
                (string) ($l->valor_referencial ?? '1.00'),
                (int) DB::table('monedas')->where('codigo', 'PEN')->value('id'),
                $buenaPro !== null ? CarbonImmutable::parse((string) $buenaPro) : Calendario::ahora()->addDays(45),
                $lineaId,
                $origen,
                $responsableUserId,
            ), [], $quien);

            $l->update(['estado' => EstadoLicitacion::EnPreparacion, 'responsable_user_id' => $responsableUserId, 'oportunidad_id' => $oportunidad->id]);
            $this->sugerirExpediente($l);

            return $l->refresh();
        });
    }

    public function noParticipar(int $licitacionId, string $motivo): Licitacion
    {
        if (trim($motivo) === '') {
            throw new RuntimeException('Di por qué no participamos: muestra dónde está el techo comercial.');
        }

        return DB::transaction(function () use ($licitacionId, $motivo): Licitacion {
            $l = $this->bloquear($licitacionId, [EstadoLicitacion::Registrada]);
            $l->update(['estado' => EstadoLicitacion::NoParticipa, 'motivo_no_participa' => trim($motivo)]);

            return $l->refresh();
        });
    }

    /** La propuesta «sugerida por IA» del expediente, por confirmar. No duplica lo que ya está. */
    public function sugerirExpediente(Licitacion $l): void
    {
        $orden = (int) $l->documentos()->max('orden');

        foreach (self::EXPEDIENTE_SUGERIDO as $nombre) {
            if (! $l->documentos()->where('nombre', $nombre)->exists()) {
                DocumentoLicitacion::query()->create(['licitacion_id' => $l->id, 'nombre' => $nombre, 'origen' => 'ia', 'es_confirmado' => false, 'orden' => ++$orden]);
            }
        }
    }

    /** Acepta la lista sugerida: deja de ser «por confirmar». Lo que se quitó antes ya no está. */
    public function confirmarSugerencias(int $licitacionId): void
    {
        DocumentoLicitacion::query()->where('licitacion_id', $licitacionId)->where('es_confirmado', false)->update(['es_confirmado' => true]);
    }

    /** Presenta la propuesta: el expediente completo y confirmado (no se presenta con faltantes, v4). */
    public function presentar(int $licitacionId, string $montoOfertado, CarbonImmutable $fecha): Licitacion
    {
        return DB::transaction(function () use ($licitacionId, $montoOfertado, $fecha): Licitacion {
            $l = $this->bloquear($licitacionId, [EstadoLicitacion::EnPreparacion]);
            $faltan = $l->documentos()->where(fn ($q) => $q->where('es_listo', false)->orWhere('es_confirmado', false))->pluck('nombre')->all();

            if ($faltan !== []) {
                throw new RuntimeException('Faltan '.count($faltan).' documentos del expediente: '.implode(', ', $faltan).'.');
            }

            if (bccomp($montoOfertado, '0', 2) <= 0) {
                throw new RuntimeException('El monto ofertado va mayor que cero.');
            }

            $l->update(['estado' => EstadoLicitacion::Presentada, 'monto_ofertado' => $montoOfertado, 'fecha_presentacion' => $fecha->toDateString()]);
            $l->hitos()->where('etapa', 'Presentación de ofertas')->update(['es_cumplido' => true]);
            $l->hitos()->whereIn('etapa', ['Convocatoria', 'Consultas y observaciones', 'Absolución', 'Integración de bases'])->update(['es_cumplido' => true]);

            return $l->refresh();
        });
    }

    /**
     * El resultado, gane quien gane (v4 paso 5): los participantes con su oferta, puntaje y puesto. Perdida o desierta,
     * su oportunidad abierta se cierra como perdida (por precio con el ganador, o proceso desierto). Con buena pro, la
     * oportunidad se gana desde su ficha, con la cotización aceptada (de ella nace el proyecto).
     *
     * @param  list<array{nombre: string, party_id: ?int, es_techquk: bool, oferta: ?string, puntaje: ?string, puesto: ?int}>  $participantes
     */
    public function registrarResultado(int $licitacionId, EstadoLicitacion $resultado, array $participantes, CarbonImmutable $fecha, User $quien): Licitacion
    {
        if (! in_array($resultado, [EstadoLicitacion::BuenaPro, EstadoLicitacion::Perdida, EstadoLicitacion::Desierta], true)) {
            throw new RuntimeException('El resultado es buena pro, perdida o desierta.');
        }

        return DB::transaction(function () use ($licitacionId, $resultado, $participantes, $fecha, $quien): Licitacion {
            $l = $this->bloquear($licitacionId, [EstadoLicitacion::Presentada]);
            $l->participantes()->delete();

            foreach ($participantes as $p) {
                $l->participantes()->create([...$p, 'es_ganador' => ($p['puesto'] ?? null) === 1]);
            }

            $l->update(['estado' => $resultado, 'fecha_resultado' => $fecha->toDateString()]);
            $l->hitos()->update(['es_cumplido' => true]);
            $this->cerrarOportunidadPerdida($l, $resultado, $quien);
            $this->cerrarOportunidadGanada($l, $resultado, $fecha, $quien);

            return $l->refresh();
        });
    }

    /**
     * Observación 7.1: las licitaciones por decidir («Registrada», aún sin oportunidad) son tarjetas «Por calificar» en
     * la lista de Oportunidades, con su próximo hito.
     *
     * @return list<array{id: int, codigo: string, nomenclatura: string, objeto: string, cuenta_id: int, es_publica: bool, linea_solucion_id: ?int, valor_referencial: ?string, responsable: ?int, desde: CarbonImmutable, hito: ?array{etapa: string, fecha: CarbonImmutable, dias: int}}>
     */
    public function porDecidir(): array
    {
        $filas = Licitacion::query()->where('estado', EstadoLicitacion::Registrada->value)->whereNull('oportunidad_id')->orderBy('id')->get();
        $hitos = $this->proximosHitosDe($filas->pluck('id')->all());

        return $filas->map(fn (Licitacion $l): array => [
            'id' => $l->id, 'codigo' => $l->codigo, 'nomenclatura' => $l->nomenclatura, 'objeto' => $l->objeto, 'cuenta_id' => (int) $l->cuenta_id,
            'es_publica' => (bool) $l->es_publica, 'linea_solucion_id' => $l->linea_solucion_id === null ? null : (int) $l->linea_solucion_id,
            'valor_referencial' => $l->valor_referencial === null ? null : (string) $l->valor_referencial, 'responsable' => $l->responsable_user_id === null ? null : (int) $l->responsable_user_id,
            'desde' => CarbonImmutable::instance($l->created_at), 'hito' => $hitos[$l->id] ?? null,
        ])->values()->all();
    }

    /**
     * Observación 7.1: de cada oportunidad que nació de una licitación, si es pública o privada y su próximo hito sin
     * cumplir (el vencido más viejo, o el que viene).
     *
     * @return array<int, array{licitacion_id: int, es_publica: bool, hito: ?array{etapa: string, fecha: CarbonImmutable, dias: int}}> por id de oportunidad
     */
    public function deOportunidades(): array
    {
        $filas = Licitacion::query()->whereNotNull('oportunidad_id')->get(['id', 'oportunidad_id', 'es_publica', 'estado']);
        $abiertas = $filas->filter(fn (Licitacion $l): bool => in_array($l->estado, [EstadoLicitacion::Registrada, EstadoLicitacion::EnPreparacion, EstadoLicitacion::Presentada], true));
        $hitos = $this->proximosHitosDe($abiertas->pluck('id')->all());
        $resultado = [];

        foreach ($filas as $l) {
            $resultado[(int) $l->oportunidad_id] = ['licitacion_id' => $l->id, 'es_publica' => (bool) $l->es_publica, 'hito' => $hitos[$l->id] ?? null];
        }

        return $resultado;
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, array{etapa: string, fecha: CarbonImmutable, dias: int}>
     */
    private function proximosHitosDe(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $hoy = CarbonImmutable::parse(Calendario::hoy());
        $resultado = [];

        // El primero que viene (de hoy en adelante); si ya pasaron todos, el último, vencido.
        foreach (HitoLicitacion::query()->whereIn('licitacion_id', $ids)->where('es_cumplido', false)->orderBy('fecha')->orderBy('orden')->get() as $h) {
            $fecha = CarbonImmutable::parse($h->fecha);
            $actual = $resultado[$h->licitacion_id] ?? null;

            if ($actual === null || $actual['dias'] < 0) {
                $resultado[$h->licitacion_id] = ['etapa' => $h->etapa, 'fecha' => $fecha, 'dias' => (int) $hoy->diffInDays($fecha, false)];
            }
        }

        return $resultado;
    }

    /**
     * El histórico de la entidad (v4 paso 4: «esta entidad adjudica entre el 76 % y el 92 % del referencial»): sus otras
     * licitaciones con resultado, a qué precio adjudicó y cuánto del referencial fue.
     *
     * @return array{filas: list<array{codigo: string, objeto: string, referencial: ?string, adjudicado: ?string, porcentaje: ?string, ganador: ?string, nosotros: string}>, minimo: ?string, maximo: ?string}
     */
    public function historico(int $cuentaId, ?int $excepto = null): array
    {
        $filas = [];

        foreach (Licitacion::query()->with('participantes')->where('cuenta_id', $cuentaId)->when($excepto !== null, fn ($q) => $q->whereKeyNot($excepto))
            ->whereIn('estado', [EstadoLicitacion::BuenaPro->value, EstadoLicitacion::Perdida->value, EstadoLicitacion::Desierta->value])->orderByDesc('fecha_resultado')->get() as $l) {
            $ganador = $l->participantes->firstWhere('es_ganador', true);
            $nuestro = $l->participantes->firstWhere('es_techquk', true);
            $porcentaje = $ganador?->oferta !== null && $l->valor_referencial !== null && bccomp((string) $l->valor_referencial, '0', 2) > 0
                ? bcmul(bcdiv((string) $ganador->oferta, (string) $l->valor_referencial, 6), '100', 1) : null;
            $filas[] = [
                'codigo' => $l->codigo, 'objeto' => $l->objeto, 'referencial' => $l->valor_referencial === null ? null : (string) $l->valor_referencial,
                'adjudicado' => $ganador?->oferta === null ? null : (string) $ganador->oferta, 'porcentaje' => $porcentaje,
                'ganador' => $ganador?->nombre, 'nosotros' => $l->estado === EstadoLicitacion::Desierta ? 'Desierta' : ($nuestro?->puesto === null ? '—' : "{$nuestro->puesto}.º"),
            ];
        }

        $porcentajes = array_values(array_filter(array_column($filas, 'porcentaje'), fn (?string $p): bool => $p !== null));
        usort($porcentajes, fn (string $a, string $b): int => bccomp($a, $b, 1));

        return ['filas' => $filas, 'minimo' => $porcentajes[0] ?? null, 'maximo' => $porcentajes === [] ? null : $porcentajes[count($porcentajes) - 1]];
    }

    /**
     * MV-023: el resultado cierra la oportunidad también cuando hay buena pro: queda ganada por lo ofertado. Si el embudo
     * pide algo más para ganar (una cotización aceptada), la oportunidad queda abierta y se gana a mano.
     */
    private function cerrarOportunidadGanada(Licitacion $l, EstadoLicitacion $resultado, CarbonImmutable $fecha, User $quien): void
    {
        if ($l->oportunidad_id === null || $resultado !== EstadoLicitacion::BuenaPro) {
            return;
        }

        $o = Oportunidad::query()->with('estado')->find($l->oportunidad_id);

        if ($o === null || in_array($o->estado->codigo, ['ganada', 'perdida', 'anulada'], true)) {
            return;
        }

        try {
            app(OportunidadesService::class)->ganar($o->id, $o->version, $fecha, $l->monto_ofertado === null ? null : (string) $l->monto_ofertado, "Buena pro de la licitación {$l->codigo}", $quien);
        } catch (RuntimeException) {
            // Se gana a mano desde la oportunidad.
        }
    }

    private function cerrarOportunidadPerdida(Licitacion $l, EstadoLicitacion $resultado, User $quien): void
    {
        if ($l->oportunidad_id === null || $resultado === EstadoLicitacion::BuenaPro) {
            return;
        }

        $o = Oportunidad::query()->with('estado')->find($l->oportunidad_id);

        if ($o === null || in_array($o->estado->codigo, ['ganada', 'perdida', 'anulada'], true)) {
            return;
        }

        $ganador = $l->participantes()->where('es_ganador', true)->first();
        $motivo = $resultado === EstadoLicitacion::Desierta ? 'proceso_desierto' : 'precio';
        $motivoId = (int) DB::table('motivos as m')->join('tipos_motivo as t', 't.id', '=', 'm.tipo_motivo_id')
            ->where('t.codigo', 'crm.oportunidades.oportunidad.perder')->where('m.codigo', $motivo)->value('m.id');
        $conGanador = $ganador?->party_id !== null && ! $ganador->es_techquk;

        app(OportunidadesService::class)->perder(
            $o->id, $o->version, new MotivoElegido($motivoId),
            $conGanador ? GanadorConocido::Si : GanadorConocido::NoSeSabe,
            $conGanador ? $ganador->party_id : null,
            $conGanador ? (string) $ganador->oferta : null,
            $conGanador ? (int) DB::table('monedas')->where('codigo', 'PEN')->value('id') : null,
            false,
            "Resultado de la licitación {$l->codigo}",
            $quien,
        );
    }

    /** @param  list<EstadoLicitacion>  $permitidos */
    private function bloquear(int $id, array $permitidos): Licitacion
    {
        $l = Licitacion::query()->lockForUpdate()->findOrFail($id);

        if (! in_array($l->estado, $permitidos, true)) {
            throw new RuntimeException("La licitación {$l->codigo} está «{$l->estado->etiqueta()}»: eso ya no se puede hacer.");
        }

        return $l;
    }
}
