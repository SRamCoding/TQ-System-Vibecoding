<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Services;

use App\Modules\Nucleo\Estados\DefinicionDocumento;
use App\Modules\Nucleo\Estados\EstadoDefinido;
use App\Modules\Nucleo\Estados\MotivoInicial;
use App\Modules\Nucleo\Estados\RegistroDocumentos;
use App\Modules\Nucleo\Excepciones\SincronizacionEstadosRechazada;
use App\Modules\Nucleo\Models\EfectoAplicado;
use App\Modules\Nucleo\Models\Estado;
use App\Modules\Nucleo\Models\HistorialEstado;
use App\Modules\Nucleo\Models\Motivo;
use App\Modules\Nucleo\Models\TipoMotivo;
use App\Modules\Nucleo\Models\TransicionEstado;
use Illuminate\Support\Facades\DB;

/**
 * Copia las definiciones de RegistroDocumentos a `estados`, `transiciones_estado` y las listas de motivos (plan 1.5,
 * D1, D2 y D9). Corre en cada despliegue y se puede repetir: solo escribe lo que cambió. De un estado ya creado no
 * pisa nombre ni color, que se editan por pantalla. Lo que sale de la definición se desactiva, nunca se borra. Si
 * la definición choca con lo que ya hay en la base, falla sin cambiar nada.
 */
