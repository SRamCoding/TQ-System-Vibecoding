<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Services;

use App\Modules\Nucleo\Estados\DefinicionDocumento;
use App\Modules\Nucleo\Estados\DocumentoConEstados;
use App\Modules\Nucleo\Estados\RegistroDocumentos;
use App\Modules\Nucleo\Excepciones\AutorDesconocido;
use App\Modules\Nucleo\Excepciones\EdicionRechazada;
use App\Modules\Nucleo\Models\Estado;
use App\Modules\Nucleo\Models\TransicionEstado;
use App\Modules\Nucleo\Models\User;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * Lo que revisan los traits TieneEstados y ParteDeDocumento antes de guardar (plan 1.5, D5 y D8). Vive en el modelo,
 * así que rechaza igual desde una pantalla, una acción, un servicio, un job o la consola:
 * - en un estado no editable, nada cambia salvo las columnas del mecanismo y los campos derivados;
 * - un campo derivado solo cambia dentro de un efecto, en cualquier estado;
 * - en un estado no editable, no se agregan ni se borran líneas;
 * - la cabecera no se borra nunca: se anula;
 * - el estado solo lo pone TransicionesService, al crear y al cambiarlo.
 * El estado se lee de la base, no del modelo: una instancia vieja no se salta el bloqueo.
 */
final readonly class BloqueoEdicion
{
    /** Las pone el mecanismo de estados y la auditoría, no la persona. */
    private const MECANISMO = ['estado_id', 'version', 'updated_at', 'updated_by'];

    public function __construct(
        private RegistroDocumentos $registro,
        private ContextoEfectos $efectos,
        private ContextoAuditoria $auditoria,
        private TransicionesService $transiciones,
        private ContextoTransicion $transicion,
    ) {}

    /**
     * Antes de crear la cabecera o de guardar un cambio de su estado: solo TransicionesService lo hace, y así queda
     * en el historial. Es un error de programación, no de la persona.
     */
    public function exigirTransicion(Model&DocumentoConEstados $documento): void
    {
        if ((! $documento->exists || $documento->isDirty('estado_id')) && ! $this->transicion->activo()) {
            throw new LogicException("El estado de un documento ({$documento->getMorphClass()}) solo lo pone TransicionesService: así queda en el historial.");
        }
    }

    /**
     * Antes de guardar cambios en la cabecera o en una línea.
     *
     * @param  list<string>  $derivados
     */
    public function alCambiar(Model $modelo, Model&DocumentoConEstados $documento, array $derivados): void
    {
        $cambiados = array_values(array_diff(array_keys($modelo->getDirty()), self::MECANISMO));
        $derivadosCambiados = array_values(array_intersect($cambiados, $derivados));

        if ($derivadosCambiados !== [] && ! $this->efectos->activo()) {
            throw EdicionRechazada::derivado($derivadosCambiados);
        }

        if (array_diff($cambiados, $derivadosCambiados) !== []) {
            $this->exigirEditable($documento);
        }
    }

    /** Un documento con estados no se borra: se anula, y su historial y sus efectos quedan (v4, parte 1, idea 3). */
    public function rechazarBorrado(Model&DocumentoConEstados $documento): never
    {
        throw EdicionRechazada::noSeBorra($this->definicion($documento)->nombreConArticulo());
    }

    /** Antes de agregar o borrar una línea. */
    public function exigirEditable(Model&DocumentoConEstados $documento): void
    {
        $estado = $this->estadoActual($documento);

        if (! $estado->es_editable) {
            throw EdicionRechazada::noEditable($this->definicion($documento)->nombreConArticulo(), $estado->nombre, $this->salidas($documento));
        }
    }

    public function esEditable(Model&DocumentoConEstados $documento): bool
    {
        return $this->estadoActual($documento)->es_editable;
    }

    private function estadoActual(Model&DocumentoConEstados $documento): Estado
    {
        // Con bloqueo compartido: una línea que se agrega mientras otro aprueba espera a que termine y ve el estado
        // nuevo. Sirve dentro de la transacción del servicio que edita, como pide `codigo.md`.
        $estadoId = $documento->newQuery()->whereKey($documento->getKey())->sharedLock()->value('estado_id') ?? $documento->getAttribute('estado_id');

        return Estado::query()->findOrFail($estadoId);
    }

    /**
     * Las salidas que la persona que guarda puede usar ahora, con las mismas reglas que la barra de estados.
     *
     * @return list<string>
     */
    private function salidas(Model&DocumentoConEstados $documento): array
    {
        try {
            $usuario = User::query()->find($this->auditoria->idActor());
        } catch (AutorDesconocido) {
            return [];
        }

        if ($usuario === null) {
            return [];
        }

        return array_map(fn (TransicionEstado $transicion): string => $transicion->nombre, $this->transiciones->salidasDisponibles($documento, $usuario));
    }

    private function definicion(Model $documento): DefinicionDocumento
    {
        return $this->registro->definicionDe($documento->getMorphClass())
            ?? throw new LogicException("{$documento->getMorphClass()} no tiene definición de estados registrada.");
    }
}
