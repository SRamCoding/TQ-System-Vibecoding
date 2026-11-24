<?php

declare(strict_types=1);

namespace App\Modules\Crm\Licitaciones\Services;

use App\Modules\Nucleo\Ia\ClienteAnthropic;
use App\Modules\Nucleo\Ia\PedidoModeloIa;
use App\Modules\Nucleo\RegistroAcciones\EjecutorAcciones;
use App\Modules\Nucleo\Services\ParametrosService;
use App\Modules\Nucleo\Tiempo\Calendario;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Observación 9.6: el asistente de configuración inicial de la búsqueda del SEACE. Rubros (catálogo con palabras
 * sugeridas) → regiones → montos → vista previa sobre lo ya guardado del OECE → guardar (crea o reemplaza las líneas y
 * vuelve a filtrar). Las sugerencias de palabras salen de los proyectos ganados (IA si está; en la demo, ya hechas).
 */
final readonly class AsistenteBusquedaSeace
{
    public const string PERMISO_CONFIGURAR = 'crm.convocatorias.configurar-busqueda';

    /** @return list<array{nombre: string, palabras: list<string>, negativas: list<string>}> */
    public function rubros(): array
    {
        return DB::table('seace_rubros')->orderBy('orden')->orderBy('id')->get()->map(fn (object $r): array => [
            'nombre' => $r->nombre, 'palabras' => array_values((array) json_decode($r->palabras, true)), 'negativas' => array_values((array) json_decode($r->negativas, true)),
        ])->all();
    }

    /** «+ Otro»: un rubro nuevo en el catálogo (con sus palabras), para esta y las próximas veces. */
    public function agregarRubro(string $nombre, array $palabras): void
    {
        $nombre = mb_substr(trim($nombre), 0, 80);

        if ($nombre === '') {
            throw new RuntimeException('Escribe el nombre del rubro.');
        }

        DB::table('seace_rubros')->updateOrInsert(['nombre' => $nombre], ['palabras' => json_encode(array_values($palabras), JSON_UNESCAPED_UNICODE), 'negativas' => '[]', 'orden' => 100, 'created_at' => now(), 'updated_at' => now()]);
    }

    /**
     * El estado inicial del asistente: cada rubro del catálogo, marcado si ya hay una línea con su nombre (o parecido),
     * con lo que esa línea tiene; las líneas sin rubro van como rubros propios. `$desdeCero`: todo sin marcar.
     *
     * @return array{rubros: list<array<string, mixed>>, umbral: string}
     */
    public function estadoInicial(bool $desdeCero = false): array
    {
        $lineas = $desdeCero ? [] : app(FiltroConvocatorias::class)->lineas();
        $usadas = [];
        $rubros = [];

        foreach ($this->rubros() as $r) {
            $linea = collect($lineas)->first(fn (array $l): bool => ! in_array($l['id'], $usadas, true)
                && (FiltroConvocatorias::normal($l['nombre']) === FiltroConvocatorias::normal($r['nombre']) || str_starts_with(FiltroConvocatorias::normal($r['nombre']), FiltroConvocatorias::normal($l['nombre']))));

            if ($linea !== null) {
                $usadas[] = $linea['id'];
            }

            $rubros[] = ['rubro' => $r['nombre'], 'nombre' => $linea['nombre'] ?? $r['nombre'], 'id' => $linea['id'] ?? null, 'marcado' => $linea !== null,
                'palabras' => $linea['palabras'] ?? $r['palabras'], 'negativas' => $linea['negativas'] ?? $r['negativas'],
                'regiones' => $linea['regiones'] ?? [], 'minimo' => $linea === null ? '20000' : number_format((float) $linea['minimo'], 0, '.', ''), 'excluidas' => $linea['excluidas'] ?? []];
        }

        foreach ($lineas as $l) {
            if (! in_array($l['id'], $usadas, true)) {
                $rubros[] = ['rubro' => $l['nombre'], 'nombre' => $l['nombre'], 'id' => $l['id'], 'marcado' => true, 'palabras' => $l['palabras'], 'negativas' => $l['negativas'],
                    'regiones' => $l['regiones'], 'minimo' => number_format((float) $l['minimo'], 0, '.', ''), 'excluidas' => $l['excluidas']];
            }
        }

        return ['rubros' => $rubros, 'umbral' => (string) app(ParametrosService::class)->entero(ConvocatoriasSeaceService::PARAMETRO_PRIORIDAD)];
    }

    /**
     * La vista previa: sobre lo ya guardado del OECE del último mes, cuántas habrían entrado por línea, las 5 primeras
     * de ejemplo y una línea de ayuda si salen demasiadas o muy pocas.
     *
     * @param  list<array<string, mixed>>  $rubros
     * @return array{total: int, leidas: int, porLinea: array<string, int>, ejemplos: list<array{objeto: string, entidad: string, linea: string, coincide: list<string>}>, ayuda: ?string}
     */
    public function vistaPrevia(array $rubros): array
    {
        $lineas = [];

        foreach (array_values(array_filter($rubros, fn (array $r): bool => (bool) $r['marcado'])) as $i => $r) {
            $lineas[] = ['id' => -1 - $i, 'nombre' => $r['nombre'], 'palabras' => array_values($r['palabras']), 'negativas' => array_values($r['negativas']),
                'minimo' => number_format(max(0, (float) $r['minimo']), 2, '.', ''), 'regiones' => array_values($r['regiones']), 'excluidas' => array_values($r['excluidas'] ?? [])];
        }

        $desde = CarbonImmutable::parse(Calendario::hoy())->subMonth()->toDateString();
        $candidatas = DB::table('convocatorias_seace')->whereNotNull('texto')
            ->where(fn ($q) => $q->where('publicada', '>=', $desde)->orWhere(fn ($q2) => $q2->whereNull('publicada')->where('created_at', '>=', $desde)))
            ->orderByDesc('publicada')->get(['id', 'objeto', 'texto', 'entidad', 'ruc_entidad', 'valor_referencial', 'departamento']);
        $porLinea = array_fill_keys(array_column($lineas, 'nombre'), 0);
        $ejemplos = [];
        $total = 0;
        $filtro = app(FiltroConvocatorias::class);

        foreach ($candidatas as $c) {
            $r = $filtro->evaluar($c, $lineas);

            if (! $r['entra']) {
                continue;
            }

            $total++;
            $nombre = collect($lineas)->firstWhere('id', $r['linea'])['nombre'] ?? '';
            $porLinea[$nombre] = ($porLinea[$nombre] ?? 0) + 1;

            if (count($ejemplos) < 5) {
                $ejemplos[] = ['objeto' => (string) $c->objeto, 'entidad' => (string) $c->entidad, 'linea' => $nombre, 'coincide' => $r['coincide']];
            }
        }

        $ayuda = match (true) {
            $lineas === [] => 'Marca al menos un rubro.',
            $total === 0 => 'No habría entrado ninguna: agrega palabras, regiones o baja el monto mínimo.',
            $total < 3 => 'Salen muy pocas: prueba con más palabras o más regiones.',
            $total > 80 => 'Salen demasiadas: quita palabras muy generales, agrega negativas o sube el monto mínimo.',
            default => null,
        };

        return ['total' => $total, 'leidas' => $candidatas->count(), 'porLinea' => $porLinea, 'ejemplos' => $ejemplos, 'ayuda' => $ayuda];
    }

    /** Guardar: cada rubro marcado es una línea (crea o reemplaza las que había) y la bandeja se vuelve a filtrar. */
    public function guardar(array $rubros, string $umbral): void
    {
        $lineas = array_values(array_map(fn (array $r): array => ['id' => $r['id'], 'nombre' => $r['nombre'], 'palabras' => $r['palabras'], 'negativas' => $r['negativas'],
            'minimo' => $r['minimo'], 'regiones' => $r['regiones'], 'excluidas' => $r['excluidas'] ?? []], array_filter($rubros, fn (array $r): bool => (bool) $r['marcado'])));

        if ($lineas === []) {
            throw new RuntimeException('Marca al menos un rubro antes de guardar.');
        }

        app(ConvocatoriasSeaceService::class)->guardarLineas($lineas);
        $actual = (string) app(ParametrosService::class)->entero(ConvocatoriasSeaceService::PARAMETRO_PRIORIDAD);

        if (is_numeric($umbral) && (string) (int) $umbral !== $actual) {
            [$grupo, $clave] = explode('.', ConvocatoriasSeaceService::PARAMETRO_PRIORIDAD, 2);
            $version = DB::table('parametros')->where('grupo', $grupo)->where('clave', $clave)->value('version');
            app(EjecutorAcciones::class)->ejecutar('nucleo.cambiar-parametro', ['parametro' => ConvocatoriasSeaceService::PARAMETRO_PRIORIDAD, 'valor' => (string) (int) $umbral, 'version' => (int) $version], auth()->user());
            DB::table('convocatorias_seace')->pluck('id')->each(fn ($id) => app(ConvocatoriasSeaceService::class)->priorizar((int) $id));
            app(ConvocatoriasSeaceService::class)->registrarCambios([], [], 'cambió el umbral de prioritaria de S/ '.number_format((float) $actual, 0).' a S/ '.number_format((float) $umbral, 0));
        }
    }

    /** @return list<array{id: int, palabra: string, rubro: string, motivo: string}> las sugerencias por confirmar */
    public function sugerencias(): array
    {
        return DB::table('seace_sugerencias')->where('estado', 'pendiente')->orderBy('id')->get(['id', 'palabra', 'rubro', 'motivo'])
            ->map(fn (object $s): array => ['id' => (int) $s->id, 'palabra' => $s->palabra, 'rubro' => $s->rubro, 'motivo' => $s->motivo])->all();
    }

    public function resolverSugerencia(int $id, bool $aceptada): void
    {
        DB::table('seace_sugerencias')->where('id', $id)->update(['estado' => $aceptada ? 'aceptada' : 'descartada', 'updated_at' => now()]);
    }

    public function iaDisponible(): bool
    {
        return (string) (config('nucleo.ia.claves')['IA_CLAVE_ANTHROPIC'] ?? '') !== '';
    }

    /** «Sugerir palabras» con IA: mira los proyectos ganados y propone palabras o rubros que faltan (una persona confirma). */
    public function sugerirConIa(array $rubros): int
    {
        $clave = (string) (config('nucleo.ia.claves')['IA_CLAVE_ANTHROPIC'] ?? '');

        if ($clave === '') {
            throw new RuntimeException('La IA no está configurada (falta IA_CLAVE_ANTHROPIC en el .env).');
        }

        $ganados = DB::table('oportunidades as o')->join('estados as e', 'e.id', '=', 'o.estado_id')->where('e.codigo', 'ganada')->pluck('o.nombre')->implode("\n");
        $actuales = collect($rubros)->map(fn (array $r): string => "{$r['nombre']}: ".implode(', ', $r['palabras']))->implode("\n");
        $r = app(ClienteAnthropic::class)->llamar(new PedidoModeloIa(
            (string) config('nucleo.ia.modelo_asistente', 'claude-haiku-5-5'), 600,
            'Ayudas a configurar la búsqueda de convocatorias públicas del Perú (SEACE) de una empresa de tecnología. Con los proyectos que ganó y sus palabras actuales, propone hasta 5 palabras clave que faltan, cada una con su rubro y por qué (en español del Perú, corto). No repitas palabras que ya están.',
            "Proyectos ganados:\n{$ganados}\n\nRubros y palabras actuales:\n{$actuales}", [],
            ['type' => 'object', 'properties' => ['sugerencias' => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => ['palabra' => ['type' => 'string'], 'rubro' => ['type' => 'string'], 'motivo' => ['type' => 'string']], 'required' => ['palabra', 'rubro', 'motivo']]]], 'required' => ['sugerencias']], 40,
        ), $clave);
        $n = 0;

        foreach ((array) ($r->salida['sugerencias'] ?? []) as $s) {
            DB::table('seace_sugerencias')->insert(['palabra' => mb_substr(mb_strtolower((string) $s['palabra']), 0, 80), 'rubro' => mb_substr((string) $s['rubro'], 0, 80), 'motivo' => mb_substr((string) $s['motivo'], 0, 300), 'created_at' => now(), 'updated_at' => now()]);
            $n++;
        }

        return $n;
    }
}
