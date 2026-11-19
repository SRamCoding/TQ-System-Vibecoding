<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Services;

use App\Modules\Nucleo\Enums\TonoEstado;
use App\Modules\Nucleo\Estados\BarraDeEstados;
use App\Modules\Nucleo\Estados\DocumentoConEstados;
use App\Modules\Nucleo\Estados\EstadoEnBarra;
use App\Modules\Nucleo\Estados\FilaHistorialEstados;
use App\Modules\Nucleo\Estados\RegistroDocumentos;
use App\Modules\Nucleo\Estados\SalidaDeEstado;
use App\Modules\Nucleo\Excepciones\VersionDesactualizada;
use App\Modules\Nucleo\Models\Estado;
use App\Modules\Nucleo\Models\HistorialEstado;
use App\Modules\Nucleo\Models\TransicionEstado;
use App\Modules\Nucleo\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Lo que las pantallas leen y editan de los estados (plan 1.5, D1 y D10): la barra de estados y el historial de un
 * documento, y el nombre y el color de un estado. El código, si es editable, inicial, final o de cancelación y sus
 * transiciones los declara el código del módulo; el sincronizador no pisa lo que se cambie aquí.
 */
final readonly class EstadosService
{
    public function __construct(
        private RegistroDocumentos $registro,
        private TransicionesService $transiciones,
    ) {}

    /** Los estados en orden, el actual, las salidas que esa persona puede usar ahora y las que esperan aprobación. */
    public function barraDeEstados(Model&DocumentoConEstados $documento, User $usuario): BarraDeEstados
    {
        $definicion = $this->registro->definicionDe($documento->getMorphClass())
            ?? throw new LogicException("{$documento->getMorphClass()} no tiene definición de estados registrada.");
        $actual = (int) $documento->newQuery()->whereKey($documento->getKey())->value('estado_id');

        $estados = Estado::query()
            ->where('documento_type', $definicion->tipo())
            ->where(fn (Builder $consulta) => $consulta->where('es_activo', true)->orWhere('id', $actual))
            ->orderBy('orden')
            ->get()
            ->map(fn (Estado $estado): EstadoEnBarra => new EstadoEnBarra(
                $estado->id, $estado->codigo, $estado->nombre, $estado->color, $estado->id === $actual, $estado->es_final, $estado->es_cancelacion,
            ))
            ->all();

        $salidas = array_map(fn (TransicionEstado $transicion): SalidaDeEstado => new SalidaDeEstado(
            $transicion->codigo,
            $transicion->nombre,
            Estado::query()->findOrFail($transicion->hacia_estado_id)->nombre,
            $transicion->requiere_motivo,
            $transicion->tipo_motivo_id,
            $transicion->hacia_es_cancelacion,
            ($definicion->transicion($transicion->codigo)->accionPropia ?? false) ? null : (string) $transicion->permiso,
        ), $this->transiciones->salidasDisponibles($documento, $usuario));

        $esperanAprobacion = TransicionEstado::query()
            ->where('documento_type', $definicion->tipo())
            ->where('desde_estado_id', $actual)
            ->where('requiere_aprobacion', true)
            ->where('es_activa', true)
            ->orderBy('orden')
            ->pluck('nombre')
            ->all();

        return new BarraDeEstados(array_values($estados), $salidas, $esperanAprobacion);
    }

    /**
     * Del primer cambio al último, con motivo, detalle, comentario, quién y quién lo originó.
     *
     * @return list<FilaHistorialEstados>
     */
    public function historialDe(Model&DocumentoConEstados $documento): array
    {
        return HistorialEstado::query()
            ->where('documentable_type', $documento->getMorphClass())
            ->where('documentable_id', $documento->getKey())
            ->with(['desde', 'hacia', 'transicion', 'motivo', 'usuario', 'originadoPor'])
            ->orderBy('id')
            ->get()
            ->map(fn (HistorialEstado $fila): FilaHistorialEstados => new FilaHistorialEstados(
                $fila->fecha,
                $fila->desde?->nombre,
                $fila->hacia()->firstOrFail()->nombre,
                $fila->transicion?->nombre,
                $fila->motivo?->nombre,
                $fila->motivo_detalle,
                $fila->comentario,
                $fila->usuario()->firstOrFail()->name,
                $fila->originadoPor?->name,
            ))
            ->values()
            ->all();
    }

    public function cambiarPresentacion(int $estadoId, int $versionLeida, string $nombre, TonoEstado $color): Estado
    {
        return DB::transaction(function () use ($estadoId, $versionLeida, $nombre, $color): Estado {
            $estado = Estado::query()->lockForUpdate()->findOrFail($estadoId);

            if ($estado->version !== $versionLeida) {
                throw VersionDesactualizada::de("el estado «{$estado->nombre}»");
            }

            $estado->nombre = trim($nombre);
            $estado->color = $color;
            $estado->version++;
            $estado->save();

            return $estado;
        });
    }
}
