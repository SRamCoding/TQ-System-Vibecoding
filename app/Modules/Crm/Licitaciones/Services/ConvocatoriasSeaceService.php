<?php

declare(strict_types=1);

namespace App\Modules\Crm\Licitaciones\Services;

use App\Modules\Crm\Cuentas\Services\CuentasService;
use App\Modules\Crm\Cuentas\Services\DatosCuenta;
use App\Modules\Crm\Licitaciones\Enums\TipoProcedimiento;
use App\Modules\Crm\Licitaciones\Events\ConvocatoriaCambio;
use App\Modules\Crm\Licitaciones\Events\ConvocatoriaLlego;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Services\ParametrosService;
use App\Modules\Nucleo\Tiempo\Calendario;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * MVP rápido (ficha t1-licitaciones v2 y observación 7.4): trae las convocatorias del SEACE desde los datos abiertos
 * del OECE (estándar de contrataciones abiertas OCDS: paquetes de «releases») y guarda todo lo liviano; la bandeja
 * muestra lo que coincide con las palabras clave de Techquk y su valor mínimo (`FiltroConvocatorias`), y cada llegada o
 * cambio dispara sus reacciones (filtrar, priorizar, avisar en Mien). «Registrar» la vuelve licitación (creando la cuenta de la
 * entidad si no existe) sin escribir nada.
 */
