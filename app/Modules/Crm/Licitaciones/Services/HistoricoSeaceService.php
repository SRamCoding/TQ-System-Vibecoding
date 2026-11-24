<?php

declare(strict_types=1);

namespace App\Modules\Crm\Licitaciones\Services;

use Generator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;
use ZipArchive;

/**
 * MVP rápido (observación 8.12, MV-092): el histórico del OECE desde sus descargas masivas mensuales
 * (`/api/v1/file/seace_v3/json/{año}/{mes}/`: un ZIP con un «record package» de una sola línea). En el MVP solo un año
 * y solo las regiones de las líneas de negocio. Se recorre proceso por proceso sin cargar el archivo en memoria; se
 * guarda solo lo liviano por lotes de 500, sin eventos ni avisos, sin bandeja ni prioridad (`es_historico`). Cada mes
 * guarda por dónde va: si se corta o se pausa, sigue desde ahí. Primero un ensayo que solo cuenta.
 */
final readonly class HistoricoSeaceService
{
    public const string FUENTE = 'seace_v3';

    public const int LOTE = 500;

    private const string URL = 'https://contratacionesabiertas.oece.gob.pe/api/v1/file/%s/json/%d/%02d/';

    private const string CARPETA = 'seace/historico';

    /** Las regiones de las líneas que tienen regiones elegidas (las «Todas» no amplían la carga del MVP). */
    public function regiones(): array
    {
        return array_values(array_unique(array_merge(...array_map(fn (array $l): array => $l['regiones'], app(FiltroConvocatorias::class)->lineas()))));
    }

    /**
     * El ensayo: baja los doce meses, los recorre y cuenta (procesos, en las regiones, los que ya estaban, peso y tiempo)
     * sin guardar nada. Deja el resultado por mes en `seace_cargas` (es_ensayo).
     *
     * @return array{procesos: int, enRegiones: int, yaEstaban: int, bytes: int, segundos: int, estimadoCarga: int, porMes: list<array<string, int>>}
     */
    public function ensayo(int $anio, ?\Closure $avance = null): array
    {
        $regiones = $this->regiones();
        $porMes = [];

        for ($mes = 1; $mes <= 12; $mes++) {
            $inicio = microtime(true);
            $archivo = $this->bajar($anio, $mes);
            [$procesos, $enRegiones, $yaEstaban] = [0, 0, 0];
            $ocids = [];

            foreach ($this->procesos($archivo) as $r) {
                $procesos++;

                if ($this->enRegiones($r, $regiones)) {
                    $enRegiones++;
                    $ocids[] = $r['ocid'] ?? '';

                    if (count($ocids) === self::LOTE) {
                        $yaEstaban += DB::table('convocatorias_seace')->whereIn('ocid', $ocids)->count();
                        $ocids = [];
                    }
                }
            }

            $yaEstaban += $ocids === [] ? 0 : DB::table('convocatorias_seace')->whereIn('ocid', $ocids)->count();
            $fila = ['bytes' => Storage::disk('local')->size($archivo), 'procesos' => $procesos, 'en_regiones' => $enRegiones, 'ya_estaban' => $yaEstaban, 'segundos' => (int) ceil(microtime(true) - $inicio)];
            DB::table('seace_cargas')->updateOrInsert(['anio' => $anio, 'mes' => $mes, 'fuente' => self::FUENTE, 'es_ensayo' => true], [...$fila, 'estado' => 'hecha', 'updated_at' => now(), 'created_at' => now()]);
            $porMes[] = ['mes' => $mes, ...$fila];
            $avance?->__invoke($mes, $fila);
        }

        $total = fn (string $k): int => array_sum(array_column($porMes, $k));
        // Guardar cuesta más que leer: se mide un lote de prueba dentro de una transacción que se deshace.
        $porProceso = $this->segundosPorProcesoAlGuardar($anio);

        return ['procesos' => $total('procesos'), 'enRegiones' => $total('en_regiones'), 'yaEstaban' => $total('ya_estaban'), 'bytes' => $total('bytes'), 'segundos' => $total('segundos'),
            'estimadoCarga' => (int) ceil($total('segundos') + $total('en_regiones') * $porProceso), 'porMes' => $porMes];
    }

    /**
     * La carga de verdad, retomable: cada mes sigue desde su posición; entre lotes mira si la pausaron.
     *
     * @return array{guardados: int, pausada: bool}
     */
    public function cargar(int $anio, ?\Closure $avance = null): array
    {
        $regiones = $this->regiones();
        $guardados = 0;

        for ($mes = 1; $mes <= 12; $mes++) {
            $clave = ['anio' => $anio, 'mes' => $mes, 'fuente' => self::FUENTE, 'es_ensayo' => false];
            $fila = DB::table('seace_cargas')->where($clave)->first();

            if ($fila?->estado === 'hecha') {
                continue;
            }

            DB::table('seace_cargas')->updateOrInsert($clave, ['estado' => 'en_curso', 'error' => null, 'updated_at' => now(), 'created_at' => $fila->created_at ?? now()]);
            $desde = (int) ($fila->posicion ?? 0);
            $inicio = microtime(true);

            try {
                $archivo = $this->bajar($anio, $mes);
                $lote = [];
                $posicion = 0;
                [$procesos, $enRegiones] = [0, 0];

                foreach ($this->procesos($archivo) as $r) {
                    $posicion++;
                    $procesos++;

                    if ($posicion <= $desde) {
                        continue;
                    }

                    if ($this->enRegiones($r, $regiones)) {
                        $enRegiones++;
                        $lote[] = $r;
                    }

                    if (count($lote) === self::LOTE) {
                        $guardados += $this->guardarLote($lote);
                        $lote = [];
                        DB::table('seace_cargas')->where($clave)->update(['posicion' => $posicion, 'guardados' => DB::raw('guardados + '.self::LOTE), 'updated_at' => now()]);
                        $avance?->__invoke($mes, $posicion);

                        if (DB::table('seace_cargas')->where($clave)->value('estado') === 'pausada') {
                            return ['guardados' => $guardados, 'pausada' => true];
                        }
                    }
                }

                $n = $lote === [] ? 0 : $this->guardarLote($lote);
                $guardados += $n;
                DB::table('seace_cargas')->where($clave)->update(['estado' => 'hecha', 'posicion' => $posicion, 'procesos' => $procesos, 'en_regiones' => DB::raw('GREATEST(en_regiones, '.$enRegiones.')'),
                    'guardados' => DB::raw('guardados + '.$n), 'bytes' => Storage::disk('local')->size($archivo), 'segundos' => DB::raw('segundos + '.(int) ceil(microtime(true) - $inicio)), 'updated_at' => now()]);
            } catch (Throwable $e) {
                DB::table('seace_cargas')->where($clave)->update(['estado' => 'error', 'error' => mb_substr($e->getMessage(), 0, 300), 'updated_at' => now()]);

                throw $e;
            }
        }

        return ['guardados' => $guardados, 'pausada' => false];
    }

    public function pausar(int $anio): void
    {
        DB::table('seace_cargas')->where('anio', $anio)->where('es_ensayo', false)->where('estado', 'en_curso')->update(['estado' => 'pausada', 'updated_at' => now()]);
    }

    /**
     * El avance de la carga de un año (para la caja Sistema): porcentaje por procesos recorridos sobre los del ensayo.
     *
     * @return array{porcentaje: int, estado: string, guardados: int, meses: list<object>, ensayo: list<object>}
     */
    public function avance(int $anio): array
    {
        $meses = DB::table('seace_cargas')->where('anio', $anio)->where('es_ensayo', false)->orderBy('mes')->get();
        $ensayo = DB::table('seace_cargas')->where('anio', $anio)->where('es_ensayo', true)->orderBy('mes')->get();
        $total = max(1, (int) $ensayo->sum('procesos'));
        $recorridos = (int) $meses->sum(fn (object $m): int => $m->estado === 'hecha' ? (int) ($ensayo->firstWhere('mes', $m->mes)->procesos ?? $m->posicion) : (int) $m->posicion);
        $estado = match (true) {
            $meses->isEmpty() => 'sin_empezar',
            $meses->contains('estado', 'en_curso') => 'en_curso',
            $meses->contains('estado', 'error') => 'error',
            $meses->contains('estado', 'pausada') => 'pausada',
            $meses->count() === 12 && $meses->every(fn (object $m): bool => $m->estado === 'hecha') => 'hecha',
            default => 'pausada',
        };

        return ['porcentaje' => min(100, (int) floor($recorridos * 100 / $total)), 'estado' => $estado, 'guardados' => (int) $meses->sum('guardados'), 'meses' => $meses->all(), 'ensayo' => $ensayo->all()];
    }

    /**
     * La verificación: el total del año guardado contra el que informa el OECE (los procesos de sus archivos en las
     * regiones) y 5 procesos al azar revisados contra su registro en la API (ganador y monto adjudicado).
     *
     * @return array{oece: int, guardados: int, muestra: list<array{ocid: string, ok: bool, detalle: string}>}
     */
    public function verificar(int $anio): array
    {
        $oece = (int) DB::table('seace_cargas')->where('anio', $anio)->where('es_ensayo', false)->sum('en_regiones');
        $guardados = DB::table('convocatorias_seace')->where('es_historico', true)->where('es_demo', false)->whereYear('publicada', $anio)->count();
        $muestra = [];

        foreach (DB::table('convocatorias_seace')->where('es_historico', true)->where('es_demo', false)->whereYear('publicada', $anio)->inRandomOrder()->limit(5)->get() as $c) {
            try {
                $rec = Http::timeout(30)->acceptJson()->withUserAgent('TechQuk-OCDS/1.0')->get('https://contratacionesabiertas.oece.gob.pe/api/v1/record/'.$c->ocid)->throw()->json();
                $cr = $rec['records'][0]['compiledRelease'] ?? $rec['compiledRelease'] ?? [];
                $award = collect($cr['awards'] ?? [])->first();
                $monto = $award['value']['amount'] ?? null;
                $ok = ($cr['ocid'] ?? null) === $c->ocid && ($monto === null || $c->monto_adjudicado === null || abs((float) $monto - (float) $c->monto_adjudicado) < 0.01);
                $muestra[] = ['ocid' => $c->ocid, 'ok' => $ok, 'detalle' => $ok ? 'coincide' : 'difiere el monto adjudicado'];
            } catch (Throwable $e) {
                $muestra[] = ['ocid' => $c->ocid, 'ok' => false, 'detalle' => 'no se pudo consultar: '.mb_substr($e->getMessage(), 0, 80)];
            }
        }

        return ['oece' => $oece, 'guardados' => $guardados, 'muestra' => $muestra];
    }

    /** Baja el ZIP del mes (si no está) y devuelve la ruta del JSON descomprimido. Los datos no van al repositorio. */
    private function bajar(int $anio, int $mes): string
    {
        $disco = Storage::disk('local');
        $json = sprintf('%s/%d-%02d_%s.json', self::CARPETA, $anio, $mes, self::FUENTE);

        if ($disco->exists($json)) {
            return $json;
        }

        $zip = $json.'.zip';
        $disco->makeDirectory(self::CARPETA);
        Http::timeout(600)->withUserAgent('TechQuk-OCDS/1.0')->sink($disco->path($zip))->get(sprintf(self::URL, self::FUENTE, $anio, $mes))->throw();
        $z = new ZipArchive;

        if ($z->open($disco->path($zip)) !== true || $z->numFiles < 1) {
            throw new RuntimeException("El archivo de {$mes}/{$anio} del OECE no es un ZIP válido.");
        }

        copy('zip://'.$disco->path($zip).'#'.$z->getNameIndex(0), $disco->path($json));
        $z->close();
        $disco->delete($zip);

        return $json;
    }

    /**
     * Recorre el «record package» proceso por proceso, leyendo por partes: busca el arreglo `records` y separa cada
     * objeto contando llaves (respetando textos y escapes); de cada uno entrega su `compiledRelease`.
     *
     * @return Generator<int, array<string, mixed>>
     */
    public function procesos(string $archivo): Generator
    {
        $f = fopen(Storage::disk('local')->path($archivo), 'rb');

        if ($f === false) {
            throw new RuntimeException("No se pudo abrir {$archivo}.");
        }

        $buffer = '';
        $enArreglo = false;
        $nivel = 0;
        $enTexto = false;
        $escape = false;
        $objeto = '';

        while (! feof($f)) {
            $buffer .= (string) fread($f, 1 << 20);

            if (! $enArreglo) {
                $p = strpos($buffer, '"records"');

                if ($p === false) {
                    $buffer = substr($buffer, -20);

                    continue;
                }

                $c = strpos($buffer, '[', $p);

                if ($c === false) {
                    continue;
                }

                $buffer = substr($buffer, $c + 1);
                $enArreglo = true;
            }

            $largo = strlen($buffer);
            $inicioObjeto = $nivel > 0 ? 0 : null;

            for ($i = 0; $i < $largo; $i++) {
                $ch = $buffer[$i];

                if ($enTexto) {
                    if ($escape) {
                        $escape = false;
                    } elseif ($ch === '\\') {
                        $escape = true;
                    } elseif ($ch === '"') {
                        $enTexto = false;
                    }

                    continue;
                }

                if ($ch === '"') {
                    $enTexto = $nivel > 0;
                } elseif ($ch === '{') {
                    if ($nivel === 0) {
                        $inicioObjeto = $i;
                    }

                    $nivel++;
                } elseif ($ch === '}') {
                    $nivel--;

                    if ($nivel === 0 && $inicioObjeto !== null) {
                        $objeto .= substr($buffer, $inicioObjeto, $i - $inicioObjeto + 1);
                        $r = json_decode($objeto, true);
                        $objeto = '';
                        $inicioObjeto = null;

                        if (is_array($r)) {
                            yield $r['compiledRelease'] ?? $r;
                        }
                    }
                } elseif ($ch === ']' && $nivel === 0) {
                    fclose($f);

                    return;
                }
            }

            if ($nivel > 0 && $inicioObjeto !== null) {
                $objeto .= substr($buffer, $inicioObjeto);
            }

            $buffer = '';
        }

        fclose($f);
    }

    /** @param  array<string, mixed>  $r  @param  list<string>  $regiones */
    private function enRegiones(array $r, array $regiones): bool
    {
        if ($regiones === []) {
            return true;
        }

        $comprador = collect($r['parties'] ?? [])->firstWhere('id', $r['buyer']['id'] ?? null) ?? [];

        return in_array(mb_strtoupper((string) ($comprador['address']['department'] ?? '')), $regiones, true);
    }

    /**
     * Guarda un lote en silencio: lo liviano, sin eventos; si ya existe, solo si el dato es más nuevo.
     *
     * @param  list<array<string, mixed>>  $lote
     */
    private function guardarLote(array $lote): int
    {
        $s = app(ConvocatoriasSeaceService::class);
        $filas = [];

        foreach ($lote as $r) {
            $datos = $s->datosLivianos($r);

            if ($datos !== null) {
                $filas[$r['ocid']] = ['ocid' => $r['ocid'], ...$datos, 'estado' => 'nueva', 'es_historico' => true, 'en_bandeja' => false, 'prioritaria' => false, 'created_at' => now(), 'updated_at' => now()];
            }
        }

        if ($filas === []) {
            return 0;
        }

        $actualizar = array_values(array_diff(array_keys(reset($filas)), ['ocid', 'estado', 'es_historico', 'en_bandeja', 'prioritaria', 'created_at']));

        DB::transaction(function () use ($filas, $actualizar): void {
            // Las que ya estaban (de la lectura diaria) conservan su estado y no pasan a históricas; se actualizan solo si el dato es más nuevo.
            $existentes = DB::table('convocatorias_seace')->whereIn('ocid', array_keys($filas))->pluck('release_fecha', 'ocid');
            $nuevas = array_values(array_filter($filas, fn (array $f): bool => ! $existentes->has($f['ocid'])));

            if ($nuevas !== []) {
                DB::table('convocatorias_seace')->insert($nuevas);
            }

            foreach ($existentes as $ocid => $fecha) {
                if ($fecha === null || strtotime((string) $filas[$ocid]['release_fecha']) > strtotime((string) $fecha)) {
                    DB::table('convocatorias_seace')->where('ocid', $ocid)->update(array_intersect_key($filas[$ocid], array_flip($actualizar)));
                }
            }
        });

        return count($filas);
    }

    /** Cuánto tarda guardar un proceso: un lote de prueba con el primer mes, dentro de una transacción que se deshace. */
    private function segundosPorProcesoAlGuardar(int $anio): float
    {
        $lote = [];

        foreach ($this->procesos($this->bajar($anio, 1)) as $r) {
            $lote[] = $r;

            if (count($lote) === self::LOTE) {
                break;
            }
        }

        if ($lote === []) {
            return 0.0;
        }

        $inicio = microtime(true);
        DB::beginTransaction();

        try {
            $this->guardarLote($lote);
        } finally {
            DB::rollBack();
        }

        return (microtime(true) - $inicio) / count($lote);
    }
}
