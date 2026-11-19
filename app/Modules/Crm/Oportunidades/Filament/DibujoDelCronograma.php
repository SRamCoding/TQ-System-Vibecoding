<?php

declare(strict_types=1);

namespace App\Modules\Crm\Oportunidades\Filament;

use App\Modules\Crm\Oportunidades\Enums\EtapaAbierta;
use App\Modules\Crm\Oportunidades\Filament\Resources\Oportunidades\OportunidadResource;
use App\Modules\Crm\Oportunidades\Models\Oportunidad;
use App\Modules\Nucleo\Models\Estado;
use App\Modules\Nucleo\Models\HistorialEstado;
use App\Modules\Nucleo\Tiempo\Calendario;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/**
 * La vista «Línea de tiempo» de Oportunidades como la propone el prototipo aprobado (plan 2.21d, D11; función `tiempo`
 * de `docs/diseno/fuentes/prototipos/oportunidades-portada-ficha.html`): **una fila por oportunidad**, con una barra por
 * cada etapa que pasó, del color de la etapa, desde que entró hasta que salió (del historial de estados); en las
 * abiertas, un tramo rayado desde hoy hasta el cierre esperado y un rombo en esa fecha; en las cerradas, ✓ o ✕ en su
 * cierre. Arriba los meses, la línea de «hoy» y abajo la leyenda. La bandera de la buena pro (SEACE) espera a su fecha.
 * Posiciones en porcentaje del rango: nada se calcula en la vista.
 */
