<?php

declare(strict_types=1);

namespace App\Modules\Crm\Licitaciones\Services;

use App\Modules\Crm\Cuentas\Services\CuentasService;
use App\Modules\Crm\Cuentas\Services\DatosCuenta;
use App\Modules\Crm\Licitaciones\Enums\TipoProcedimiento;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Services\ParametrosService;
use App\Modules\Nucleo\Tiempo\Calendario;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * MVP rápido (ficha t1-licitaciones v2): trae las convocatorias del SEACE desde los datos abiertos del OECE (estándar
 * de contrataciones abiertas OCDS: paquetes de «releases»), se queda con las que coinciden con las palabras clave de
 * Techquk y su rango de valor, y las deja en la bandeja. «Registrar» la vuelve licitación (creando la cuenta de la
 * entidad si no existe) sin escribir nada.
 */
final readonly class ConvocatoriasSeaceService
{
    /** MV-066: cuántas páginas de la API del OECE se leen como máximo en una lectura. */
    public const int PAGINAS_MAXIMAS = 20;

    public const string PARAMETRO_PALABRAS = 'crm.seace_palabras_clave';

    public const string PARAMETRO_VALOR_MINIMO = 'crm.seace_valor_minimo';

    /** @return list<string> */
    public function palabras(): array
    {
        return array_values(array_filter(array_map(fn (string $p): string => mb_strtolower(trim($p)), explode(',', app(ParametrosService::class)->texto(self::PARAMETRO_PALABRAS)))));
    }

    /** Lee de la dirección configurada (OECE_OCDS_URL) los releases publicados desde la fecha dada. */
    public function sincronizar(?CarbonImmutable $desde = null): array
    {
        $url = (string) config('services.oece.ocds_url');

        if ($url === '') {
            throw new RuntimeException('Falta la dirección de los datos abiertos del OECE (OECE_OCDS_URL en el .env). Mientras tanto, importa un archivo OCDS descargado del portal.');
        }

        // MV-066: la API del OECE (`/releases`) no acepta «since»: entrega lo más reciente primero, 20 por página, y se
        // siguen sus páginas (`links.next`) mientras traigan releases más nuevos que «desde», hasta 20 páginas.
        $desde ??= Calendario::ahora()->subDays(3);
        // `releasesAfter` ordena por su id interno (salen releases de 2024 primero): se usa `/releases`, que sí va por fecha.
        $url = (string) preg_replace('#/releasesAfter(?=$|\?)#', '/releases', $url);
        $siguiente = $url.(str_contains($url, '?') ? '&' : '?').'format=json';
        $total = ['leidas' => 0, 'nuevas' => 0, 'descartadas_por_filtro' => 0];

        for ($pagina = 0; $pagina < self::PAGINAS_MAXIMAS && $siguiente !== null; $pagina++) {
            try {
                // La dirección va completa: si se pasaran parámetros aparte, Guzzle reemplazaría los que ya trae.
                // El OECE rechaza (403) la cabecera por defecto de Guzzle: el cliente se presenta con un nombre genérico.
                $json = Http::timeout(60)->acceptJson()->withUserAgent('TechQuk-OCDS/1.0')->get($siguiente)->throw()->body();
            } catch (Throwable $e) {
                if ($pagina === 0) {
                    throw new RuntimeException('El OECE no respondió: '.mb_substr($e->getMessage(), 0, 120));
                }

                break;
            }

            $datos = json_decode($json, true);
            $releases = is_array($datos) ? self::releasesDe($datos) : [];
            $recientes = array_values(array_filter($releases, fn (array $r): bool => ! isset($r['date']) || CarbonImmutable::parse($r['date'])->greaterThanOrEqualTo($desde)));
            $parte = $this->importar(json_encode(['releases' => $recientes]) ?: '{}');

            foreach ($total as $k => $v) {
                $total[$k] = $v + $parte[$k];
            }

            // Si en esta página ya hubo releases más viejos que «desde», no hace falta seguir.
            $siguiente = count($recientes) < count($releases) ? null : ($datos['links']['next'] ?? null);
        }

        return $total;
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
     * Importa un paquete OCDS (release package, record package o una lista de releases).
     *
     * @return array{leidas: int, nuevas: int, descartadas_por_filtro: int}
     */
    public function importar(string $json): array
    {
        $datos = json_decode($json, true);

        if (! is_array($datos)) {
            throw new RuntimeException('El archivo no es un JSON de contrataciones abiertas (OCDS).');
        }

        $releases = self::releasesDe($datos);
        $palabras = $this->palabras();
        $minimo = (float) app(ParametrosService::class)->entero(self::PARAMETRO_VALOR_MINIMO);
        [$leidas, $nuevas, $fuera] = [0, 0, 0];

        foreach ($releases as $r) {
            $leidas++;
            $t = $r['tender'] ?? [];
            $texto = mb_strtolower(($t['title'] ?? '').' '.($t['description'] ?? '').' '.implode(' ', array_map(fn ($i): string => (string) ($i['description'] ?? ''), $t['items'] ?? [])));
            $coinciden = array_values(array_filter($palabras, fn (string $p): bool => str_contains($texto, $p)));
            $valor = $t['value']['amount'] ?? null;
            $valor = $valor !== null && (float) $valor > 0 ? $valor : null; // el OECE manda 0 cuando el valor no es público

            if ($coinciden === [] || ($valor !== null && (float) $valor < $minimo) || ! isset($r['ocid'])) {
                $fuera++;

                continue;
            }

            $comprador = collect($r['parties'] ?? [])->firstWhere('id', $r['buyer']['id'] ?? null) ?? [];
            $ruc = preg_replace('/\D/', '', (string) ($comprador['identifier']['id'] ?? ''));
            $fecha = fn (?string $f): ?string => $f === null ? null : substr($f, 0, 10);

            $hecho = DB::table('convocatorias_seace')->insertOrIgnore([
                'ocid' => $r['ocid'], 'nomenclatura' => mb_substr((string) ($t['id'] ?? $r['ocid']), 0, 120), 'entidad' => mb_substr((string) ($r['buyer']['name'] ?? $comprador['name'] ?? 'Entidad'), 0, 250),
                'ruc_entidad' => strlen($ruc) === 11 ? $ruc : null, 'region' => $comprador['address']['region'] ?? null,
                'objeto' => mb_substr((string) ($t['title'] ?? $t['description'] ?? ''), 0, 600), 'tipo' => mb_substr((string) ($t['procurementMethodDetails'] ?? 'Por definir'), 0, 30),
                'valor_referencial' => $valor, 'moneda' => $t['value']['currency'] ?? 'PEN', 'publicada' => $fecha($r['date'] ?? null),
                'presentacion' => $fecha($t['tenderPeriod']['endDate'] ?? null), 'buena_pro' => $fecha($t['awardPeriod']['startDate'] ?? null),
                'url' => mb_substr((string) (collect($t['documents'] ?? [])->first()['url'] ?? ''), 0, 500) ?: null,
                'coincide' => mb_substr(implode(', ', $coinciden), 0, 200), 'estado' => 'nueva', 'created_at' => now(), 'updated_at' => now(),
            ]);
            $nuevas += $hecho;
        }

        return ['leidas' => $leidas, 'nuevas' => $nuevas, 'descartadas_por_filtro' => $fuera];
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
            $l = app(LicitacionesService::class)->registrar($cuentaId, $c->nomenclatura, $tipo, $c->objeto, null, $c->valor_referencial === null ? null : (string) $c->valor_referencial, $publicada, $c->url, $cronograma, $quien);
            DB::table('convocatorias_seace')->where('id', $id)->update(['estado' => 'registrada', 'licitacion_id' => $l->id, 'updated_at' => now()]);

            return $l->id;
        });
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
