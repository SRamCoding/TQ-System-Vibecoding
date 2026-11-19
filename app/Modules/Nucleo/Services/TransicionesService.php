<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Services;

use App\Modules\Nucleo\Estados\AprobacionDeTransicion;
use App\Modules\Nucleo\Estados\DefinicionDocumento;
use App\Modules\Nucleo\Estados\DocumentoConEstados;
use App\Modules\Nucleo\Estados\Efecto;
use App\Modules\Nucleo\Estados\EfectoExterno;
use App\Modules\Nucleo\Estados\MotivoElegido;
use App\Modules\Nucleo\Estados\RegistroDocumentos;
use App\Modules\Nucleo\Excepciones\TransicionRechazada;
use App\Modules\Nucleo\Excepciones\VersionDesactualizada;
use App\Modules\Nucleo\Jobs\EjecutarEfectoExterno;
use App\Modules\Nucleo\Jobs\RevertirEfectoExterno;
use App\Modules\Nucleo\Models\EfectoAplicado;
use App\Modules\Nucleo\Models\Estado;
use App\Modules\Nucleo\Models\HistorialEstado;
use App\Modules\Nucleo\Models\Motivo;
use App\Modules\Nucleo\Models\TransicionEstado;
use App\Modules\Nucleo\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * El único camino para crear un documento con estados y para cambiarle el estado (plan 1.5, D3-D7 y D9). En una
 * transacción: bloquea la fila, compara la versión que leyó la persona, valida la transición desde el estado
 * actual, el permiso con sus ámbitos, las condiciones y el motivo (al anular, también que nada vivo dependa del
 * documento), cambia el estado, escribe el historial, revierte los efectos vivos si anula y aplica los efectos de la
 * transición. Los efectos externos corren por cola después del commit. Todo lo revisa aquí, no la acción: una
 * acción propia de un módulo no se lo puede saltar.
 */