final readonly class ConvocatoriasSeaceService
{
    /** MV-066: cuántas páginas de la API del OECE se leen como máximo en una lectura. */
    public const int PAGINAS_MAXIMAS = 20;

    /** Observación 7.4: desde este valor referencial (soles) una convocatoria es prioritaria. */
    public const string PARAMETRO_PRIORIDAD = 'crm.seace_valor_prioritaria';

    /** Observación 7.4: al entrar, se lee otra vez si la última lectura tiene más de estos minutos. */
    public const int MINUTOS_FRESCA = 15;

    /** Observación 7.4: lo que la demo deja «por publicar» (solo local). */
    public const string DEMO_POR_PUBLICAR = 'seace/por-publicar.json';

    /**
     * Observación 7.4: lee solo lo nuevo. Pide lo más reciente primero (`order=desc`) y sigue las páginas hasta llegar a
     * un release que ya tiene (la fecha del más nuevo guardado), con un tope de páginas. Cada convocatoria se guarda
     * entera en lo liviano (entre o no a la bandeja); las nuevas y las que cambiaron disparan sus eventos. Queda anotada
     * la lectura (para «Leído hace…» y para decir si el OECE no respondió).
     *
     * @return array{leidas: int, nuevas: int, actualizadas: int, a_bandeja: int}
     */
    public function sincronizar(string $origen = 'a_mano'): array
    {
        $lectura = DB::table('seace_lecturas')->insertGetId(['inicio' => now(), 'origen' => $origen]);
        $total = ['leidas' => 0, 'nuevas' => 0, 'actualizadas' => 0, 'a_bandeja' => 0];

        try {
            app(FiltroConvocatorias::class)->reaplicarSiCambio();
            // En local, lo que la demo dejó «por publicar» llega primero (atajo del MVP para ver la llegada en vivo).
            $this->sumar($total, $this->pendientesDeDemo());
            $url = (string) config('services.oece.ocds_url');

            if ($url === '') {
                throw new RuntimeException('Falta la dirección de los datos abiertos del OECE (OECE_OCDS_URL en el .env).');
            }

            $hasta = DB::table('convocatorias_seace')->max('release_fecha');
            $hasta = $hasta === null ? Calendario::ahora()->subDays(3) : CarbonImmutable::parse($hasta);
            $url = (string) preg_replace('#/releasesAfter(?=$|\?)#', '/releases', $url);
            $siguiente = $url.(str_contains($url, '?') ? '&' : '?').'format=json&order=desc';

            for ($pagina = 0; $pagina < self::PAGINAS_MAXIMAS && $siguiente !== null; $pagina++) {
                try {
                    // La dirección va completa; el OECE rechaza (403) la cabecera por defecto de Guzzle.
                    $json = Http::timeout(30)->acceptJson()->withUserAgent('TechQuk-OCDS/1.0')->get($siguiente)->throw()->body();
                } catch (Throwable $e) {
                    if ($pagina === 0) {
                        throw new RuntimeException('El OECE no respondió: '.mb_substr($e->getMessage(), 0, 120));
                    }

                    break;
                }

                $datos = json_decode($json, true);
                $releases = is_array($datos) ? self::releasesDe($datos) : [];
                $nuevos = array_values(array_filter($releases, fn (array $r): bool => ! isset($r['date']) || CarbonImmutable::parse($r['date'])->greaterThan($hasta)));
                $this->sumar($total, $this->guardarTodos($nuevos));
                // Llegó a lo que ya tenía: se detiene.
                $siguiente = count($nuevos) < count($releases) || $releases === [] ? null : ($datos['links']['next'] ?? null);
            }

            DB::table('seace_lecturas')->where('id', $lectura)->update(['fin' => now(), 'es_ok' => true, ...$total]);
        } catch (Throwable $e) {
            DB::table('seace_lecturas')->where('id', $lectura)->update(['fin' => now(), 'es_ok' => false, 'error' => mb_substr($e->getMessage(), 0, 300), ...$total]);

            throw $e instanceof RuntimeException ? $e : new RuntimeException($e->getMessage());
        }

        return $total;
    }

    /**
     * Observación 7.4: al entrar a la bandeja, si la última lectura tiene más de 15 minutos, lee otra (una sola a la vez).
     * Nunca lanza: si el OECE no responde, queda anotado y la pantalla sigue con lo guardado.
     *
     * @return array{leidas: int, nuevas: int, actualizadas: int, a_bandeja: int}|null nulo si no hacía falta
     */
    public function leerSiHaceFalta(): ?array
    {
        $ultima = DB::table('seace_lecturas')->max('inicio');

        if ($ultima !== null && CarbonImmutable::parse($ultima)->greaterThan(Calendario::ahora()->subMinutes(self::MINUTOS_FRESCA))) {
            return null;
        }

        $candado = Cache::lock('crm.seace.lectura', 120);

        if (! $candado->get()) {
            return null;
        }

        try {
            return $this->sincronizar('al_entrar');
        } catch (Throwable) {
            return null;
        } finally {
            $candado->release();
        }
    }

    /**
     * La última lectura que terminó bien y, si la última de todas falló, su error.
     *
     * @return array{ok: ?CarbonImmutable, fallo: ?string, falloEn: ?CarbonImmutable}
     */
    public function estadoLectura(): array
    {
        $ok = DB::table('seace_lecturas')->where('es_ok', true)->max('fin');
        $ultima = DB::table('seace_lecturas')->whereNotNull('fin')->orderByDesc('id')->first();

        return ['ok' => $ok === null ? null : CarbonImmutable::parse($ok), 'fallo' => $ultima !== null && ! $ultima->es_ok ? $ultima->error : null,
            'falloEn' => $ultima !== null && ! $ultima->es_ok ? CarbonImmutable::parse($ultima->fin) : null];
    }

    /**
     * @param  array<mixed>  $datos
     * @return list<array<string, mixed>>
     */
    private static function releasesDe(array $datos): array
    {
        return $datos['releases'] ?? (isset($datos['records']) ? array_map(fn (array $r): array => $r['compiledRelease'] ?? ($r['releases'][0] ?? []), $datos['records']) : (array_is_list($datos) ? $datos : []));
    }

    /**
     * Importa un paquete OCDS (release package, record package o una lista de releases): todo se guarda.
     *
     * @return array{leidas: int, nuevas: int, actualizadas: int, a_bandeja: int}
     */
    public function importar(string $json): array
    {
        $datos = json_decode($json, true);

        if (! is_array($datos)) {
            throw new RuntimeException('El archivo no es un JSON de contrataciones abiertas (OCDS).');
        }

        return $this->guardarTodos(self::releasesDe($datos));
    }

    /**
     * @param  list<array<string, mixed>>  $releases
     * @return array{leidas: int, nuevas: int, actualizadas: int, a_bandeja: int}
     */
    private function guardarTodos(array $releases): array
    {
        $total = ['leidas' => 0, 'nuevas' => 0, 'actualizadas' => 0, 'a_bandeja' => 0];

        foreach ($releases as $r) {
            $total['leidas']++;
            $hecho = $this->guardar($r);

            if ($hecho === null) {
                continue;
            }

            $total[$hecho['nueva'] ? 'nuevas' : 'actualizadas']++;
            $total['a_bandeja'] += $hecho['nueva'] && DB::table('convocatorias_seace')->where('id', $hecho['id'])->value('en_bandeja') ? 1 : 0;
        }

        return $total;
    }

    /**
     * Guarda lo liviano de un release por su código OCDS (único): si no estaba, la crea y avisa «llegó»; si llega una
     * versión más nueva, la actualiza, anota qué cambió y avisa «cambió». Si llega dos veces la misma, no hace nada.
     *
     * @param  array<string, mixed>  $r
     * @return array{id: int, nueva: bool}|null
     */
    public function guardar(array $r): ?array
    {
        if (! isset($r['ocid'])) {
            return null;
        }

        $t = $r['tender'] ?? [];
        $comprador = collect($r['parties'] ?? [])->firstWhere('id', $r['buyer']['id'] ?? null) ?? [];
        $ruc = preg_replace('/\D/', '', (string) ($comprador['identifier']['id'] ?? ''));
        $fecha = fn (?string $f): ?string => $f === null ? null : substr($f, 0, 10);
        $valor = $t['value']['amount'] ?? null;
        $award = collect($r['awards'] ?? [])->first(fn (array $a): bool => ($a['status'] ?? 'active') === 'active') ?? ($r['awards'][0] ?? null);
        $proveedor = $award['suppliers'][0] ?? null;
        $titulo = trim((string) ($t['title'] ?? ''));
        $descripcion = trim((string) ($t['description'] ?? ''));
        $datos = [
            'nomenclatura' => mb_substr((string) ($t['id'] ?? $r['ocid']), 0, 120), 'entidad' => mb_substr((string) ($r['buyer']['name'] ?? $comprador['name'] ?? 'Entidad'), 0, 250),
            'ruc_entidad' => strlen($ruc) === 11 ? $ruc : null, 'region' => $comprador['address']['region'] ?? null,
            // Observación 8.9: la región (departamento) de la entidad; `region` del OECE es la provincia.
            'departamento' => isset($comprador['address']['department']) ? mb_strtoupper(mb_substr((string) $comprador['address']['department'], 0, 40)) : null,
            // En el OECE el título suele ser la nomenclatura: el objeto es la descripción si la hay.
            'objeto' => mb_substr($descripcion !== '' ? $descripcion : $titulo, 0, 600),
            'texto' => mb_substr(trim($titulo.' '.$descripcion.' '.implode(' ', array_map(fn ($i): string => (string) ($i['description'] ?? ''), $t['items'] ?? []))), 0, 2000),
            'tipo' => mb_substr((string) ($t['procurementMethodDetails'] ?? 'Por definir'), 0, 30),
            // El OECE manda 0 cuando el valor no es público.
            'valor_referencial' => $valor !== null && (float) $valor > 0 ? $valor : null, 'moneda' => $t['value']['currency'] ?? 'PEN', 'publicada' => $fecha($t['datePublished'] ?? $r['date'] ?? null),
            'consultas_hasta' => $fecha($t['enquiryPeriod']['endDate'] ?? null), 'presentacion' => $fecha($t['tenderPeriod']['endDate'] ?? null), 'buena_pro' => $fecha($t['awardPeriod']['startDate'] ?? null),
            'url' => mb_substr((string) (collect($t['documents'] ?? [])->first()['url'] ?? ''), 0, 500) ?: null,
            'estado_ocds' => isset($t['status']) ? mb_substr((string) $t['status'], 0, 30) : null,
            // Observación 8.10: los enlaces a los documentos del proceso (de la convocatoria y de la adjudicación).
            'documentos' => json_encode(self::documentosDe($r), JSON_UNESCAPED_UNICODE),
            'ganador' => $proveedor === null ? null : mb_substr((string) ($proveedor['name'] ?? ''), 0, 250),
            'ganador_ruc' => $proveedor === null ? null : (strlen($g = preg_replace('/\D/', '', (string) ($proveedor['id'] ?? ''))) === 11 ? $g : null),
            'monto_adjudicado' => isset($award['value']['amount']) && (float) $award['value']['amount'] > 0 ? $award['value']['amount'] : null,
            // Con su desfase: así Postgres no la corre de zona horaria y «hasta dónde se leyó» compara bien.
            'release_fecha' => (isset($r['date']) ? CarbonImmutable::parse($r['date']) : CarbonImmutable::now())->format('Y-m-d\TH:i:s.uP'),
        ];
        $guardada = DB::table('convocatorias_seace')->where('ocid', $r['ocid'])->first();

        if ($guardada === null) {
            $id = (int) DB::table('convocatorias_seace')->insertGetId(['ocid' => $r['ocid'], ...$datos, 'estado' => 'nueva', 'created_at' => now(), 'updated_at' => now()]);
            event(new ConvocatoriaLlego($id));

            return ['id' => $id, 'nueva' => true];
        }

        if ($guardada->release_fecha !== null && ! CarbonImmutable::parse($datos['release_fecha'])->greaterThan(CarbonImmutable::parse($guardada->release_fecha))) {
            return null;
        }

        $cambios = [];

        foreach (['presentacion', 'buena_pro', 'consultas_hasta', 'valor_referencial', 'estado_ocds', 'ganador', 'monto_adjudicado', 'objeto'] as $campo) {
            $antes = $guardada->{$campo} === null ? null : (string) $guardada->{$campo};
            $despues = $datos[$campo] === null ? null : (string) $datos[$campo];

            if (is_numeric($antes) && is_numeric($despues) ? bccomp($antes, $despues, 2) !== 0 : $antes !== $despues) {
                $cambios[$campo] = [$antes, $despues];
            }
        }

        DB::table('convocatorias_seace')->where('id', $guardada->id)->update([...$datos, 'updated_at' => now(),
            ...($cambios === [] ? [] : ['cambios' => json_encode($cambios, JSON_UNESCAPED_UNICODE), 'cambiada_at' => now()])]);

        if ($cambios !== []) {
            event(new ConvocatoriaCambio((int) $guardada->id, $cambios));
        }

        return ['id' => (int) $guardada->id, 'nueva' => false];
    }

    /** La página pública del proceso en el portal de contrataciones abiertas del OECE (observación 8.10). */
    public const string URL_PROCESO_OECE = 'https://contratacionesabiertas.oece.gob.pe/proceso/';

    /** Cómo se llama un documento del OECE cuando no trae título. */
    public const array TIPOS_DOCUMENTO = ['biddingDocuments' => 'Bases', 'tenderNotice' => 'Aviso de convocatoria', 'clarifications' => 'Absolución de consultas',
        'evaluationReports' => 'Informe de evaluación', 'awardNotice' => 'Acta de buena pro', 'contractSigned' => 'Contrato', 'technicalSpecifications' => 'Especificaciones técnicas'];

    /**
     * Los documentos de un release (bases, bases integradas, actas…): título, tipo, formato y enlace; hasta 15.
     *
     * @param  array<string, mixed>  $r
     * @return list<array{titulo: string, tipo: ?string, formato: ?string, url: string}>
     */
    public static function documentosDe(array $r): array
    {
        $docs = [...($r['tender']['documents'] ?? []), ...array_merge(...array_map(fn (array $a): array => $a['documents'] ?? [], $r['awards'] ?? [])), ...array_merge(...array_map(fn (array $c): array => $c['documents'] ?? [], $r['contracts'] ?? []))];
        $lista = [];

        foreach ($docs as $d) {
            if (! empty($d['url'])) {
                $lista[] = ['titulo' => mb_substr((string) ($d['title'] ?? self::TIPOS_DOCUMENTO[$d['documentType'] ?? ''] ?? 'Documento'), 0, 150), 'tipo' => $d['documentType'] ?? null, 'formato' => isset($d['format']) ? mb_strtoupper((string) $d['format']) : null, 'url' => mb_substr((string) $d['url'], 0, 500)];
            }
        }

        return array_slice($lista, 0, 15);
    }

    /** Observación 7.4, reacción «Priorizar»: valor alto (parámetro) o entidad que ya es cuenta. */
    public function priorizar(int $id): void
    {
        $c = DB::table('convocatorias_seace')->find($id);

        if ($c === null) {
            return;
        }

        $umbral = app(ParametrosService::class)->entero(self::PARAMETRO_PRIORIDAD);
        $esCuenta = $c->ruc_entidad !== null && DB::table('cuentas')->join('parties', 'parties.id', '=', 'cuentas.party_id')->where('parties.numero_documento', $c->ruc_entidad)->exists();
        $alto = $c->valor_referencial !== null && (float) $c->valor_referencial >= $umbral;
        DB::table('convocatorias_seace')->where('id', $id)->update([
            'prioritaria' => $alto || $esCuenta,
            'motivo_prioridad' => $alto && $esCuenta ? 'Valor alto y ya es cliente' : ($alto ? 'Valor desde S/ '.number_format($umbral, 0) : ($esCuenta ? 'La entidad ya es cuenta' : null)),
        ]);
    }

    /**
     * Atajo del MVP (solo en local): releases que la demo dejó «por publicar» en `storage/app/seace/por-publicar.json`;
     * la primera lectura los trae (así se ve una convocatoria llegar sola) y el archivo se borra.
     *
     * @return array{leidas: int, nuevas: int, actualizadas: int, a_bandeja: int}
     */
    private function pendientesDeDemo(): array
    {
        $disco = Storage::disk('local');

        if (! app()->environment('local') || ! $disco->exists(self::DEMO_POR_PUBLICAR)) {
            return ['leidas' => 0, 'nuevas' => 0, 'actualizadas' => 0, 'a_bandeja' => 0];
        }

        $json = (string) $disco->get(self::DEMO_POR_PUBLICAR);
        $disco->delete(self::DEMO_POR_PUBLICAR);

        return $this->importar($json);
    }

    /** @param  array<string, int>  $total @param  array<string, int>  $parte */
    private function sumar(array &$total, array $parte): void
    {
        foreach ($total as $k => $v) {
            $total[$k] = $v + ($parte[$k] ?? 0);
        }
    }

    /** La vuelve licitación «por decidir», con su cronograma, creando la cuenta de la entidad si no existe. */
    public function registrar(int $id, User $quien): int
    {
        return DB::transaction(function () use ($id, $quien): int {
            $c = DB::table('convocatorias_seace')->lockForUpdate()->find($id);

            if ($c === null || $c->estado !== 'nueva') {
                throw new RuntimeException('Esa convocatoria ya se registró o se descartó.');
            }

            if ($c->ruc_entidad === null) {
                throw new RuntimeException('La convocatoria no trae el RUC de la entidad: regístrala a mano.');
            }

            $cuentas = app(CuentasService::class);
            $revision = $cuentas->revisarRuc($c->ruc_entidad);
            $cuentaId = $revision->cuenta?->id ?? $cuentas->crear(new DatosCuenta($c->ruc_entidad, $quien->id,
                (int) DB::table('origenes_comerciales')->where('codigo', 'CONVOCATORIA_PUBLICA')->value('id'), true, $this->nivel($c->entidad), razonSocial: $c->entidad))->cuenta->id;

            $tipo = match (true) {
                str_contains(mb_strtolower($c->tipo), 'licitaci') => TipoProcedimiento::LicitacionPublica,
                str_contains(mb_strtolower($c->tipo), 'concurso') => TipoProcedimiento::ConcursoPublico,
                str_contains(mb_strtolower($c->tipo), 'subasta') => TipoProcedimiento::SubastaInversa,
                str_contains(mb_strtolower($c->tipo), 'comparaci') => TipoProcedimiento::ComparacionPrecios,
                default => TipoProcedimiento::AdjudicacionSimplificada,
            };
            $publicada = CarbonImmutable::parse($c->publicada ?? Calendario::hoy());
            $presentacion = $c->presentacion === null ? $publicada->addDays(19) : CarbonImmutable::parse($c->presentacion);
            $buenaPro = $c->buena_pro === null ? $presentacion->addDays(7) : CarbonImmutable::parse($c->buena_pro);
            $cronograma = [['Convocatoria', $publicada->toDateString()], ['Consultas y observaciones', $publicada->addDays(6)->toDateString()], ['Absolución', $publicada->addDays(11)->toDateString()],
                ['Integración de bases', $publicada->addDays(14)->toDateString()], ['Presentación de ofertas', $presentacion->toDateString()], ['Buena pro', $buenaPro->toDateString()]];
            // La línea de negocio de la bandeja, si tiene una línea de solución con el mismo nombre.
            $lineaSolucion = $c->linea_id === null ? null : DB::table('lineas_solucion')->whereRaw('lower(nombre) = lower(?)', [(string) DB::table('seace_lineas')->where('id', $c->linea_id)->value('nombre')])->value('id');
            $l = app(LicitacionesService::class)->registrar($cuentaId, $c->nomenclatura, $tipo, mb_substr($c->objeto, 0, 500), $lineaSolucion === null ? null : (int) $lineaSolucion, $c->valor_referencial === null ? null : (string) $c->valor_referencial, $publicada, $c->url, $cronograma, $quien);
            DB::table('convocatorias_seace')->where('id', $id)->update(['estado' => 'registrada', 'licitacion_id' => $l->id, 'updated_at' => now()]);

            return $l->id;
        });
    }

    /** Observación 7.5: el responsable de revisarla. */
    public function asignar(int $id, ?int $userId): void
    {
        DB::table('convocatorias_seace')->where('id', $id)->update(['responsable_user_id' => $userId, 'updated_at' => now()]);
    }

    /** @param  list<string>  $etiquetas */
    public function etiquetar(int $id, array $etiquetas): void
    {
        $limpias = array_values(array_unique(array_filter(array_map(fn (string $e): string => mb_substr(trim($e), 0, 40), $etiquetas))));
        DB::table('convocatorias_seace')->where('id', $id)->update(['etiquetas' => $limpias === [] ? null : json_encode($limpias, JSON_UNESCAPED_UNICODE), 'updated_at' => now()]);
    }

    /** Observación 7.5, «Traer igual a la bandeja»: entra aunque el filtro la deje fuera. */
    public function traerIgual(int $id): void
    {
        DB::table('convocatorias_seace')->where('id', $id)->update(['es_traida' => true, 'en_bandeja' => true, 'updated_at' => now()]);
        $this->priorizar($id);
    }

    /** Observación 8.9, «Agregar [región] a la línea»; después se vuelve a filtrar todo. */
    public function agregarRegion(string $linea, string $region): void
    {
        $l = DB::table('seace_lineas')->where('nombre', $linea)->first();

        if ($l !== null && in_array($region, FiltroConvocatorias::REGIONES, true)) {
            $regiones = array_values(array_unique([...(array) json_decode($l->regiones, true), $region]));
            DB::table('seace_lineas')->where('id', $l->id)->update(['regiones' => json_encode($regiones, JSON_UNESCAPED_UNICODE), 'updated_at' => now()]);
            app(FiltroConvocatorias::class)->reaplicar();
        }
    }

    /** Observación 8.9, «Quitar la entidad de las excluidas» de una línea; después se vuelve a filtrar todo. */
    public function quitarExcluida(string $linea, string $ruc): void
    {
        $l = DB::table('seace_lineas')->where('nombre', $linea)->first();

        if ($l !== null) {
            $quedan = array_values(array_filter((array) json_decode($l->entidades_excluidas, true), fn (array $e): bool => ($e['ruc'] ?? null) !== $ruc));
            DB::table('seace_lineas')->where('id', $l->id)->update(['entidades_excluidas' => json_encode($quedan, JSON_UNESCAPED_UNICODE), 'updated_at' => now()]);
            app(FiltroConvocatorias::class)->reaplicar();
        }
    }

    /** Observación 7.5, «Quitar la palabra negativa» de una línea; después se vuelve a filtrar todo. */
    public function quitarNegativa(string $linea, string $palabra): void
    {
        $l = DB::table('seace_lineas')->where('nombre', $linea)->first();

        if ($l !== null) {
            $negativas = array_values(array_filter((array) json_decode($l->negativas, true), fn (string $p): bool => $p !== $palabra));
            DB::table('seace_lineas')->where('id', $l->id)->update(['negativas' => json_encode($negativas, JSON_UNESCAPED_UNICODE), 'updated_at' => now()]);
            app(FiltroConvocatorias::class)->reaplicar();
        }
    }

    /**
     * Observación 7.5: guarda las líneas de negocio de la búsqueda (con «+ Nueva línea») y vuelve a filtrar lo guardado.
     *
     * @param  list<array{id?: ?int, nombre: string, palabras: list<string>, negativas: list<string>, minimo: string|int|float}>  $lineas
     */
    public function guardarLineas(array $lineas): void
    {
        DB::transaction(function () use ($lineas): void {
            $quedan = [];

            foreach (array_values($lineas) as $i => $l) {
                $nombre = mb_substr(trim((string) $l['nombre']), 0, 80);

                if ($nombre === '') {
                    continue;
                }

                $datos = ['nombre' => $nombre, 'palabras' => json_encode(self::limpias($l['palabras']), JSON_UNESCAPED_UNICODE), 'negativas' => json_encode(self::limpias($l['negativas']), JSON_UNESCAPED_UNICODE),
                    'regiones' => json_encode(array_values(array_intersect(array_map('mb_strtoupper', (array) ($l['regiones'] ?? [])), FiltroConvocatorias::REGIONES)), JSON_UNESCAPED_UNICODE),
                    'entidades_excluidas' => json_encode(array_values(array_filter((array) ($l['excluidas'] ?? []), fn ($e): bool => is_array($e) && ! empty($e['ruc']))), JSON_UNESCAPED_UNICODE),
                    'valor_minimo' => number_format(max(0, (float) $l['minimo']), 2, '.', ''), 'orden' => $i + 1, 'updated_at' => now()];

                if (! empty($l['id']) && DB::table('seace_lineas')->where('id', $l['id'])->exists()) {
                    DB::table('seace_lineas')->where('id', $l['id'])->update($datos);
                    $quedan[] = (int) $l['id'];
                } else {
                    $quedan[] = (int) DB::table('seace_lineas')->insertGetId([...$datos, 'created_at' => now()]);
                }
            }

            DB::table('seace_lineas')->whereNotIn('id', $quedan === [] ? [0] : $quedan)->delete();
        });

        app(FiltroConvocatorias::class)->reaplicar();
    }

    /** @param  list<string>  $palabras @return list<string> */
    private static function limpias(array $palabras): array
    {
        return array_values(array_unique(array_filter(array_map(fn (string $p): string => mb_strtolower(trim($p)), $palabras))));
    }

    public function descartar(int $id, string $motivo): void
    {
        DB::table('convocatorias_seace')->where('id', $id)->where('estado', 'nueva')->update(['estado' => 'descartada', 'motivo_descarte' => mb_substr($motivo, 0, 200), 'updated_at' => now()]);
    }

    /** El nivel de gobierno por el nombre de la entidad (se puede corregir en la cuenta). */
    private function nivel(string $entidad): int
    {
        $e = mb_strtolower($entidad);
        $codigo = match (true) {
            str_contains($e, 'municipalidad') => 'LOCAL',
            str_contains($e, 'regional') || str_contains($e, 'ugel') || str_contains($e, 'gestión educativa') || str_contains($e, 'proyecto especial') => 'REGIONAL',
            default => 'NACIONAL',
        };

        return (int) DB::table('niveles_gobierno')->where('codigo', $codigo)->value('id');
    }
}
