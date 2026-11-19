<?php

declare(strict_types=1);

namespace App\Modules\Erp\Presupuestos\Imputacion;

use App\Modules\Erp\Presupuestos\Estados\DefinicionArbolPartidas;
use App\Modules\Erp\Presupuestos\Models\PartidaControl;
use App\Modules\Erp\Proyectos\Services\ProyectosService;
use App\Modules\Nucleo\Excepciones\ImputacionRechazada;
use App\Modules\Nucleo\Imputacion\DestinoImputacion;
use App\Modules\Nucleo\Models\CentroCosto;
use App\Modules\Nucleo\Models\Motivo;

/**
 * Una partida de control como destino de una línea de costo (plan 2.1, D6). Se revisa al pedirla (aviso temprano) y
 * otra vez dentro de la transacción de `destinar()`, con la fila de la partida bloqueada en lectura compartida: así no
 * se desactiva mientras se le imputa. El estado del proyecto se lee por C2, sin bloqueo: cerrar el proyecto mientras
 * se imputa no está cubierto (plan 2.1, bitácora). Se pide con `PartidasService::destino()`.
 */
final readonly class DestinoPartida implements DestinoImputacion
{
    /** La lista de motivos de un gasto no presupuestado (PC-99; flujo 7). La registra el provider. */
    public const string FAMILIA_NO_PRESUPUESTADO = 'erp.presupuestos.gasto_no_presupuestado';

    /** La lista de motivos de un exceso de partida (plan 2.2, D4). La registra el provider. */
    public const string FAMILIA_EXCESO = 'erp.presupuestos.exceso_partida';

    private int $proyectoId;

    private string $codigo;

    public function __construct(
        private int $partidaId,
        private ?int $motivoId,
        private ?string $comentario,
        private ?int $centroCostoId,
        private ProyectosService $proyectos,
    ) {
        $partida = $this->revisar(false);
        $this->proyectoId = $partida->proyecto_id;
        $this->codigo = $partida->codigo;
    }

    public function columnas(): array
    {
        return [
            'partida_control_id' => $this->partidaId,
            'proyecto_id' => $this->proyectoId,
            'centro_costo_id' => $this->centroCostoId,
            'motivo_id' => $this->motivoId,
            'comentario' => $this->comentario === null || trim($this->comentario) === '' ? null : trim($this->comentario),
        ];
    }

    public function revisarAlDestinar(): void
    {
        $this->revisar(true);
    }

    public function descripcion(): string
    {
        return "la partida {$this->codigo}";
    }

    private function revisar(bool $bloquear): PartidaControl
    {
        $partida = PartidaControl::query()
            ->with('arbol.estado')
            ->whereKey($this->partidaId)
            ->when($bloquear, fn ($query) => $query->sharedLock())
            ->first() ?? throw ImputacionRechazada::conMotivo('Esa partida no existe.');

        if (! $partida->es_activa) {
            throw ImputacionRechazada::conMotivo("La partida {$partida->codigo} está desactivada: no recibe costos.");
        }

        if (! $partida->es_hoja) {
            throw ImputacionRechazada::conMotivo("La partida {$partida->codigo} no es una hoja: el costo va a una de las partidas de abajo.");
        }

        if ($partida->arbol->estado->codigo !== DefinicionArbolPartidas::APROBADO) {
            throw ImputacionRechazada::conMotivo('El árbol de este proyecto todavía no está aprobado.');
        }

        $proyecto = $this->proyectos->resumen($partida->proyecto_id);

        if (! $proyecto->aceptaCostos) {
            throw ImputacionRechazada::conMotivo("El proyecto {$proyecto->codigo} está ".mb_strtolower($proyecto->estadoNombre).': no acepta costos.');
        }

        if ($this->comentario !== null && mb_strlen(trim($this->comentario)) > 500) {
            throw ImputacionRechazada::conMotivo('El comentario va con hasta 500 caracteres.');
        }

        if ($partida->es_no_presupuestado) {
            $this->revisarNoPresupuestado($bloquear);
        } elseif ($this->centroCostoId !== null) {
            throw ImputacionRechazada::conMotivo('El centro de costo de referencia es solo para la PC-99.');
        } elseif ($this->motivoId !== null && ! $this->esDeLaLista($this->motivoId, self::FAMILIA_EXCESO)) {
            // Plan 2.2, D4: en una partida normal, el único motivo es el del exceso.
            throw ImputacionRechazada::conMotivo('Ese motivo no es de la lista de excesos de partida.');
        }

        return $partida;
    }

    private function revisarNoPresupuestado(bool $bloquear): void
    {
        if ($this->motivoId === null || trim((string) $this->comentario) === '') {
            throw ImputacionRechazada::conMotivo('Un gasto no presupuestado (PC-99) lleva su motivo y un comentario.');
        }

        if (! $this->esDeLaLista($this->motivoId, self::FAMILIA_NO_PRESUPUESTADO)) {
            throw ImputacionRechazada::conMotivo('Ese motivo no es de la lista de gastos no presupuestados.');
        }

        // Con la misma lectura compartida que `CentroDeCosto`: el centro de referencia no se desactiva mientras se imputa.
        if ($this->centroCostoId !== null && ! CentroCosto::query()->whereKey($this->centroCostoId)->where('es_activo', true)->when($bloquear, fn ($query) => $query->sharedLock())->exists()) {
            throw ImputacionRechazada::conMotivo('Ese centro de costo no existe o está desactivado.');
        }
    }

    private function esDeLaLista(int $motivoId, string $familia): bool
    {
        return Motivo::query()
            ->whereKey($motivoId)
            ->where('es_activo', true)
            ->whereHas('tipoMotivo', fn ($query) => $query->where('codigo', $familia))
            ->exists();
    }
}
