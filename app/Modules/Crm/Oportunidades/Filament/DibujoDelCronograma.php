<?php

declare(strict_types=1);

namespace App\Modules\Crm\Oportunidades\Filament;

use App\Modules\Crm\Oportunidades\Enums\EtapaAbierta;
use App\Modules\Crm\Oportunidades\Enums\TallaDeGema;
use App\Modules\Crm\Oportunidades\Filament\Resources\Oportunidades\OportunidadResource;
use App\Modules\Crm\Oportunidades\Models\Oportunidad;
use App\Modules\Crm\Oportunidades\Services\PortadaOportunidades;
use App\Modules\Nucleo\Models\Estado;
use App\Modules\Nucleo\Models\HistorialEstado;
use App\Modules\Nucleo\Tiempo\Calendario;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/**
 * La Línea de tiempo de oportunidades (MV-060, el «Cronograma refinado» del lienzo): una fila por oportunidad con su gema,
 * nombre, cliente y monto; a la derecha, un tramo redondeado por cada etapa que recorrió (más oscuro al avanzar), la línea
 * punteada desde hoy hasta el rombo dorado del cierre esperado (coral y «venció» si ya pasó) y la línea «Hoy». Ordena
 * («Por urgencia» por defecto, y por monto, valor esperado, etapa, días estancada o cliente) y filtra (buscador,
 * vencimiento, etapa, responsable y rango del eje).
 */