final readonly class TransicionesService
{
    public function __construct(
        private RegistroDocumentos $registro,
        private SeguridadService $seguridad,
        private ParametrosService $parametros,
        private UsuarioSistema $sistema,
        private AprobacionDeTransicion $aprobaciones,
        private ContextoAuditoria $auditoria,
        private ContextoEfectos $efectos,
        private ContextoTransicion $transicion,
        private NumeracionService $numeracion,
    ) {}

    /**
     * Guarda un documento nuevo en su estado inicial, con la primera fila del historial (sin `desde` ni transición). Si
     * su tipo se numera, toma el número justo antes de guardar, dentro de la misma transacción (plan 1.6, D1 y D2).
     * `$originadoPor`: la persona cuya acción lo generó, cuando lo crea el sistema.
     *
     * @template T of Model&DocumentoConEstados
     *
     * @param  T  $documento  sin guardar, con sus datos llenos
     * @return T
     */
    public function crear(Model&DocumentoConEstados $documento, User $usuario, ?User $originadoPor = null): Model
    {
        if ($documento->exists) {
            throw new LogicException('crear() recibe un documento sin guardar: uno guardado cambia de estado con transicionar().');
        }

        $definicion = $this->definicionDe($documento);
        $inicial = Estado::query()
            ->where('documento_type', $definicion->tipo())
            ->where('es_inicial', true)
            ->where('es_activo', true)
            ->first() ?? throw TransicionRechazada::sinEstadoInicial(ucfirst($definicion->nombre()));

        return DB::transaction(fn (): Model => $this->auditoria->comoUsuario($usuario, function () use ($documento, $definicion, $inicial, $usuario, $originadoPor): Model {
            $documento->setAttribute('estado_id', $inicial->id);
            $formato = $definicion->numeracionDe($documento);

            if ($formato !== null && ! $this->heredaNumero($definicion, $documento)) {
                $documento->setAttribute('numero', $definicion->numeroVisible($this->numeracion->siguiente(
                    $definicion->tipo(),
                    $formato,
                    $definicion->fechaDeNumeracion($documento),
                    $definicion->ambitoDeNumeracion($documento),
                ), $documento));
            }

            $this->transicion->durante(fn (): bool => $documento->save());

            HistorialEstado::query()->create([
                'documentable_type' => $documento->getMorphClass(),
                'documentable_id' => $documento->getKey(),
                'hacia_estado_id' => $inicial->id,
                'user_id' => $usuario->id,
                'originado_por_user_id' => $originadoPor?->id,
            ]);

            $this->aprobaciones->alCambiarEstado($documento, null, $originadoPor ?? $usuario);

            return $documento;
        }));
    }

    /**
     * Si el documento nuevo es una versión 2 o más de un documento cuyas versiones comparten número (planes 2.9 y 2.22,
     * D2): trae el número de su versión anterior, que tiene que existir. La versión 1 se numera aquí, nunca a mano.
     */
    private function heredaNumero(DefinicionDocumento $definicion, Model $documento): bool
    {
        if (! $definicion->versionesCompartenNumero()) {
            return false;
        }

        $version = (int) ($documento->getAttribute('numero_version') ?? 1);
        $numero = $documento->getAttribute('numero');

        if ($version === 1) {
            if ($numero !== null) {
                throw new LogicException(ucfirst($definicion->nombreConArticulo()).' v1 toma su número al crearse: el servicio no se lo pone.');
            }

            $documento->setAttribute('numero_version', 1);

            return false;
        }

        $anterior = $numero !== null && $documento->newQuery()
            ->where('numero', $numero)
            ->where('numero_version', $version - 1)
            ->exists();

        if (! $anterior) {
            throw new LogicException(ucfirst($definicion->nombreConArticulo())." v{$version} lleva el número de su versión anterior, y esa versión no existe.");
        }

        return true;
    }

    /**
     * Una persona cambia el estado. Rechaza las transiciones del sistema y las que requieren aprobación.
     *
     * @throws TransicionRechazada|VersionDesactualizada
     */
    public function transicionar(
        Model&DocumentoConEstados $documento,
        string $codigo,
        int $versionLeida,
        User $usuario,
        ?MotivoElegido $motivo = null,
        ?string $comentario = null,
    ): HistorialEstado {
        return $this->ejecutar($documento, $codigo, $versionLeida, $usuario, false, null, $motivo, $comentario);
    }

    /**
     * Código del módulo pide una transición del sistema, o el mecanismo de aprobaciones una que las requiere. Se
     * guarda a nombre del usuario de sistema, con la persona que la originó si la hubo (D5, agregado 2).
     *
     * @throws TransicionRechazada
     */
    public function transicionarComoSistema(
        Model&DocumentoConEstados $documento,
        string $codigo,
        ?User $originadoPor = null,
        ?MotivoElegido $motivo = null,
        ?string $comentario = null,
    ): HistorialEstado {
        $sistema = User::query()->findOrFail($this->sistema->id());

        return $this->ejecutar($documento, $codigo, null, $sistema, true, $originadoPor, $motivo, $comentario);
    }

    private function ejecutar(
        Model&DocumentoConEstados $documento,
        string $codigo,
        ?int $versionLeida,
        User $usuario,
        bool $comoSistema,
        ?User $originadoPor,
        ?MotivoElegido $motivo,
        ?string $comentario,
    ): HistorialEstado {
        $definicion = $this->definicionDe($documento);
        $comentario = $comentario === null || trim($comentario) === '' ? null : trim($comentario);

        return DB::transaction(fn (): HistorialEstado => $this->auditoria->comoUsuario($usuario, function () use (
            $documento, $definicion, $codigo, $versionLeida, $usuario, $comoSistema, $originadoPor, $motivo, $comentario,
        ): HistorialEstado {
            /** @var Model&DocumentoConEstados $fila */
            $fila = $documento->newQuery()->lockForUpdate()->findOrFail($documento->getKey());

            if ($versionLeida !== null && (int) $fila->getAttribute('version') !== $versionLeida) {
                throw VersionDesactualizada::de($definicion->nombreConArticulo());
            }

            $transicion = $this->transicionDisponible($fila, $definicion, $codigo);
            $this->revisarQuien($fila, $transicion, $usuario, $comoSistema);
            $this->revisarCondiciones($fila, $definicion, $transicion, $usuario);
            $motivoValido = $this->revisarMotivo($transicion, $motivo);

            if ($transicion->hacia_es_cancelacion) {
                $this->revisarDependientes($fila, $definicion);
            }

            $fila->setAttribute('estado_id', $transicion->hacia_estado_id);
            $fila->setAttribute('version', (int) $fila->getAttribute('version') + 1);
            $this->transicion->durante(fn (): bool => $fila->save());

            $historial = HistorialEstado::query()->create([
                'documentable_type' => $fila->getMorphClass(),
                'documentable_id' => $fila->getKey(),
                'desde_estado_id' => $transicion->desde_estado_id,
                'hacia_estado_id' => $transicion->hacia_estado_id,
                'transicion_id' => $transicion->id,
                'requiere_motivo' => $transicion->requiere_motivo,
                'tipo_motivo_id' => $transicion->tipo_motivo_id,
                'motivo_id' => $motivoValido?->id,
                'motivo_es_otro' => $motivoValido->es_otro ?? false,
                'motivo_detalle' => $motivoValido?->es_otro ? trim((string) $motivo?->detalle) : null,
                'comentario' => $comentario,
                'user_id' => $usuario->id,
                'originado_por_user_id' => $originadoPor?->id,
            ]);

            if ($transicion->hacia_es_cancelacion) {
                $this->revertirEfectosVivos($fila, $historial);
            }

            $this->aplicarEfectos($fila, $definicion->transicion($codigo)->efectos ?? [], $historial);

            // Abre o cierra los pedidos de aprobación del estado nuevo (plan 1.7).
            $this->aprobaciones->alCambiarEstado($fila, $transicion, $originadoPor ?? $usuario);

            // Quien llamó sigue con el documento al día: estado y versión nuevos.
            $documento->setRawAttributes($fila->getAttributes(), true);

            return $historial;
        }));
    }

    /**
     * Las transiciones que la persona puede hacer ahora desde el estado actual: de persona, con su permiso en algún
     * ámbito y con las condiciones cumplidas (D6 y D8). Las del sistema y las que requieren aprobación no salen.
     *
     * @return list<TransicionEstado>
     */
    public function salidasDisponibles(Model&DocumentoConEstados $documento, User $usuario): array
    {
        $definicion = $this->definicionDe($documento);
        $estadoId = $documento->newQuery()->whereKey($documento->getKey())->value('estado_id');

        return TransicionEstado::query()
            ->where('documento_type', $definicion->tipo())
            ->where('desde_estado_id', $estadoId)
            ->where('es_activa', true)
            ->where('requiere_aprobacion', false)
            ->whereNotNull('permiso')
            ->orderBy('orden')
            ->get()
            ->filter(fn (TransicionEstado $transicion): bool => $this->puedeEjecutar($usuario, (string) $transicion->permiso, $documento)
                && $this->primeraCondicionQueFalla($documento, $definicion, $transicion, $usuario) === null)
            ->values()
            ->all();
    }

    private function transicionDisponible(Model $fila, DefinicionDocumento $definicion, string $codigo): TransicionEstado
    {
        $transicion = TransicionEstado::query()
            ->where('documento_type', $definicion->tipo())
            ->where('desde_estado_id', $fila->getAttribute('estado_id'))
            ->where('codigo', $codigo)
            ->where('es_activa', true)
            ->first();

        if ($transicion === null) {
            $estado = Estado::query()->findOrFail($fila->getAttribute('estado_id'));

            throw TransicionRechazada::noDisponible($definicion->transicion($codigo)->nombre ?? $codigo, $definicion->nombreConArticulo(), $estado->nombre);
        }

        return $transicion;
    }

    private function revisarQuien(Model&DocumentoConEstados $fila, TransicionEstado $transicion, User $usuario, bool $comoSistema): void
    {
        if ($comoSistema) {
            if (! $transicion->esDelSistema() && ! $transicion->requiere_aprobacion) {
                throw TransicionRechazada::noEsDelSistema($transicion->nombre);
            }

            $pendiente = $transicion->requiere_aprobacion ? $this->aprobaciones->pendiente($fila, $transicion) : null;

            if ($pendiente !== null) {
                throw TransicionRechazada::condicion($pendiente);
            }

            return;
        }

        if ($transicion->requiere_aprobacion) {
            throw TransicionRechazada::conAprobacion($transicion->nombre);
        }

        if ($transicion->esDelSistema()) {
            throw TransicionRechazada::delSistema($transicion->nombre);
        }

        if (! $this->puedeEjecutar($usuario, (string) $transicion->permiso, $fila)) {
            throw TransicionRechazada::sinPermiso($transicion->nombre);
        }
    }

    /** El permiso global o en cualquiera de los ámbitos del documento (D6, ajuste 1). */
    private function puedeEjecutar(User $usuario, string $permiso, Model&DocumentoConEstados $documento): bool
    {
        if ($this->seguridad->puede($usuario, $permiso)) {
            return true;
        }

        foreach ($documento->ambitoDePermisos() as $ambito) {
            if ($this->seguridad->puede($usuario, $permiso, $ambito)) {
                return true;
            }
        }

        return false;
    }

    private function revisarCondiciones(Model $fila, DefinicionDocumento $definicion, TransicionEstado $transicion, User $usuario): void
    {
        $mensaje = $this->primeraCondicionQueFalla($fila, $definicion, $transicion, $usuario);

        if ($mensaje !== null) {
            throw TransicionRechazada::condicion($mensaje);
        }
    }

    private function primeraCondicionQueFalla(Model $fila, DefinicionDocumento $definicion, TransicionEstado $transicion, User $usuario): ?string
    {
        foreach ($definicion->transicion($transicion->codigo)->condiciones ?? [] as $clase) {
            $mensaje = app($clase)->revisar($fila, $usuario, $transicion);

            if ($mensaje !== null) {
                return $mensaje;
            }
        }

        return null;
    }

    /**
     * Un motivo activo de la lista de la transición; con «Otro», el detalle con el mínimo del parámetro (D9). La
     * base revisa lo mismo salvo el mínimo; aquí se revisa antes para dar un mensaje claro.
     */
    private function revisarMotivo(TransicionEstado $transicion, ?MotivoElegido $elegido): ?Motivo
    {
        if (! $transicion->requiere_motivo) {
            if ($elegido !== null) {
                throw TransicionRechazada::motivoSobrante($transicion->nombre);
            }

            return null;
        }

        if ($elegido === null) {
            throw TransicionRechazada::faltaMotivo($transicion->nombre);
        }

        $motivo = Motivo::query()
            ->where('id', $elegido->motivoId)
            ->where('tipo_motivo_id', $transicion->tipo_motivo_id)
            ->where('es_activo', true)
            ->first() ?? throw TransicionRechazada::motivoNoValido($transicion->nombre);

        $detalle = trim((string) $elegido->detalle);

        if (! $motivo->es_otro && $detalle !== '') {
            throw TransicionRechazada::detalleSobrante();
        }

        if ($motivo->es_otro) {
            $minimo = $this->parametros->entero('motivos.minimo_detalle_otro');

            if (mb_strlen($detalle) < $minimo) {
                throw TransicionRechazada::detalleCorto($minimo);
            }
        }

        return $motivo;
    }

    /** Un documento no se anula mientras otro vivo dependa de él (D4). */
    private function revisarDependientes(Model $fila, DefinicionDocumento $definicion): void
    {
        $impiden = [];

        foreach ($this->registro->revisionesAntesDeAnular($definicion) as $clase) {
            foreach (app($clase)->documentosQueImpiden($fila) as $documento) {
                $impiden[] = $documento->descripcion();
            }
        }

        if ($impiden !== []) {
            throw TransicionRechazada::documentosVivos($definicion->nombreConArticulo(), $impiden);
        }
    }

    /**
     * Los internos corren aquí, en orden, y guardan lo que hicieron; de los externos solo se guarda lo preparado y
     * un job los ejecuta después del commit (D3).
     *
     * @param  list<string>  $claves
     */
    private function aplicarEfectos(Model $fila, array $claves, HistorialEstado $historial): void
    {
        foreach ($claves as $indice => $clave) {
            $efecto = $this->efectoDe($clave);
            $externo = $efecto instanceof EfectoExterno;
            $datos = $this->efectos->durante($efecto, fn (): array => $efecto instanceof EfectoExterno ? $efecto->preparar($fila) : $efecto->aplicar($fila));

            $aplicado = EfectoAplicado::query()->create([
                'historial_estado_id' => $historial->id,
                'clave' => $clave,
                'orden' => $indice + 1,
                'es_externo' => $externo,
                'datos' => $datos,
                'ejecutado_at' => $externo ? null : now(),
            ]);

            if ($externo) {
                EjecutarEfectoExterno::dispatch($aplicado->id);
            }
        }
    }

    /**
     * Revierte, del más reciente al más antiguo, los efectos que siguen vivos, con los datos que guardaron (D3 y D4).
     * `$despuesDe` limita a los aplicados después de esa fila del historial: lo que usará «Reabrir».
     */
    private function revertirEfectosVivos(Model $fila, HistorialEstado $historial, ?int $despuesDe = null): void
    {
        $vivos = EfectoAplicado::query()
            ->whereNull('revertido_por_historial_id')
            ->whereHas('historial', fn (Builder $consulta) => $consulta
                ->where('documentable_type', $fila->getMorphClass())
                ->where('documentable_id', $fila->getKey())
                ->when($despuesDe !== null, fn (Builder $desde) => $desde->where('id', '>', $despuesDe)))
            ->orderByDesc('historial_estado_id')
            ->orderByDesc('orden')
            ->lockForUpdate()
            ->get();

        foreach ($vivos as $aplicado) {
            $efecto = $this->efectoDe($aplicado->clave);

            if ($efecto instanceof Efecto) {
                $this->efectos->durante($efecto, fn () => $efecto->revertir($fila, $aplicado->datos));
            }

            $aplicado->revertido_por_historial_id = $historial->id;
            $aplicado->save();

            // Un externo que salió o pudo salir (ejecutado, o reservado sin confirmar) se revierte por cola. Uno que
            // nunca se tomó no tiene nada que deshacer: su job ya no lo enviará.
            if ($aplicado->es_externo && ($aplicado->ejecutado_at !== null || $aplicado->reservado_hasta !== null)) {
                RevertirEfectoExterno::dispatch($aplicado->id);
            }
        }
    }

    /**
     * Si algún efecto externo vivo del documento salió o pudo salir: ejecutado, o reservado y sin confirmar. Lo que
     * consulta «Reabrir», que solo se permite si nada salió (`codigo.md`). `$despuesDe` limita a los aplicados
     * después de esa fila del historial.
     */
    public function salioAlExterior(Model&DocumentoConEstados $documento, ?int $despuesDe = null): bool
    {
        return EfectoAplicado::query()
            ->where('es_externo', true)
            ->whereNull('revertido_por_historial_id')
            ->where(fn (Builder $consulta) => $consulta->whereNotNull('ejecutado_at')->orWhereNotNull('reservado_hasta'))
            ->whereHas('historial', fn (Builder $consulta) => $consulta
                ->where('documentable_type', $documento->getMorphClass())
                ->where('documentable_id', $documento->getKey())
                ->when($despuesDe !== null, fn (Builder $desde) => $desde->where('id', '>', $despuesDe)))
            ->exists();
    }

    private function efectoDe(string $clave): Efecto|EfectoExterno
    {
        $clase = $this->registro->claseDeEfecto($clave) ?? throw new LogicException("El efecto «{$clave}» no está registrado.");

        return app($clase);
    }

    private function definicionDe(Model $documento): DefinicionDocumento
    {
        return $this->registro->definicionDe($documento->getMorphClass())
            ?? throw new LogicException("{$documento->getMorphClass()} no tiene definición de estados registrada.");
    }
}
