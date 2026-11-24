<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Desarrollo;

use App\Modules\Nucleo\Enums\Catalogo;
use App\Modules\Nucleo\Services\EmpresaService;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Observación 9.8 (MV-102), la condición de Sebastián: la configuración es la misma en las dos bases (parámetros, la
 * búsqueda del SEACE, catálogos, usuarios, permisos, preferencias); lo que se cambia en una se copia a la otra. Los
 * datos de trabajo (oportunidades, proyectos, gastos…) quedan separados. La base limpia nace de la de demo con solo su
 * configuración y lo leído del OECE (las convocatorias reales).
 *
 * Atajo del MVP (solo local): la copia apaga las claves foráneas de la sesión (`session_replication_role = replica`,
 * hace falta un usuario de Postgres con permiso), reemplaza cada tabla de configuración entera y vuelve a poner sus
 * secuencias; se dispara al final de cada petición que cambia algo, solo para las tablas que cambiaron.
 */
final class BasesDeDatos
{
    /** Las tablas de configuración (además de los catálogos y de las personas de los usuarios y la empresa). */
    public const array CONFIGURACION = [
        'users', 'unidades_organizativas', 'puestos', 'roles', 'permissions', 'model_has_roles', 'model_has_permissions', 'role_has_permissions',
        'permisos_por_ambito', 'incompatibilidades_permisos', 'parametros', 'preferencias_usuario', 'estado_interfaz_usuario',
        'aplicaciones_en_escritorio', 'aplicaciones_ocultas', 'aplicaciones_orden', 'aplicaciones_principales', 'aplicaciones_propias',
        'tipos_motivo', 'motivos', 'estados', 'transiciones_estado', 'reglas_aprobacion', 'pasos_regla_aprobacion', 'alerta_tipo_roles',
        'configuracion_pasos', 'controles_cruce', 'seace_lineas', 'seace_rubros', 'seace_sugerencias', 'seace_busqueda_cambios',
    ];

    /** Lo leído del OECE: es real, va también a la base limpia. */
    public const array DEL_OECE = ['seace_lecturas', 'seace_cargas'];

    /** @return list<string> */
    public static function tablas(): array
    {
        $catalogos = array_map(fn (Catalogo $c): string => (new ($c->modelo()))->getTable(), Catalogo::cases());

        return array_values(array_unique([...$catalogos, ...self::CONFIGURACION]));
    }

    /** Crea (o rehace) la base limpia: migra, copia toda la configuración y lo real del OECE. */
    public function crearLimpia(): string
    {
        $nombre = (string) config('database.connections.'.DatosDemo::CONEXION_LIMPIA.'.database');
        $demo = DB::connection(DatosDemo::CONEXION_DEMO);

        if ($demo->selectOne('SELECT 1 AS si FROM pg_database WHERE datname = ?', [$nombre]) === null) {
            $demo->statement('CREATE DATABASE "'.str_replace('"', '', $nombre).'"');
        }

        Artisan::call('migrate:fresh', ['--database' => DatosDemo::CONEXION_LIMPIA, '--force' => true]);
        DB::purge(DatosDemo::CONEXION_LIMPIA);
        $limpia = DB::connection(DatosDemo::CONEXION_LIMPIA);
        // Lo que las migraciones siembran solas (configuración de fábrica) se reemplaza por lo de la base de demo.
        $existentes = array_map(fn ($t): string => str_replace('public.', '', (string) $t), $limpia->getSchemaBuilder()->getTableListing());

        foreach (self::tablas() as $t) {
            if (in_array($t, $existentes, true)) {
                $this->copiar($demo, $limpia, $t);
            }
        }

        $this->copiarPersonas($demo, $limpia);
        $this->copiarOece($demo, $limpia);
        $this->tomarContadores(DatosDemo::CONEXION_DEMO);
        $this->tomarContadores(DatosDemo::CONEXION_LIMPIA);

        return $nombre;
    }

    /**
     * Al final de una petición que cambió algo: las tablas de configuración que cambiaron en la base en uso se copian a
     * la otra. Se sabe qué cambió por la huella del contenido de cada tabla.
     */
    public function sincronizarCambios(string $desde): void
    {
        if (! DatosDemo::limpiaLista()) {
            return;
        }

        $hacia = $desde === DatosDemo::CONEXION_DEMO ? DatosDemo::CONEXION_LIMPIA : DatosDemo::CONEXION_DEMO;
        $origen = DB::connection($desde);
        $destino = DB::connection($hacia);
        $clave = "nucleo.bases.contadores.{$desde}";
        $ahora = $this->contadores($origen);
        $antes = Cache::store('file')->get($clave);
        Cache::store('file')->forever($clave, $ahora);

        if (! is_array($antes)) {
            return;
        }

        foreach ($ahora as $tabla => $n) {
            if (($antes[$tabla] ?? null) === $n) {
                continue;
            }

            try {
                $tabla === 'parties' ? $this->copiarPersonas($origen, $destino) : $this->copiar($origen, $destino, $tabla);
            } catch (Throwable $e) {
                Log::warning("No se pudo copiar la configuración «{$tabla}» a la otra base: ".$e->getMessage());
            }
        }

        // Lo copiado también movió los contadores de la otra base: que no se copie de vuelta.
        $this->tomarContadores($hacia);
    }

