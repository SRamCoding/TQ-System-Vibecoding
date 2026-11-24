<?php

declare(strict_types=1);

namespace App\Modules\Crm\Licitaciones\Services;

use App\Modules\Nucleo\Tiempo\Calendario;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Observación 7.5 (lienzo «B · Bandeja con detalle»): lo que dibuja la bandeja de Convocatorias del SEACE. La lista
 * compacta con sus chips (líneas de negocio y «ver») y sus cantidades, y el detalle de la elegida: cronograma, lo que
 * Techquk ya hizo con esa entidad y los ganadores anteriores guardados del OECE.
 */
final readonly class BandejaSeace
{
    /** Los chips «ver». */
    public const array VER = ['nuevas' => 'Nuevas', 'prioritarias' => 'Prioritarias', 'mias' => 'Mías', 'por_vencer' => 'Por vencer', 'registradas' => 'Registradas', 'descartadas' => 'Descartadas'];

    public const array ORDENES = ['urgencia' => 'Por urgencia', 'valor' => 'Mayor valor', 'recientes' => 'Más recientes', 'entidad' => 'Por entidad'];

    /** A menos de estos días la presentación va en coral y cuenta como «por vencer». */
    public const int DIAS_POR_VENCER = 5;

    /**
     * @param  array{buscar: string, todo: bool, linea: ?int, ver: ?string, orden: string, region?: ?string, entidad?: ?string}  $f
     * @return array{filas: list<array<string, mixed>>, lineas: list<array{id: ?int, nombre: string, n: int}>, ver: array<string, int>, regiones: array<string, int>, entidades: array<string, array{nombre: string, n: int}>}
     */
    public function lista(array $f, int $usuarioId): array
    {
        $hoy = CarbonImmutable::parse(Calendario::hoy());
        // Observación 8.12: lo histórico no entra a la bandeja; con «Buscar en todo el SEACE» se busca con el índice de texto.
        $todas = DB::table('convocatorias_seace')->where('es_historico', false)->get();

        if ($f['todo'] && trim($f['buscar']) !== '') {
            $todas = $todas->merge(DB::table('convocatorias_seace')->where('es_historico', true)
                ->whereRaw("to_tsvector('spanish', tq_sin_tildes(objeto || ' ' || entidad || ' ' || nomenclatura)) @@ plainto_tsquery('spanish', tq_sin_tildes(?))", [trim($f['buscar'])])
                ->orderByDesc('publicada')->limit(200)->get());
        }
        $vistas = DB::table('convocatoria_vistas')->where('user_id', $usuarioId)->pluck('convocatoria_id')->flip();
        $lineas = DB::table('seace_lineas')->orderBy('orden')->pluck('nombre', 'id');
        $cuentas = $this->rucsDeCuentas();
        $texto = FiltroConvocatorias::normal(trim($f['buscar']));

        $filas = $todas->map(fn (object $c): array => $this->fila($c, $hoy, $vistas, $lineas, $cuentas, $usuarioId))
            // La bandeja: lo que entra (o se trajo) y sigue abierto; con «Buscar en todo el SEACE», todo lo guardado.
            ->filter(fn (array $r): bool => $f['todo'] && $texto !== '' ? true : $r['enBandeja'])
            ->filter(fn (array $r): bool => $texto === '' || str_contains(FiltroConvocatorias::normal($r['objeto'].' '.$r['entidad'].' '.$r['nomenclatura']), $texto));

        $porVer = fn (Collection $c, ?string $ver): Collection => $c->filter(fn (array $r): bool => match ($ver) {
            'nuevas' => $r['nueva'] && $r['estado'] === 'nueva' && $r['abierta'],
            'prioritarias' => $r['prioritaria'] && $r['estado'] === 'nueva' && $r['abierta'],
            'mias' => $r['responsableId'] === $usuarioId && $r['estado'] !== 'descartada',
            'por_vencer' => $r['estado'] === 'nueva' && $r['dias'] !== null && $r['dias'] >= 0 && $r['dias'] < self::DIAS_POR_VENCER,
            'registradas' => $r['estado'] === 'registrada',
            'descartadas' => $r['estado'] === 'descartada',
            // Sin chip: las que esperan decisión (nuevas y abiertas; las de fuera de la bandeja si se buscó en todo).
            default => ($r['estado'] === 'nueva' && $r['abierta']) || ! $r['enBandeja'],
        });
        $porLinea = fn (Collection $c, ?int $linea): Collection => $linea === null ? $c : $c->filter(fn (array $r): bool => $r['lineaId'] === $linea);
        // Observación 8.9: los filtros rápidos de región y entidad (lo que se ve ahora, sin cambiar la configuración).
        $region = $f['region'] ?? null;
        $entidad = $f['entidad'] ?? null;
        $porRegion = fn (Collection $c): Collection => $region === null ? $c : $c->filter(fn (array $r): bool => ($r['departamento'] ?? '') === $region);
        $porEntidad = fn (Collection $c): Collection => $entidad === null ? $c : $c->filter(fn (array $r): bool => $r['claveEntidad'] === $entidad);
        $opcionesRegion = $porEntidad($porLinea($porVer($filas, $f['ver']), $f['linea']))->countBy(fn (array $r): string => $r['departamento'] ?? '')->sortKeys()->all();
        $opcionesEntidad = [];

        foreach ($porRegion($porLinea($porVer($filas, $f['ver']), $f['linea'])) as $r) {
            $opcionesEntidad[$r['claveEntidad']] ??= ['nombre' => $r['entidad'], 'n' => 0];
            $opcionesEntidad[$r['claveEntidad']]['n']++;
        }

        uasort($opcionesEntidad, fn (array $a, array $b): int => [$b['n'], $a['nombre']] <=> [$a['n'], $b['nombre']]);
        $filas = $porEntidad($porRegion($filas));

        $conVer = $porVer($filas, $f['ver']);
        $chipsLinea = [['id' => null, 'nombre' => 'Todas', 'n' => $conVer->count()]];

        foreach ($lineas as $id => $nombre) {
            $chipsLinea[] = ['id' => (int) $id, 'nombre' => $nombre, 'n' => $porLinea($conVer, (int) $id)->count()];
        }

        $chipsVer = [];

        foreach (array_keys(self::VER) as $v) {
            $chipsVer[$v] = $porVer($porLinea($filas, $f['linea']), $v)->count();
        }

        $lista = $porLinea($conVer, $f['linea'])->values()->all();
        usort($lista, match ($f['orden']) {
            'valor' => fn (array $a, array $b): int => [(float) ($b['valor'] ?? 0), $a['id']] <=> [(float) ($a['valor'] ?? 0), $b['id']],
            'recientes' => fn (array $a, array $b): int => [$b['publicada'] ?? '', $b['id']] <=> [$a['publicada'] ?? '', $a['id']],
            'entidad' => fn (array $a, array $b): int => [FiltroConvocatorias::normal($a['entidad']), $a['id']] <=> [FiltroConvocatorias::normal($b['entidad']), $b['id']],
            // Por urgencia: la presentación más cercana primero; las vencidas y las sin fecha al final.
            default => fn (array $a, array $b): int => [$a['dias'] === null || $a['dias'] < 0 ? 1 : 0, $a['dias'] ?? PHP_INT_MAX, $a['id']] <=> [$b['dias'] === null || $b['dias'] < 0 ? 1 : 0, $b['dias'] ?? PHP_INT_MAX, $b['id']],
        });

        return ['filas' => $lista, 'lineas' => $chipsLinea, 'ver' => $chipsVer, 'regiones' => $opcionesRegion, 'entidades' => $opcionesEntidad];
    }

    /**
     * El detalle de una: cronograma (lo pasado lleno), lo que Techquk ya hizo con esa entidad y los ganadores anteriores
     * guardados del OECE (Techquk resaltado), con su descuento sobre el valor referencial.
     *
     * @return array<string, mixed>|null
     */
    public function detalle(int $id, int $usuarioId): ?array
    {
        $c = DB::table('convocatorias_seace')->find($id);

        if ($c === null) {
            return null;
        }

        $hoy = CarbonImmutable::parse(Calendario::hoy());
        $fila = $this->fila($c, $hoy, DB::table('convocatoria_vistas')->where('user_id', $usuarioId)->pluck('convocatoria_id')->flip(), DB::table('seace_lineas')->pluck('nombre', 'id'), $this->rucsDeCuentas(), $usuarioId);
        $publicada = $c->publicada === null ? null : CarbonImmutable::parse($c->publicada);
        $presentacion = $c->presentacion === null ? null : CarbonImmutable::parse($c->presentacion);
        // El OECE trae convocatoria, fin de consultas, presentación y buena pro; absolución e integración se estiman.
        $consultas = $c->consultas_hasta !== null ? CarbonImmutable::parse($c->consultas_hasta) : $publicada?->addDays(6);
        // Estimadas entre el fin de consultas y la presentación (nunca después de ella).
        $antesDe = fn (?CarbonImmutable $f): ?CarbonImmutable => $f === null || $presentacion === null ? $f : $f->min($presentacion->subDay());
        $hitos = [
            ['Convocatoria', $publicada],
            ['Consultas', $antesDe($consultas)],
            ['Absolución', $antesDe($consultas?->addDays(4))],
            ['Integración', $antesDe($consultas?->addDays(7))],
            ['Presentación', $presentacion],
            ['Buena pro', $c->buena_pro === null ? $presentacion?->addDays(7) : CarbonImmutable::parse($c->buena_pro)],
        ];
        $cronograma = array_map(fn (array $h): array => ['nombre' => $h[0], 'fecha' => $h[1], 'pasado' => $h[1] !== null && $h[1]->lte($hoy), 'clave' => $h[0] === 'Presentación'], $hitos);
        $pasados = count(array_filter($cronograma, fn (array $h): bool => $h['pasado']));

        // Observación 8.12: los procesos pasados parecidos de la misma entidad (misma línea, palabras clave de esa línea
        // o palabras en común), con su ganador; incluye lo cargado del histórico del OECE.
        $ganadores = $c->ruc_entidad === null ? collect() : DB::table('convocatorias_seace')->where('ruc_entidad', $c->ruc_entidad)->where('id', '!=', $c->id)->whereNotNull('ganador')
            ->orderByDesc('publicada')->limit(60)->get()->filter(fn (object $g): bool => $this->esParecido($c, $g))->take(6)->values()->map(fn (object $g): array => [
                'objeto' => $g->objeto, 'anio' => $g->publicada === null ? null : substr((string) $g->publicada, 0, 4), 'ganador' => $g->ganador,
                'techquk' => str_contains(FiltroConvocatorias::normal((string) $g->ganador), 'techquk'),
                'monto' => $g->monto_adjudicado === null ? null : (string) $g->monto_adjudicado,
                'descuento' => $g->monto_adjudicado !== null && $g->valor_referencial !== null && (float) $g->valor_referencial > 0 ? number_format(100 - (float) $g->monto_adjudicado * 100 / (float) $g->valor_referencial, 1) : null,
            ]);

        return [...$fila,
            'region' => $c->region, 'url' => $c->url,
            // Observación 8.10: la página del proceso en el OECE y los documentos que trae (bases, integradas, actas…).
            'urlOece' => ConvocatoriasSeaceService::URL_PROCESO_OECE.$c->ocid, 'urlSeace' => PostulacionService::URL_SEACE,
            'documentos' => $c->documentos === null ? [] : (array) json_decode($c->documentos, true), 'cronograma' => $cronograma, 'avance' => max(0, min(100, ($pasados - 0.5) * 100 / 5)),
            'conEntidad' => $this->conEntidad($c->ruc_entidad), 'ganadores' => $ganadores->all(), 'motivoDescarte' => $c->motivo_descarte, 'fueraPor' => $c->fuera_por,
            'licitacionId' => $c->licitacion_id === null ? null : (int) $c->licitacion_id, 'motivoPrioridad' => $c->motivo_prioridad,
        ];
    }

    /** Parecido: la misma línea, alguna palabra clave de su línea, o dos palabras largas en común. */
    private function esParecido(object $c, object $g): bool
    {
        if ($c->linea_id !== null && $g->linea_id === $c->linea_id) {
            return true;
        }

        $textoG = FiltroConvocatorias::normal((string) ($g->texto ?: $g->objeto));
        $linea = $c->linea_id === null ? null : collect(app(FiltroConvocatorias::class)->lineas())->firstWhere('id', (int) $c->linea_id);

        if ($linea !== null && collect($linea['palabras'])->contains(fn (string $p): bool => str_contains($textoG, FiltroConvocatorias::normal($p)))) {
            return true;
        }

        $palabras = fn (string $t): array => array_unique(array_filter(preg_split('/[^a-z0-9]+/', FiltroConvocatorias::normal($t)) ?: [], fn (string $p): bool => strlen($p) >= 6));

        return count(array_intersect($palabras((string) $c->objeto), $palabras((string) $g->objeto))) >= 2;
    }

    public function marcarVista(int $id, int $usuarioId): void
    {
        DB::table('convocatoria_vistas')->insertOrIgnore(['convocatoria_id' => $id, 'user_id' => $usuarioId, 'vista_at' => now()]);
    }

    /**
     * «¿Por qué no entró?»: por nomenclatura o código OCDS (o parte), entre todo lo guardado.
     *
     * @return array{id: int, nomenclatura: string, objeto: string, entidad: string, valor: ?string, enBandeja: bool, evaluacion: array<string, mixed>}|null
     */
    public function porQue(string $codigo): ?array
    {
        $codigo = trim($codigo);

        if ($codigo === '') {
            return null;
        }

        $c = DB::table('convocatorias_seace')->where('nomenclatura', 'ilike', $codigo)->orWhere('ocid', $codigo)->first()
            ?? DB::table('convocatorias_seace')->where('nomenclatura', 'ilike', "%{$codigo}%")->orWhere('ocid', 'ilike', "%{$codigo}%")->orderByDesc('id')->first();

        return $c === null ? null : ['id' => (int) $c->id, 'ruc' => $c->ruc_entidad, 'nomenclatura' => $c->nomenclatura, 'objeto' => $c->objeto, 'entidad' => $c->entidad,
            'valor' => $c->valor_referencial === null ? null : (string) $c->valor_referencial, 'enBandeja' => (bool) $c->en_bandeja, 'evaluacion' => app(FiltroConvocatorias::class)->evaluar($c)];
    }

    /**
     * @param  Collection<int|string, mixed>  $vistas
     * @param  Collection<int|string, mixed>  $lineas
     * @param  array<string, true>  $cuentas
     * @return array<string, mixed>
     */
    private function fila(object $c, CarbonImmutable $hoy, Collection $vistas, Collection $lineas, array $cuentas, int $usuarioId): array
    {
        $dias = $c->presentacion === null ? null : (int) $hoy->diffInDays(CarbonImmutable::parse($c->presentacion), false);
        $responsable = $c->responsable_user_id === null ? null : (once(fn () => DB::table('users')->pluck('name', 'id'))[$c->responsable_user_id] ?? null);

        return [
            'id' => (int) $c->id, 'nomenclatura' => $c->nomenclatura, 'entidad' => $c->entidad, 'objeto' => $c->objeto, 'tipo' => $c->tipo,
            'departamento' => $c->departamento ?? null, 'claveEntidad' => $c->ruc_entidad ?? 'n:'.md5($c->entidad),
            'valor' => $c->valor_referencial === null ? null : (string) $c->valor_referencial, 'moneda' => $c->moneda, 'publicada' => $c->publicada,
            'presentacion' => $c->presentacion === null ? null : CarbonImmutable::parse($c->presentacion), 'dias' => $dias,
            'urgente' => $dias !== null && $dias >= 0 && $dias < self::DIAS_POR_VENCER, 'abierta' => $dias === null || $dias >= 0,
            'estado' => $c->estado, 'nueva' => ! $vistas->has($c->id), 'prioritaria' => (bool) $c->prioritaria, 'enBandeja' => (bool) $c->en_bandeja,
            'porQuePrioritaria' => self::porQuePrioritaria($c->motivo_prioridad ?? null),
            'lineaId' => $c->linea_id === null ? null : (int) $c->linea_id, 'linea' => $c->linea_id === null ? null : $lineas[$c->linea_id] ?? null,
            'coincide' => $c->coincide === null ? [] : array_map('trim', explode(',', $c->coincide)),
            'responsableId' => $c->responsable_user_id === null ? null : (int) $c->responsable_user_id, 'responsable' => $responsable,
            'etiquetas' => $c->etiquetas === null ? [] : (array) json_decode($c->etiquetas, true),
            'esCliente' => $c->ruc_entidad !== null && isset($cuentas[$c->ruc_entidad]), 'mia' => $c->responsable_user_id !== null && (int) $c->responsable_user_id === $usuarioId,
        ];
    }

    /** Observación 8.11: lo que dice la estrella al pasar el puntero o tocarla. */
    public static function porQuePrioritaria(?string $motivo): string
    {
        $m = mb_strtolower((string) $motivo);
        $alto = str_contains($m, 'valor');
        $cliente = str_contains($m, 'cliente') || str_contains($m, 'cuenta');

        return match (true) {
            $alto && $cliente => 'Prioritaria: valor referencial alto y ya es cliente',
            $alto => 'Prioritaria: valor referencial alto',
            $cliente => 'Prioritaria: ya es cliente',
            default => 'Prioritaria',
        };
    }

    /** @return array<string, true> los RUC de las entidades que ya son cuenta */
    private function rucsDeCuentas(): array
    {
        return once(fn (): array => DB::table('cuentas')->join('parties', 'parties.id', '=', 'cuentas.party_id')->whereNotNull('parties.numero_documento')
            ->pluck('parties.numero_documento')->mapWithKeys(fn ($r): array => [(string) $r => true])->all());
    }

    /** «Con esta entidad»: proyectos ganados por Techquk y el año del último (atajo: consulta directa a oportunidades). */
    private function conEntidad(?string $ruc): ?string
    {
        if ($ruc === null) {
            return null;
        }

        $ganadas = DB::table('oportunidades as o')->join('cuentas as c', 'c.id', '=', 'o.cuenta_id')->join('parties as p', 'p.id', '=', 'c.party_id')
            ->join('estados as e', 'e.id', '=', 'o.estado_id')->where('p.numero_documento', $ruc)->where('e.codigo', 'ganada')
            ->selectRaw('count(*) as n, max(o.fecha_cierre_real) as ultima')->first();

        if ($ganadas === null || (int) $ganadas->n === 0) {
            return DB::table('cuentas as c')->join('parties as p', 'p.id', '=', 'c.party_id')->where('p.numero_documento', $ruc)->exists() ? 'Ya es cuenta · sin proyectos ganados' : 'Aún no trabajamos con ella';
        }

        return $ganadas->n.((int) $ganadas->n === 1 ? ' proyecto ganado' : ' proyectos ganados').' · último en '.substr((string) $ganadas->ultima, 0, 4);
    }
}