final readonly class DibujoDelCronograma
{
    /** Cuántos meses hacia atrás se ven las cerradas y el comienzo de las barras. */
    public const int MESES_ATRAS = 4;

    /** Lo más lejos que se dibuja un cierre esperado. */
    public const int MESES_ADELANTE = 12;

    /** Cuántas filas como máximo, entre todas las páginas: las abiertas primero. */
    public const int FILAS = 500;

    /** Cuántas filas por página (pedido del desarrollador, 2026-11-16: «que muestre solo 10… de 10 en 10»). */
    public const int POR_PAGINA = 10;

    /**
     * Con `$buscar`, las que coinciden suben arriba mientras se escribe: coincide la que tiene cada palabra escrita en su
     * nombre, número o entidad; primero las que empiezan con la primera palabra, y al final el resto en su orden (pedido del desarrollador, 2026-11-16). Sin acentos ni mayúsculas. El rango de fechas no cambia
     * al buscar: las barras no saltan.
     *
     * @return array{filas: list<array<string, mixed>>, meses: list<array{x: float, texto: string}>, hoy: float, etapas: list<array{nombre: string, clave: string}>, coinciden: int}
     */
    public function datos(string $buscar = ''): array
    {
        $hoy = CarbonImmutable::parse(Calendario::hoy());
        $inicio = $hoy->subMonths(self::MESES_ATRAS)->startOfMonth();
        $oportunidades = $this->oportunidades($inicio);
        $cierres = array_map(fn (Oportunidad $o): CarbonImmutable => $o->fecha_cierre_estimada, $oportunidades);
        $fin = min($hoy->addMonths(self::MESES_ADELANTE), max([$hoy->addMonth(), ...$cierres]))->addDays(10);
        $dias = max(1, (int) $inicio->diffInDays($fin));
        $x = fn (CarbonImmutable $d): float => round(max(0, min(100, $inicio->diffInDays($d, false) / $dias * 100)), 2);
        $historial = $this->historial(array_map(fn (Oportunidad $o): int => $o->id, $oportunidades));
        $cuentas = OportunidadResource::cuentas(array_map(fn (Oportunidad $o): int => $o->cuenta_id, $oportunidades));
        $filas = [];

        foreach ($oportunidades as $o) {
            $filas[] = $this->fila($o, $historial[$o->id] ?? [], $hoy, $x, $cuentas[$o->cuenta_id] ?? '—');
        }

        $coinciden = 0;
        $texto = self::normal($buscar);

        if ($texto !== '') {
            // Por palabras: coincide si cada palabra escrita está en el nombre, el número o la entidad. Primero las que
            // empiezan con todo lo escrito, luego las que empiezan con la primera palabra, luego las demás que coinciden.
            $palabras = preg_split('/\s+/', $texto) ?: [];
            $puntaje = function (array $f) use ($palabras, $texto): int {
                $numero = self::normal((string) strstr($f['detalle'], ' ·', true));
                $nombre = self::normal($f['nombre']);
                $todo = "{$nombre} {$numero} ".self::normal($f['entidad']);

                return match (true) {
                    array_filter($palabras, fn (string $p): bool => ! str_contains($todo, $p)) !== [] => 3,
                    str_starts_with($numero, $texto) || str_starts_with($nombre, $texto) => 0,
                    str_starts_with($numero, $palabras[0]) || str_starts_with($nombre, $palabras[0]) => 1,
                    default => 2,
                };
            };
            $conPuntaje = array_map(fn (array $f, int $i): array => [$puntaje($f), $i, $f], $filas, array_keys($filas));
            usort($conPuntaje, fn (array $a, array $b): int => [$a[0], $a[1]] <=> [$b[0], $b[1]]);
            $coinciden = count(array_filter($conPuntaje, fn (array $p): bool => $p[0] < 3));
            $filas = array_map(fn (array $p): array => [...$p[2], 'coincide' => $p[0] < 3], $conPuntaje);
        }

        $meses = [];

        for ($mes = $inicio; $mes->lessThan($fin); $mes = $mes->addMonth()) {
            $meses[] = ['x' => $x($mes), 'texto' => $mes->locale('es')->translatedFormat('M')];
        }

        return [
            'filas' => $filas,
            'meses' => $meses,
            'hoy' => $x($hoy),
            'etapas' => array_map(fn (EtapaAbierta $e): array => ['nombre' => $e->etiqueta(), 'clave' => $e->value], EtapaAbierta::cases()),
            'coinciden' => $coinciden,
        ];
    }

    private static function normal(string $texto): string
    {
        return mb_strtolower(trim(Str::ascii($texto)));
    }

    /**
     * @param  list<array{codigo: string, desde: CarbonImmutable}>  $entradas  cada entrada a una etapa, en orden
     * @param  callable(CarbonImmutable): float  $x
     * @return array<string, mixed>
     */
    private function fila(Oportunidad $o, array $entradas, CarbonImmutable $hoy, callable $x, string $entidad): array
    {
        $codigo = $o->estado->codigo;
        $cerrada = in_array($codigo, ['ganada', 'perdida'], true);
        // La creación también es una entrada del historial: dos entradas seguidas a la misma etapa son un solo tramo.
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
            $desde = $x($entrada['desde']);
            $ancho = $x($hasta) - $desde;

            if ($x($hasta) > 0) {
                $tramos[] = [
                    'etapa' => $etapa->value,
                    'x' => $desde,
                    'ancho' => max(0.6, round($ancho, 2)),
                    'titulo' => "{$etapa->etiqueta()}: {$entrada['desde']->format('d/m/Y')} → {$hasta->format('d/m/Y')}",
                ];
            }
        }

        $cierre = $cerrada ? ($o->fecha_cierre_real ?? $hoy) : $o->fecha_cierre_estimada;

        return [
            'id' => $o->id,
            'nombre' => $o->nombre,
            'detalle' => "{$o->numero} · ".OportunidadResource::monto($o),
            'entidad' => $entidad,
            'tramos' => $tramos,
            // Lo que falta hasta el cierre esperado (rayado), solo en las abiertas que cierran después de hoy.
            'falta' => ! $cerrada && $cierre->greaterThan($hoy) ? ['x' => $x($hoy), 'ancho' => max(0.5, round($x($cierre) - $x($hoy), 2))] : null,
            'hito' => ['x' => $x($cierre), 'tipo' => $cerrada ? $codigo : 'esperado', 'titulo' => ($cerrada ? ($codigo === 'ganada' ? 'Ganada el ' : 'Perdida el ') : 'Cierre esperado: ').$cierre->format('d/m/Y')],
        ];
    }

    /** @return list<Oportunidad> abiertas (por cierre esperado) y luego las cerradas del rango (las más recientes primero) */
    private function oportunidades(CarbonImmutable $inicio): array
    {
        $estados = Estado::query()->where('documento_type', 'oportunidad')->pluck('id', 'codigo');
        $abiertas = Oportunidad::query()->with('estado')
            ->whereIn('estado_id', $estados->only(array_map(fn (EtapaAbierta $e): string => $e->value, EtapaAbierta::cases()))->values())
            ->orderBy('fecha_cierre_estimada')->orderBy('id')->limit(self::FILAS)->get()->all();
        $cerradas = Oportunidad::query()->with('estado')
            ->whereIn('estado_id', $estados->only(['ganada', 'perdida'])->values())
            ->where('fecha_cierre_real', '>=', $inicio->toDateString())
            ->orderByDesc('fecha_cierre_real')->orderByDesc('id')->limit(max(0, self::FILAS - count($abiertas)))->get()->all();

        return [...$abiertas, ...$cerradas];
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