    /** Reemplaza una tabla entera de la otra base con la de esta, con sus mismos ids. */
    private function copiar(Connection $origen, Connection $destino, string $tabla): void
    {
        $filas = $origen->table($tabla)->get()->map(fn (object $f): array => (array) $f)->all();

        $destino->transaction(function () use ($destino, $tabla, $filas): void {
            $destino->statement('SET LOCAL session_replication_role = replica');
            $destino->table($tabla)->delete();

            foreach (array_chunk($filas, 500) as $lote) {
                $destino->table($tabla)->insert($lote);
            }

            $this->reponerSecuencia($destino, $tabla);
        });
    }

    /** Las personas de los usuarios y la ficha de la empresa (por id; el resto de las personas es dato de trabajo). */
    private function copiarPersonas(Connection $origen, Connection $destino): void
    {
        [$grupo, $clave] = explode('.', EmpresaService::PARAMETRO, 2);
        $ficha = (string) $origen->table('parametros')->where('grupo', $grupo)->where('clave', $clave)->value('valor');
        $empresa = ctype_digit($ficha) && strlen($ficha) < 11 ? (int) $ficha : (int) $origen->table('parties')->where('numero_documento', $ficha)->value('id');
        $ids = array_values(array_filter([...$origen->table('users')->pluck('party_id')->all(), $empresa]));
        $filas = $origen->table('parties')->whereIn('id', $ids ?: [0])->get()->map(fn (object $f): array => (array) $f)->all();

        if ($filas === []) {
            return;
        }

        $destino->transaction(function () use ($origen, $destino, $filas): void {
            $destino->statement('SET LOCAL session_replication_role = replica');
            $destino->table('parties')->upsert($filas, ['id'], array_keys($filas[0]));
            $this->reponerSecuencia($destino, 'parties');
            // Las sedes de esas personas (la empresa registra sus gastos por sede).
            $sedes = DB::connection($origen->getName())->table('party_sedes')->whereIn('party_id', array_column($filas, 'id'))->get()->map(fn (object $f): array => (array) $f)->all();

            if ($sedes !== []) {
                $destino->table('party_sedes')->upsert($sedes, ['id'], array_keys($sedes[0]));
                $this->reponerSecuencia($destino, 'party_sedes');
            }
        });
    }

    /** Las convocatorias reales del OECE (nunca las de demo) y el registro de sus lecturas y cargas. */
    private function copiarOece(Connection $origen, Connection $destino): void
    {
        $destino->transaction(function () use ($origen, $destino): void {
            $destino->statement('SET LOCAL session_replication_role = replica');

            foreach (self::DEL_OECE as $t) {
                $this->copiarDentro($origen, $destino, $t);
            }

            $origen->table('convocatorias_seace')->where('es_demo', false)->orderBy('id')
                ->chunk(500, fn ($lote) => $destino->table('convocatorias_seace')->insert($lote->map(fn (object $f): array => [...(array) $f, 'licitacion_id' => null, 'responsable_user_id' => null, 'estado' => $f->estado === 'registrada' ? 'nueva' : $f->estado])->all()));
            $this->reponerSecuencia($destino, 'convocatorias_seace');
        });
    }

    private function copiarDentro(Connection $origen, Connection $destino, string $tabla): void
    {
        foreach (array_chunk($origen->table($tabla)->get()->map(fn (object $f): array => (array) $f)->all(), 500) as $lote) {
            $destino->table($tabla)->insert($lote);
        }

        $this->reponerSecuencia($destino, $tabla);
    }

    private function reponerSecuencia(Connection $c, string $tabla): void
    {
        if (! $c->getSchemaBuilder()->hasColumn($tabla, 'id')) {
            return;
        }

        $secuencia = $c->selectOne("SELECT pg_get_serial_sequence(?, 'id') AS s", [$tabla])?->s;

        if ($secuencia !== null) {
            $c->statement("SELECT setval(?, GREATEST((SELECT COALESCE(MAX(id), 0) FROM \"{$tabla}\"), 1))", [$secuencia]);
        }
    }

    /**
     * La huella del contenido de cada tabla de configuración (una sola consulta): si cambia, la tabla cambió.
     *
     * @return array<string, string>
     */
    private function contadores(Connection $c): array
    {
        $existentes = array_map(fn ($t): string => str_replace('public.', '', (string) $t), $c->getSchemaBuilder()->getTableListing());
        $partes = array_map(fn (string $t): string => "SELECT '{$t}' AS t, md5(coalesce(string_agg(x::text, chr(10) ORDER BY x::text), '')) AS h FROM \"{$t}\" x",
            array_values(array_intersect([...self::tablas(), 'parties'], $existentes)));

        return collect($c->select(implode(' UNION ALL ', $partes)))->mapWithKeys(fn (object $r): array => [$r->t => (string) $r->h])->all();
    }

    private function tomarContadores(string $conexion): void
    {
        Cache::store('file')->forever("nucleo.bases.contadores.{$conexion}", $this->contadores(DB::connection($conexion)));
    }
}
