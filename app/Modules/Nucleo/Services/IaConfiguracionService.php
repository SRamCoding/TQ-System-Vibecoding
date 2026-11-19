<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Services;

use App\Modules\Nucleo\Excepciones\IaRechazada;
use App\Modules\Nucleo\Excepciones\VersionDesactualizada;
use App\Modules\Nucleo\Ia\ClientesModeloLenguaje;
use App\Modules\Nucleo\Ia\CostoLlamadaIa;
use App\Modules\Nucleo\Ia\DefinicionProcesoIa;
use App\Modules\Nucleo\Ia\RegistroProcesosIa;
use App\Modules\Nucleo\Models\IaModelo;
use App\Modules\Nucleo\Models\IaPrecioModelo;
use App\Modules\Nucleo\Models\IaProceso;
use App\Modules\Nucleo\Models\IaProcesoDestino;
use App\Modules\Nucleo\Models\IaProveedor;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Lo editable del mecanismo de IA (plan 1.11, D2 y D5): la autorización de Gerencia, los modelos de cada proceso, el
 * modelo por defecto y los datos de cada proveedor.
 *
 * Cada cambio que mueve adónde van los datos revisa, en la misma transacción, los procesos que toca: recalcula
 * `envia_datos_externos`, hace caer la autorización si el proceso declara enviar otra cosa o va a un proveedor externo
 * que Gerencia no autorizó (pasar a uno local no la hace caer), y apaga el que quedaría enviando sin autorización, en
 * vez de que el CHECK de la base haga fallar el cambio. El apagado queda en la bitácora del proceso.
 */