final readonly class SincronizadorEstados
{
    public function __construct(private RegistroDocumentos $registro) {}

    public function sincronizar(): void
    {
        DB::transaction(function (): void {
            foreach ($this->registro->familias() as $codigo => $nombre) {
                $this->crearIniciales($this->asegurarFamilia($codigo, $nombre), $this->registro->motivosIniciales($codigo));
            }

            $this->revisarRegistros();

            foreach ($this->registro->definiciones() as $definicion) {
                $this->revisarEfectos($definicion);
                $this->sincronizarEstados($definicion);
                $this->sincronizarTransiciones($definicion);
            }
        });
    }

    /**
     * Una revisión antes de anular para un documento que nadie definió, o un efecto aplicado y vivo cuya clave ya no
     * está registrada: anular el documento no podría revertirlo (D3 y D4).
     */
    private function revisarRegistros(): void
    {
        foreach ($this->registro->documentosConRevisiones() as $clave) {
            if (! isset($this->registro->definiciones()[$clave])) {
                throw SincronizacionEstadosRechazada::revisionSinDocumento($clave);
            }
        }

        $vivas = EfectoAplicado::query()->whereNull('revertido_por_historial_id')->distinct()->pluck('clave');

        foreach ($vivas as $clave) {
            if ($this->registro->claseDeEfecto($clave) === null) {
                throw SincronizacionEstadosRechazada::efectoVivoSinRegistro($clave);
            }
        }
    }

    private function revisarEfectos(DefinicionDocumento $definicion): void
    {
        if ($definicion->tipo() === $definicion->modelo()) {
            throw SincronizacionEstadosRechazada::sinAlias($definicion->modelo());
        }

        foreach ($definicion->transiciones() as $transicion) {
            foreach ($transicion->efectos as $clave) {
                if ($this->registro->claseDeEfecto($clave) === null) {
                    throw SincronizacionEstadosRechazada::efectoNoRegistrado($clave, $transicion->nombre, $definicion->nombre());
                }
            }
        }
    }

    private function sincronizarEstados(DefinicionDocumento $definicion): void
    {
        $existentes = Estado::query()->where('documento_type', $definicion->tipo())->get()->keyBy('codigo');
        $declarados = collect($definicion->estados())->keyBy('codigo');

        // Primero se quitan los que salieron y se apaga el inicial que cambió: el índice admite un solo inicial.
        foreach ($existentes as $codigo => $estado) {
            $declarado = $declarados->get($codigo);

            if ($declarado === null && $estado->es_activo) {
                // El estado actual de un documento vive en su columna `estado_id`.
                $cantidad = $definicion->modelo()::query()->where('estado_id', $estado->id)->count();

                if ($cantidad > 0) {
                    throw SincronizacionEstadosRechazada::estadoConDocumentos($estado->nombre, $definicion->nombre(), $cantidad);
                }
            }

            $estado->fill([
                'es_activo' => $declarado !== null && $estado->es_activo,
                'es_inicial' => $estado->es_inicial && ($declarado->esInicial ?? false),
            ]);
            $this->guardarSiCambio($estado);
        }

        foreach ($definicion->estados() as $posicion => $declarado) {
            $estado = $existentes->get($declarado->codigo) ?? new Estado([
                'documento_type' => $definicion->tipo(), 'codigo' => $declarado->codigo,
                'nombre' => $declarado->nombre, 'color' => $declarado->tono,
            ]);

            if ($estado->exists && $estado->es_cancelacion !== $declarado->esCancelacion
                && TransicionEstado::query()->where('hacia_estado_id', $estado->id)->exists()) {
                throw SincronizacionEstadosRechazada::cancelacionConTransiciones($estado->nombre, $definicion->nombre());
            }

            $estado->fill($this->reglasDe($declarado) + ['orden' => $posicion + 1, 'es_activo' => true]);
            $this->guardarSiCambio($estado);
        }
    }

    private function sincronizarTransiciones(DefinicionDocumento $definicion): void
    {
        $estados = Estado::query()->where('documento_type', $definicion->tipo())->get()->keyBy('codigo');
        $vigentes = [];

        foreach ($definicion->transiciones() as $posicion => $declarada) {
            $familia = $definicion->familiaDeMotivos($declarada);
            $hacia = $estados->get($declarada->hacia);
            $reglas = [
                'nombre' => $declarada->nombre,
                'orden' => $posicion + 1,
                'hacia_estado_id' => $hacia?->id,
                'hacia_es_cancelacion' => $hacia?->es_cancelacion,
                'requiere_motivo' => $declarada->requiereMotivo,
                'tipo_motivo_id' => $familia === null ? null : $this->asegurarFamilia($familia, "{$declarada->nombre} · {$definicion->nombre()}"),
                'requiere_aprobacion' => $declarada->requiereAprobacion,
                'permiso' => $definicion->permiso($declarada),
                'es_activa' => true,
            ];

            foreach ($declarada->desde as $codigoDesde) {
                $transicion = TransicionEstado::query()
                    ->where('documento_type', $definicion->tipo())
                    ->where('desde_estado_id', $estados->get($codigoDesde)?->id)
                    ->where('codigo', $declarada->codigo)
                    ->first() ?? new TransicionEstado([
                        'documento_type' => $definicion->tipo(), 'codigo' => $declarada->codigo,
                        'desde_estado_id' => $estados->get($codigoDesde)?->id,
                    ]);

                $transicion->fill($reglas);
                $this->revisarHistorial($transicion, $definicion);
                $this->guardarSiCambio($transicion);
                $vigentes[] = $transicion->id;
            }
        }

        TransicionEstado::query()
            ->where('documento_type', $definicion->tipo())
            ->whereNotIn('id', $vigentes)
            ->where('es_activa', true)
            ->get()
            ->each(fn (TransicionEstado $transicion) => $transicion->update(['es_activa' => false]));
    }

    /**
     * El historial copia de la transición sus estados y su regla de motivo (D2): con historial, esas no cambian.
     */
    private function revisarHistorial(TransicionEstado $transicion, DefinicionDocumento $definicion): void
    {
        $cambios = [
            'hacia_estado_id' => 'el estado al que lleva',
            'requiere_motivo' => 'si exige motivo',
            'tipo_motivo_id' => 'su lista de motivos',
        ];

        foreach ($cambios as $columna => $que) {
            if ($transicion->exists && $transicion->isDirty($columna)
                && HistorialEstado::query()->where('transicion_id', $transicion->id)->exists()) {
                throw SincronizacionEstadosRechazada::transicionConHistorial($transicion->nombre, $definicion->nombre(), $que);
            }
        }
    }

    /**
     * La lista y su «Otro», que siempre existe (D9). El nombre solo se escribe al crearla.
     */
    private function asegurarFamilia(string $codigo, string $nombre): int
    {
        $familia = TipoMotivo::query()->firstOrCreate(['codigo' => $codigo], ['nombre' => $nombre]);

        if (! Motivo::query()->where('tipo_motivo_id', $familia->id)->where('es_otro', true)->exists()) {
            Motivo::query()->create(['tipo_motivo_id' => $familia->id, 'codigo' => Motivo::CODIGO_OTRO, 'nombre' => 'Otro', 'es_otro' => true]);
        }

        return $familia->id;
    }

    /**
     * Los motivos con que nace la lista (plan 2.6, D3), solo si faltan: ni su código ni su nombre están. Uno renombrado
     * o desactivado en Configuración queda como lo dejaron.
     *
     * @param  list<MotivoInicial>  $iniciales
     */
    private function crearIniciales(int $familiaId, array $iniciales): void
    {
        foreach ($iniciales as $inicial) {
            $existe = Motivo::query()->where('tipo_motivo_id', $familiaId)
                ->where(fn ($query) => $query->where('codigo', $inicial->codigo)->orWhere('nombre_comparable', Motivo::comparable($inicial->nombre)))
                ->exists();

            if (! $existe) {
                Motivo::query()->create(['tipo_motivo_id' => $familiaId, 'codigo' => $inicial->codigo, 'nombre' => $inicial->nombre, 'es_cambio_cliente' => $inicial->esCambioCliente]);
            }
        }
    }

    /**
     * @return array<string, bool>
     */
    private function reglasDe(EstadoDefinido $declarado): array
    {
        return [
            'es_inicial' => $declarado->esInicial,
            'es_final' => $declarado->esFinal,
            'es_editable' => $declarado->esEditable,
            'es_cancelacion' => $declarado->esCancelacion,
        ];
    }

    private function guardarSiCambio(Estado|TransicionEstado $fila): void
    {
        if (! $fila->exists || $fila->isDirty()) {
            $fila->save();
        }
    }
}