final readonly class DibujoDelCronograma
{
    public const int FILAS = 500;

    /** El color de cada etapa, de la más clara a la más oscura (como el embudo). */
    public const array COLORES = ['prospecto' => '#A6F2FF', 'calificada' => '#7FE9FF', 'propuesta_enviada' => '#4DDDF5', 'negociacion' => '#1FCDEB'];

    public const array ORDENES = ['urgencia' => 'Por urgencia', 'monto' => 'Por monto', 'valor' => 'Por valor esperado', 'etapa' => 'Por etapa', 'estancada' => 'Por días estancada', 'cliente' => 'Por cliente'];

    public const array VENCIMIENTOS = ['vencidas' => 'Vencidas', 'semana' => 'Esta semana', 'mes' => 'Este mes', 'sin_fecha' => 'Sin fecha'];

    /** Cuántos días abarca el eje y cuántos de ellos van antes de hoy (hoy queda a un tercio, como en el lienzo). */
    public const array RANGOS = ['mes' => ['Mes', 45, 15], 'trimestre' => ['Trimestre', 90, 30], 'semestre' => ['Semestre', 180, 60]];

    /**
     * @param  array{orden: string, buscar: string, vencimiento: ?string, etapa: ?string, responsable: string, rango: string}  $f
     * @return array<string, mixed>
     */
    public function datos(array $f, int $usuarioId): array
    {
        $hoy = CarbonImmutable::parse(Calendario::hoy());
        [, $dias, $atras] = self::RANGOS[$f['rango']] ?? self::RANGOS['semestre'];
        $inicio = $hoy->subDays($atras);
        $x = fn (CarbonImmutable $d): float => round($inicio->diffInDays($d, false) / $dias * 100, 2);

        $oportunidades = $this->oportunidades($f['etapa'] === 'cerradas');
        $historial = $this->historial(array_map(fn (Oportunidad $o): int => $o->id, $oportunidades));
        $cuentas = OportunidadResource::cuentas(array_map(fn (Oportunidad $o): int => $o->cuenta_id, $oportunidades));
        $portada = app(PortadaOportunidades::class);
        $prob = [];

        foreach (EtapaAbierta::cases() as $e) {
            $prob[$e->value] = $portada->probabilidad($e);
        }

        $filas = array_map(fn (Oportunidad $o): array => $this->fila($o, $historial[$o->id] ?? [], $hoy, $x, Str::before($cuentas[$o->cuenta_id] ?? '—', ' · '), $prob), $oportunidades);
        $texto = self::normal($f['buscar']);
        $filas = array_values(array_filter($filas, function (array $r) use ($f, $usuarioId, $texto, $hoy): bool {
            if ($f['responsable'] === 'mias' && $r['ejecutivo'] !== $usuarioId) {
                return false;
            }
            if (ctype_digit($f['responsable']) && $r['ejecutivo'] !== (int) $f['responsable']) {
                return false;
            }
            if ($f['etapa'] !== null && $f['etapa'] !== 'cerradas' && $r['etapa'] !== $f['etapa']) {
                return false;
            }
            if ($texto !== '' && ! str_contains(self::normal($r['nombre'].' '.$r['cliente']), $texto)) {
                return false;
            }

            return match ($f['vencimiento']) {
                'vencidas' => $r['vencida'],
                'semana' => $r['cierre'] !== null && ! $r['vencida'] && $r['cierre']->lte($hoy->endOfWeek()),
                'mes' => $r['cierre'] !== null && ! $r['vencida'] && $r['cierre']->lte($hoy->endOfMonth()),
                'sin_fecha' => $r['cierre'] === null,
                default => true,
            };
        }));

        usort($filas, self::comparador($f['orden']));
        $vencidas = count(array_filter($filas, fn (array $r): bool => $r['vencida']));

        $meses = [];
        for ($mes = $inicio->startOfMonth(); $mes->lessThan($inicio->addDays($dias)); $mes = $mes->addMonth()) {
            if ($mes->gte($inicio)) {
                $meses[] = ['x' => $x($mes), 'texto' => Str::ucfirst($mes->locale('es')->translatedFormat('F'))];
            }
        }

        return ['filas' => $filas, 'meses' => $meses, 'hoy' => $x($hoy), 'textoHoy' => 'Hoy · '.$hoy->locale('es')->translatedFormat('j M'), 'vencidas' => $f['orden'] === 'urgencia' ? $vencidas : 0];
    }

    /** El orden pedido; el empate, por la fecha de cierre más próxima. */
    private static function comparador(string $orden): \Closure
    {
        $cierre = fn (array $r): int => $r['cierre']?->getTimestamp() ?? PHP_INT_MAX;
        $monto = fn (array $r): float => (float) $r['montoNumero'];

        return match ($orden) {
            'monto' => fn (array $a, array $b): int => [$monto($b), $cierre($a)] <=> [$monto($a), $cierre($b)],
            'valor' => fn (array $a, array $b): int => [$monto($b) * $b['probabilidad'], $cierre($a)] <=> [$monto($a) * $a['probabilidad'], $cierre($b)],
            'etapa' => fn (array $a, array $b): int => [$b['orden'], $cierre($a)] <=> [$a['orden'], $cierre($b)],
            'estancada' => fn (array $a, array $b): int => [$b['diasEnEtapa'], $cierre($a)] <=> [$a['diasEnEtapa'], $cierre($b)],
            'cliente' => fn (array $a, array $b): int => [self::normal($a['cliente']), $cierre($a)] <=> [self::normal($b['cliente']), $cierre($b)],
            // Por urgencia: vencidas (la más vencida primero), por vencer (la más próxima primero), a igual día la de mayor
            // monto; al final las sin fecha, de mayor a menor monto.
            default => fn (array $a, array $b): int => [$a['cierre'] === null ? 1 : 0, $cierre($a), $monto($b)] <=> [$b['cierre'] === null ? 1 : 0, $cierre($b), $monto($a)],
        };
    }

    private static function normal(string $texto): string
    {
        return mb_strtolower(trim(Str::ascii($texto)));
    }

    /**
     * @param  list<array{codigo: string, desde: CarbonImmutable}>  $entradas
     * @param  callable(CarbonImmutable): float  $x
     * @param  array<string, int>  $prob
     * @return array<string, mixed>
     */
    private function fila(Oportunidad $o, array $entradas, CarbonImmutable $hoy, callable $x, string $cliente, array $prob): array
    {
        $codigo = $o->estado->codigo;
        $cerrada = in_array($codigo, ['ganada', 'perdida'], true);
        $todas = [['codigo' => EtapaAbierta::Prospecto->value, 'desde' => CarbonImmutable::instance($o->created_at)->startOfDay()], ...$entradas];
        $entradas = [];

        foreach ($todas as $entrada) {
            if ($entradas === [] || $entradas[count($entradas) - 1]['codigo'] !== $entrada['codigo']) {
                $entradas[] = $entrada;
            }
        }

        $tramos = [];

        foreach ($entradas as $i => $entrada) {
            $etapa = EtapaAbierta::tryFrom($entrada['codigo']);

            if ($etapa === null) {
                continue;
            }

            $hasta = $entradas[$i + 1]['desde'] ?? ($cerrada ? ($o->fecha_cierre_real ?? $hoy) : $hoy);
            $desde = max(0, $x($entrada['desde']));
            $fin = min(100, $x($hasta));

            if ($fin > 0 && $desde < 100) {
                $tramos[] = ['etapa' => $etapa->value, 'x' => $desde, 'ancho' => max(0.4, round($fin - $desde, 2)), 'color' => self::COLORES[$etapa->value],
                    'titulo' => "{$etapa->etiqueta()}: {$entrada['desde']->format('d/m/Y')} → {$hasta->format('d/m/Y')}"];
            }
        }

        $cierre = $cerrada ? ($o->fecha_cierre_real === null ? null : CarbonImmutable::parse($o->fecha_cierre_real)) : ($o->fecha_cierre_estimada === null ? null : CarbonImmutable::parse($o->fecha_cierre_estimada));
        $vencida = ! $cerrada && $cierre !== null && $cierre->lt($hoy);
        $etapaAbierta = EtapaAbierta::tryFrom($codigo);
        $ultimaEntrada = $entradas === [] ? $hoy : $entradas[count($entradas) - 1]['desde'];

        return [
            'id' => $o->id,
            'nombre' => $o->nombre,
            'cliente' => $cliente,
            'monto' => OportunidadResource::monto($o),
            'montoNumero' => (string) $o->monto_estimado,
            'etapa' => $codigo,
            'orden' => $etapaAbierta === null ? -1 : array_search($etapaAbierta, EtapaAbierta::cases(), true),
            'probabilidad' => $prob[$codigo] ?? 0,
            'talla' => $cerrada ? ($codigo === 'ganada' ? TallaDeGema::Brillante : TallaDeGema::Fisurada) : TallaDeGema::desdeEtapa($codigo),
            'ejecutivo' => (int) $o->ejecutivo_user_id,
            'diasEnEtapa' => (int) $ultimaEntrada->diffInDays($hoy),
            'cierre' => $cierre,
            'vencida' => $vencida,
            'diasVencida' => $vencida ? (int) $cierre->diffInDays($hoy) : 0,
            'tramos' => $tramos,
            'falta' => ! $cerrada && $cierre !== null && $cierre->gt($hoy) ? ['x' => max(0, $x($hoy)), 'ancho' => round(min(100, $x($cierre)) - max(0, $x($hoy)), 2)] : null,
            'hito' => $cierre === null ? null : ['x' => $x($cierre), 'visible' => $x($cierre) >= 0 && $x($cierre) <= 100, 'texto' => $cerrada ? ($codigo === 'ganada' ? 'ganada' : 'perdida') : ($vencida ? 'venció' : 'cierre'),
                'titulo' => ($cerrada ? ($codigo === 'ganada' ? 'Ganada el ' : 'Perdida el ') : 'Cierre esperado: ').$cierre->format('d/m/Y')],
        ];
    }

    /** @return list<Oportunidad> abiertas o, con «Cerradas», las ganadas y perdidas */
    private function oportunidades(bool $cerradas): array
    {
        $estados = Estado::query()->where('documento_type', 'oportunidad')->pluck('id', 'codigo');
        $codigos = $cerradas ? ['ganada', 'perdida'] : array_map(fn (EtapaAbierta $e): string => $e->value, EtapaAbierta::cases());

        return Oportunidad::query()->with('estado')->whereIn('estado_id', $estados->only($codigos)->values())->limit(self::FILAS)->get()->all();
    }

    /**
     * Las entradas a cada etapa de esas oportunidades, en orden, de una sola consulta.
     *
     * @param  list<int>  $ids
     * @return array<int, list<array{codigo: string, desde: CarbonImmutable}>>
     */
    private function historial(array $ids): array
    {
        $codigos = Estado::query()->where('documento_type', 'oportunidad')->pluck('codigo', 'id');
        $porOportunidad = [];

        foreach (HistorialEstado::query()->where('documentable_type', 'oportunidad')->whereIn('documentable_id', $ids)->orderBy('id')->get(['documentable_id', 'hacia_estado_id', 'fecha']) as $fila) {
            $porOportunidad[$fila->documentable_id][] = ['codigo' => (string) ($codigos[$fila->hacia_estado_id] ?? ''), 'desde' => CarbonImmutable::parse($fila->fecha)->startOfDay()];
        }

        return $porOportunidad;
    }
}