final readonly class IaConfiguracionService
{
    public function __construct(private RegistroProcesosIa $registro, private ClientesModeloLenguaje $clientes) {}

    /** Cada proceso declarado con su fila editable: apagado, sin modelo y con tope 0 (D1, D3). Revisa los que existen. */
    public function sincronizar(): void
    {
        foreach ($this->registro->procesos() as $definicion) {
            $this->registro->revisar($definicion);

            DB::transaction(function () use ($definicion): void {
                IaProceso::query()->createOrFirst(['codigo' => $definicion->clave]);
                $this->revisar(IaProceso::query()->where('codigo', $definicion->clave)->lockForUpdate()->firstOrFail());
            });
        }
    }

    /**
     * Gerencia autoriza lo que el proceso declara enviar hoy, a los proveedores externos que usa hoy, y fija su tope del
     * mes en dólares (D2, D3; 0 = no llama).
     */
    public function autorizar(int $procesoId, string $topeMensualUsd, int $versionLeida): void
    {
        if (bccomp($topeMensualUsd, '0', 2) < 0) {
            throw IaRechazada::topeNegativo();
        }

        DB::transaction(function () use ($procesoId, $topeMensualUsd, $versionLeida): void {
            $proceso = $this->procesoParaCambiar($procesoId, $versionLeida);
            $huella = $this->registro->proceso($proceso->codigo)->huella();
            // Una autorización nueva reemplaza a la anterior: sus destinos primero (llevan la huella vieja).
            $proceso->destinos()->delete();
            $proceso->fill(['autorizado_por' => app(ContextoAuditoria::class)->idActor(null), 'autorizado_at' => now(), 'autorizacion_huella' => $huella, 'tope_mensual_usd' => $topeMensualUsd]);
            $this->guardar($proceso);

            foreach ($this->destinosExternos($proceso) as $proveedorId) {
                IaProcesoDestino::query()->create(['proceso_id' => $proceso->id, 'proveedor_id' => $proveedorId, 'autorizacion_huella' => $huella]);
            }

            $this->revisar($proceso);
        });
    }

    /**
     * Lo que configura el administrador en un proceso (D5): su modelo (nulo: el por defecto), el de respaldo y si está
     * encendido. Si cambiar los modelos mueve los datos a otro destino, la autorización cae; un proceso que envía datos
     * fuera no se enciende sin autorización vigente (D2).
     */
    public function cambiarProceso(int $procesoId, bool $activo, ?int $modeloId, ?int $respaldoId, int $versionLeida): void
    {
        if ($respaldoId !== null && $respaldoId === $modeloId) {
            throw IaRechazada::respaldoIgual();
        }

        DB::transaction(function () use ($procesoId, $activo, $modeloId, $respaldoId, $versionLeida): void {
            $proceso = $this->procesoParaCambiar($procesoId, $versionLeida);
            $this->revisarModelosAsignables([$modeloId, $respaldoId]);
            $proceso->fill(['modelo_id' => $modeloId, 'modelo_respaldo_id' => $respaldoId]);
            $this->revisar($proceso);

            if ($activo && $proceso->envia_datos_externos && ! $proceso->estaAutorizado()) {
                throw IaRechazada::encenderSinAutorizacion();
            }

            if ($proceso->esta_activo !== $activo) {
                $proceso->esta_activo = $activo;
                $this->guardar($proceso);
            }
        });
    }

    /** El modelo del proceso (nulo: el por defecto) y el de respaldo (D5). */
    public function asignarModelos(int $procesoId, ?int $modeloId, ?int $respaldoId, int $versionLeida): void
    {
        if ($respaldoId !== null && $respaldoId === $modeloId) {
            throw IaRechazada::respaldoIgual();
        }

        DB::transaction(function () use ($procesoId, $modeloId, $respaldoId, $versionLeida): void {
            $proceso = $this->procesoParaCambiar($procesoId, $versionLeida);

            $this->revisarModelosAsignables([$modeloId, $respaldoId]);

            $proceso->fill(['modelo_id' => $modeloId, 'modelo_respaldo_id' => $respaldoId]);
            $this->revisar($proceso);
        });
    }

    /** El modelo por defecto del sistema: cambia el destino de todos los procesos sin modelo propio. */
    public function marcarPorDefecto(int $modeloId, int $versionLeida): void
    {
        DB::transaction(function () use ($modeloId, $versionLeida): void {
            $modelo = IaModelo::query()->lockForUpdate()->findOrFail($modeloId);

            if ($modelo->version !== $versionLeida) {
                throw VersionDesactualizada::de("el modelo «{$modelo->nombre}»");
            }

            if (! $modelo->es_activo) {
                throw IaRechazada::modeloInactivo($modelo->nombre);
            }

            $this->exigirPrecio($modelo);

            foreach (IaModelo::query()->where('es_por_defecto', true)->whereKeyNot($modelo->id)->lockForUpdate()->get() as $anterior) {
                $anterior->es_por_defecto = false;
                $anterior->version++;
                $anterior->save();
            }

            $modelo->es_por_defecto = true;
            $modelo->version++;
            $modelo->save();

            $this->revisarTodos(IaProceso::query()->whereNull('modelo_id'));
        });
    }

    /**
     * Los datos de un proveedor. Si pasa a sacar los datos de la empresa, revisa los procesos que lo usan. Si cambia su
     * implementación o su variable de clave, el mismo proveedor pasaría a ser otro servicio u otra cuenta: cae la
     * autorización de los procesos que lo tienen como destino (D2, ajuste del 2026-10-28).
     */
    public function editarProveedor(int $proveedorId, string $nombre, string $implementacion, bool $esExterno, ?string $variableClave, int $llamadasEnParalelo, int $versionLeida, ?bool $esActivo = null): void
    {
        if ($variableClave !== null && preg_match(IaProveedor::PATRON_VARIABLE_CLAVE, $variableClave) !== 1) {
            throw IaRechazada::variableClave($variableClave);
        }

        if (! $this->clientes->existe($implementacion)) {
            throw IaRechazada::implementacionDesconocida($implementacion, $this->clientes->codigos());
        }

        DB::transaction(function () use ($proveedorId, $nombre, $implementacion, $esExterno, $variableClave, $llamadasEnParalelo, $versionLeida, $esActivo): void {
            $proveedor = IaProveedor::query()->lockForUpdate()->findOrFail($proveedorId);

            if ($proveedor->version !== $versionLeida) {
                throw VersionDesactualizada::de("el proveedor «{$proveedor->nombre}»");
            }

            $proveedor->fill([
                'nombre' => $nombre,
                'implementacion' => $implementacion,
                'es_externo' => $esExterno,
                'variable_clave' => $variableClave,
                'llamadas_en_paralelo' => $llamadasEnParalelo,
                'es_activo' => $esActivo ?? $proveedor->es_activo,
            ]);
            $cambiaCuenta = $proveedor->isDirty(['implementacion', 'variable_clave']);
            $proveedor->version++;
            $proveedor->save();

            $this->revisarTodos(IaProceso::query(), $cambiaCuenta ? [$proveedor->id] : []);
        });
    }

    /** Un proveedor nuevo del catálogo (D5): con su implementación registrada y, si la necesita, su variable de clave. */
    public function crearProveedor(string $codigo, string $nombre, string $implementacion, bool $esExterno, ?string $variableClave, int $llamadasEnParalelo): IaProveedor
    {
        if ($variableClave !== null && preg_match(IaProveedor::PATRON_VARIABLE_CLAVE, $variableClave) !== 1) {
            throw IaRechazada::variableClave($variableClave);
        }

        if (! $this->clientes->existe($implementacion)) {
            throw IaRechazada::implementacionDesconocida($implementacion, $this->clientes->codigos());
        }

        return IaProveedor::query()->create([
            'codigo' => $codigo,
            'nombre' => $nombre,
            'implementacion' => $implementacion,
            'es_externo' => $esExterno,
            'variable_clave' => $variableClave,
            'llamadas_en_paralelo' => $llamadasEnParalelo,
        ]);
    }

    /** Un modelo nuevo de un proveedor (D5). Se asigna recién con su precio vigente. */
    public function crearModelo(int $proveedorId, string $codigo, string $nombre, int $maxTokensSalida): IaModelo
    {
        return IaModelo::query()->create([
            'proveedor_id' => IaProveedor::query()->findOrFail($proveedorId)->id,
            'codigo' => $codigo,
            'nombre' => $nombre,
            'max_tokens_salida' => $maxTokensSalida,
        ]);
    }

    /** Nombre, tokens de salida y si está activo. El modelo por defecto no se desactiva. */
    public function editarModelo(int $modeloId, string $nombre, int $maxTokensSalida, bool $esActivo, int $versionLeida): void
    {
        DB::transaction(function () use ($modeloId, $nombre, $maxTokensSalida, $esActivo, $versionLeida): void {
            $modelo = IaModelo::query()->lockForUpdate()->findOrFail($modeloId);

            if ($modelo->version !== $versionLeida) {
                throw VersionDesactualizada::de("el modelo «{$modelo->nombre}»");
            }

            if (! $esActivo && $modelo->es_por_defecto) {
                throw IaRechazada::defectoNoSeDesactiva($modelo->nombre);
            }

            $modelo->fill(['nombre' => $nombre, 'max_tokens_salida' => $maxTokensSalida, 'es_activo' => $esActivo]);
            $modelo->version++;
            $modelo->save();
        });
    }

    /**
     * Un precio nuevo, desde hoy o después (D3): el de antes queda para las llamadas ya hechas, que no se recalculan.
     * Por millón de tokens, en dólares; un modelo propio, 0.
     */
    public function agregarPrecio(int $modeloId, string $vigenteDesde, string $usdMillonEntrada, string $usdMillonSalida): IaPrecioModelo
    {
        if (bccomp($usdMillonEntrada, '0', 6) < 0 || bccomp($usdMillonSalida, '0', 6) < 0) {
            throw IaRechazada::precioNegativo();
        }

        $desde = CarbonImmutable::parse($vigenteDesde, 'America/Lima')->startOfDay();

        if ($desde->lt(CarbonImmutable::now('America/Lima')->startOfDay())) {
            throw IaRechazada::precioPasado();
        }

        $modelo = IaModelo::query()->findOrFail($modeloId);

        if (IaPrecioModelo::query()->where('modelo_id', $modelo->id)->where('vigente_desde', $desde->toDateString())->exists()) {
            throw IaRechazada::precioRepetido($desde->format('d/m/Y'));
        }

        return IaPrecioModelo::query()->create([
            'modelo_id' => $modelo->id,
            'vigente_desde' => $desde->toDateString(),
            'usd_millon_entrada' => $usdMillonEntrada,
            'usd_millon_salida' => $usdMillonSalida,
        ]);
    }

    /**
     * Los proveedores externos adonde irían hoy los datos del proceso: el de su modelo (o el por defecto) y el de su
     * respaldo. Ids, ordenados.
     *
     * @return list<int>
     */
    public function destinosExternos(IaProceso $proceso): array
    {
        $modelos = array_filter([
            $proceso->modelo_id ?? IaModelo::query()->where('es_por_defecto', true)->value('id'),
            $proceso->modelo_respaldo_id,
        ]);

        $ids = IaProveedor::query()
            ->where('es_externo', true)
            ->whereIn('id', IaModelo::query()->whereIn('id', $modelos)->select('proveedor_id'))
            ->orderBy('id')
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();

        return array_values($ids);
    }

    /**
     * Solo se asigna un modelo activo y con precio vigente.
     *
     * @param  list<int|null>  $ids
     */
    private function revisarModelosAsignables(array $ids): void
    {
        foreach (array_filter($ids) as $id) {
            $modelo = IaModelo::query()->findOrFail($id);

            if (! $modelo->es_activo) {
                throw IaRechazada::modeloInactivo($modelo->nombre);
            }

            $this->exigirPrecio($modelo);
        }
    }

    /** Sin precio vigente no hay llamada (ajuste del 2026-10-28): tampoco se asigna. */
    private function exigirPrecio(IaModelo $modelo): void
    {
        if (CostoLlamadaIa::precioVigente($modelo->id, CarbonImmutable::now()) === null) {
            throw IaRechazada::sinPrecio($modelo->nombre);
        }
    }

    /**
     * Si lo que el proceso envía hoy, y adónde, es lo que Gerencia autorizó (D2). Sin datos que salgan, siempre. Se
     * revisa antes de cada llamada: la autorización pudo caer por un cambio que no pasó por este servicio (otro
     * código).
     */
    public function autorizacionVigente(IaProceso $proceso): bool
    {
        $destinos = $this->destinosExternos($proceso);

        if ($destinos === []) {
            return true;
        }

        return $proceso->estaAutorizado() && ! $this->autorizacionCae($proceso, $this->registro->procesos()[$proceso->codigo] ?? null, $destinos, []);
    }

    /**
     * Recalcula y guarda (D2): envía o no fuera, si la autorización sigue valiendo y si puede seguir encendido. Va
     * dentro de la transacción del cambio que lo provocó.
     */
    /** @param list<int> $proveedoresCambiados  proveedores que pasaron a ser otro servicio u otra cuenta */
    private function revisar(IaProceso $proceso, array $proveedoresCambiados = []): void
    {
        $destinos = $this->destinosExternos($proceso);
        $definicion = $this->registro->procesos()[$proceso->codigo] ?? null;
        $proceso->envia_datos_externos = $destinos !== [];

        if ($proceso->estaAutorizado() && $this->autorizacionCae($proceso, $definicion, $destinos, $proveedoresCambiados)) {
            // Los destinos primero: llevan la huella que se quita.
            $proceso->destinos()->delete();
            $proceso->fill(['autorizado_por' => null, 'autorizado_at' => null, 'autorizacion_huella' => null]);
            Log::info('Cayó la autorización de un proceso de IA: cambió lo que envía, adónde o con qué cuenta.', ['proceso' => $proceso->codigo]);
        }

        if ($proceso->esta_activo && $proceso->envia_datos_externos && ! $proceso->estaAutorizado()) {
            $proceso->esta_activo = false;
            Log::warning('Se apagó un proceso de IA: enviaría datos fuera sin autorización.', ['proceso' => $proceso->codigo]);
        }

        if ($proceso->isDirty()) {
            $this->guardar($proceso);
        }
    }

    /**
     * @param  list<int>  $destinos
     * @param  list<int>  $proveedoresCambiados
     */
    private function autorizacionCae(IaProceso $proceso, ?DefinicionProcesoIa $definicion, array $destinos, array $proveedoresCambiados): bool
    {
        if ($definicion !== null && $definicion->huella() !== $proceso->autorizacion_huella) {
            return true;
        }

        $autorizados = $proceso->destinos()->pluck('proveedor_id')->map(fn (mixed $id): int => (int) $id)->all();

        return array_diff($destinos, $autorizados) !== [] || array_intersect($autorizados, $proveedoresCambiados) !== [];
    }

    /**
     * @param  Builder<IaProceso>  $consulta
     * @param  list<int>  $proveedoresCambiados
     */
    private function revisarTodos(Builder $consulta, array $proveedoresCambiados = []): void
    {
        foreach ($consulta->orderBy('id')->lockForUpdate()->get() as $proceso) {
            $this->revisar($proceso, $proveedoresCambiados);
        }
    }

    private function procesoParaCambiar(int $procesoId, int $versionLeida): IaProceso
    {
        $proceso = IaProceso::query()->lockForUpdate()->findOrFail($procesoId);

        if ($proceso->version !== $versionLeida) {
            throw VersionDesactualizada::de("el proceso de IA «{$proceso->codigo}»");
        }

        return $proceso;
    }

    private function guardar(IaProceso $proceso): void
    {
        $proceso->version++;
        $proceso->save();
    }
}
