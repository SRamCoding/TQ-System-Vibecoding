<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Services;

use App\Modules\Nucleo\Alertas\AlertasDelNucleo;
use App\Modules\Nucleo\Aprobaciones\DatoParaAprobar;
use App\Modules\Nucleo\Aprobaciones\DatosRegla;
use App\Modules\Nucleo\Aprobaciones\PendienteDeFirma;
use App\Modules\Nucleo\Aprobaciones\RegistroResumenesAprobacion;
use App\Modules\Nucleo\Aprobaciones\ReglasDeAprobacion;
use App\Modules\Nucleo\Aprobaciones\ResolutorDeFirmante;
use App\Modules\Nucleo\Enums\CondicionRegla;
use App\Modules\Nucleo\Enums\EstadoFirmaAprobacion;
use App\Modules\Nucleo\Enums\EstadoRondaAprobacion;
use App\Modules\Nucleo\Estados\DefinicionDocumento;
use App\Modules\Nucleo\Estados\DocumentoConEstados;
use App\Modules\Nucleo\Estados\RegistroDocumentos;
use App\Modules\Nucleo\Excepciones\AprobacionRechazada;
use App\Modules\Nucleo\Excepciones\VersionDesactualizada;
use App\Modules\Nucleo\Models\FirmaAprobacion;
use App\Modules\Nucleo\Models\Motivo;
use App\Modules\Nucleo\Models\PasoReglaAprobacion;
use App\Modules\Nucleo\Models\ReglaAprobacion;
use App\Modules\Nucleo\Models\RondaAprobacion;
use App\Modules\Nucleo\Models\TipoMotivo;
use App\Modules\Nucleo\Models\UnidadOrganizativa;
use App\Modules\Nucleo\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * M2 · Firmar, observar y autoaprobar, y las reglas de aprobación (plan 1.7). Cada firma bloquea la fila del documento
 * y revisa todo dentro del bloqueo (D9): le toca a quien ocupa la jefatura del paso que sigue (D1, en orden: D2), sobre
 * la misma versión que se pidió (D5). Con la última firma, ejecuta la transición como sistema con quien firmó como
 * originador (plan 1.5, D7). Observar devuelve el documento a editable (D4). Las reglas son datos (v4).
 */
final readonly class AprobacionesService
{
    /** La lista de motivos para observar (plan 1.5, D9), con su «Otro». */
    public const string FAMILIA_OBSERVAR = 'nucleo.aprobaciones.observar';

    public const string PERMISO_AUTOAPROBAR = 'aprobaciones.autoaprobar';

    public function __construct(
        private RegistroDocumentos $registro,
        private ReglasDeAprobacion $reglas,
        private ResolutorDeFirmante $resolutor,
        private TransicionesService $transiciones,
        private ContextoAuditoria $auditoria,
        private AlertasService $alertas,
    ) {}

    /**
     * Firma el paso que sigue. Si era el último, el documento pasa por la transición aprobada. Con `$versionLeida` (la
     * del documento al abrir la bandeja, plan 2.22, P6), si el documento cambió desde entonces, no se firma.
     */
    public function firmar(string $documentoType, int $documentoId, string $transicionCodigo, User $usuario, ?int $versionLeida = null): FirmaAprobacion
    {
        return $this->conLaFirmaQueSigue($documentoType, $documentoId, $transicionCodigo, $usuario, $versionLeida, function (Model&DocumentoConEstados $documento, RondaAprobacion $ronda, FirmaAprobacion $firma, string $paso) use ($usuario): FirmaAprobacion {
            if ($this->resolutor->firmante($firma, $ronda)?->id !== $usuario->id) {
                throw AprobacionRechazada::noTeToca($paso);
            }

            return $this->aprobar($documento, $ronda, $firma, $usuario, null);
        });
    }

    /**
     * Quien pidió la aprobación firma su propio paso (D6): solo si ocupa esa jefatura, tiene el permiso, la regla lo
     * permite hasta su tope y da un motivo. La firma queda marcada para revisión posterior.
     */
    public function autoaprobar(string $documentoType, int $documentoId, string $transicionCodigo, User $usuario, string $motivo): FirmaAprobacion
    {
        return $this->conLaFirmaQueSigue($documentoType, $documentoId, $transicionCodigo, $usuario, null, function (Model&DocumentoConEstados $documento, RondaAprobacion $ronda, FirmaAprobacion $firma, string $paso) use ($usuario, $motivo): FirmaAprobacion {
            $motivo = trim($motivo);

            if ($ronda->solicitante_user_id !== $usuario->id || $this->resolutor->titular($firma, $ronda)?->id !== $usuario->id) {
                throw AprobacionRechazada::autoaprobacion("Solo autoaprueba quien pidió la aprobación y ocupa {$paso}.");
            }

            if (! $usuario->can(self::PERMISO_AUTOAPROBAR)) {
                throw AprobacionRechazada::autoaprobacion('No tienes permiso para aprobar lo que tú mismo pediste.');
            }

            $tope = ReglaAprobacion::query()->whereKey($firma->regla_aprobacion_id)->value('tope_autoaprobacion');

            if ($tope === null || bccomp($ronda->monto_soles, (string) $tope, 2) > 0) {
                throw AprobacionRechazada::autoaprobacion($tope === null
                    ? 'Esta regla no permite aprobar lo que uno mismo pidió: firma la jefatura de arriba.'
                    : 'El monto pasa el tope de autoaprobación de la regla (S/ '.number_format((float) $tope, 2).'): firma la jefatura de arriba.');
            }

            if ($motivo === '') {
                throw AprobacionRechazada::autoaprobacion('Di por qué apruebas lo que tú mismo pediste.');
            }

            $firmada = $this->aprobar($documento, $ronda, $firma, $usuario, $motivo);
            // Plan 1.10, D3: avisa en el momento, en la misma transacción (si la firma no queda, el aviso tampoco).
            $definicion = $this->definicionDe($documento);
            $this->alertas->emitir(
                AlertasDelNucleo::AUTOAPROBACION,
                "firma:{$firmada->id}",
                ['persona' => $usuario->name, 'documento' => $definicion->nombre().' '.$definicion->identificador($documento)],
                $documento,
                route('filament.app.configuracion.resources.aprobaciones.index', ['tab' => 'autoaprobaciones']),
            );

            return $firmada;
        });
    }

    /**
     * Quien debe firmar lo observa (D4): motivo de la lista de observaciones y un comentario que diga qué corregir. Las
     * firmas de la ronda quedan anuladas y el documento vuelve a editable. `$versionLeida`, como en `firmar`.
     */
    public function observar(string $documentoType, int $documentoId, string $transicionCodigo, User $usuario, int $motivoId, string $comentario, ?int $versionLeida = null): FirmaAprobacion
    {
        return $this->conLaFirmaQueSigue($documentoType, $documentoId, $transicionCodigo, $usuario, $versionLeida, function (Model&DocumentoConEstados $documento, RondaAprobacion $ronda, FirmaAprobacion $firma, string $paso) use ($usuario, $motivoId, $comentario, $transicionCodigo): FirmaAprobacion {
            $definicion = $this->definicionDe($documento);
            $comentario = trim($comentario);

            if ($this->resolutor->firmante($firma, $ronda)?->id !== $usuario->id) {
                throw AprobacionRechazada::noTeToca($paso);
            }

            $alObservar = $definicion->transicionAlObservar($transicionCodigo)
                ?? throw AprobacionRechazada::sinObservacion(ucfirst($definicion->nombreConArticulo()));

            $familia = TipoMotivo::query()->where('codigo', self::FAMILIA_OBSERVAR)->value('id');
            $valido = Motivo::query()->whereKey($motivoId)->where('tipo_motivo_id', $familia)->where('es_activo', true)->exists();

            if (! $valido) {
                throw AprobacionRechazada::motivo('Elige un motivo de la lista de observaciones.');
            }

            if ($comentario === '') {
                throw AprobacionRechazada::motivo('Escribe qué hay que corregir.');
            }

            $firma->update([
                'estado' => EstadoFirmaAprobacion::Observada,
                'user_id' => $usuario->id,
                'firmado_at' => now(),
                'motivo_id' => $motivoId,
                'comentario' => $comentario,
            ]);
            $ronda->firmas()->where('estado', EstadoFirmaAprobacion::Pendiente)->get()
                ->each(fn (FirmaAprobacion $otra): bool => $otra->update(['estado' => EstadoFirmaAprobacion::Anulada]));
            $ronda->update(['estado' => EstadoRondaAprobacion::Observada, 'cerrada_at' => now()]);

            $this->transiciones->transicionarComoSistema($documento, $alObservar, $usuario, comentario: $comentario);

            return $firma;
        });
    }

    /**
     * Lo que la persona tiene por firmar ahora: el paso que sigue de cada ronda abierta, cuando le toca a ella.
     *
     * @return list<FirmaAprobacion>
     */
    public function porFirmar(User $usuario): array
    {
        $porFirmar = [];

        foreach (RondaAprobacion::query()->where('estado', EstadoRondaAprobacion::Abierta)->orderBy('id')->get() as $ronda) {
            $firma = $ronda->firmas()->where('estado', EstadoFirmaAprobacion::Pendiente)->first();

            if ($firma !== null && $this->resolutor->firmante($firma, $ronda)?->id === $usuario->id) {
                $porFirmar[] = $firma;
            }
        }

        return $porFirmar;
    }

    /**
     * «Mis aprobaciones» (plan 2.22, P6): lo que la persona tiene por firmar, con el resumen de cada documento. Los datos
     * de un grupo sensible se quitan aquí, en el servidor, para quien no tiene su permiso.
     *
     * @return list<PendienteDeFirma>
     */
    public function bandeja(User $usuario, RegistroResumenesAprobacion $resumenes): array
    {
        $bandeja = [];

        foreach ($this->porFirmar($usuario) as $firma) {
            $ronda = $firma->ronda;
            $definicion = $this->definicionDe($ronda->aprobable_type);
            $modelo = $definicion->modelo();
            /** @var Model&DocumentoConEstados $documento */
            $documento = $modelo::query()->findOrFail($ronda->aprobable_id);
            $resumen = $resumenes->para($ronda->aprobable_type);
            $datos = $resumen === null ? [] : array_values(array_filter(
                $resumen->datos($ronda->aprobable_id),
                fn (DatoParaAprobar $dato): bool => $dato->grupo === null || $usuario->can($dato->grupo->permiso()),
            ));

            $bandeja[] = new PendienteDeFirma(
                firmaId: $firma->id,
                documentoType: $ronda->aprobable_type,
                documentoId: $ronda->aprobable_id,
                transicion: $ronda->transicion_codigo,
                documento: ucfirst($definicion->nombre()).' '.$definicion->identificador($documento),
                paso: $this->resolutor->descripcion($firma, $ronda),
                solicitante: (string) User::query()->whereKey($ronda->solicitante_user_id)->value('name'),
                pedidaAt: CarbonImmutable::parse($ronda->created_at),
                montoSoles: $ronda->monto_soles,
                version: (int) $documento->getAttribute('version'),
                datos: $datos,
                enlace: $resumen?->enlace($ronda->aprobable_id),
            );
        }

        return $bandeja;
    }

    /**
     * Cierra la ronda abierta y la vuelve a calcular con las reglas de ahora (D5): para la que nació sin reglas, o
     * después de cambiar una regla que debía alcanzarla. Lo hace un administrador.
     */
    public function recalcular(string $documentoType, int $documentoId, string $transicionCodigo, User $usuario): RondaAprobacion
    {
        return DB::transaction(fn (): RondaAprobacion => $this->auditoria->comoUsuario($usuario, function () use ($documentoType, $documentoId, $transicionCodigo): RondaAprobacion {
            $definicion = $this->definicionDe($documentoType);
            $modelo = $definicion->modelo();
            /** @var Model&DocumentoConEstados $documento */
            $documento = $modelo::query()->lockForUpdate()->findOrFail($documentoId);
            $ronda = $this->reglas->rondaAbierta($documento, $transicionCodigo)
                ?? throw AprobacionRechazada::sinRonda($definicion->nombreConArticulo());

            $this->reglas->reiniciar($documento, $ronda);

            return $this->reglas->rondaAbierta($documento, $transicionCodigo) ?? throw new LogicException('La ronda nueva no se abrió.');
        }));
    }

    /** Marca revisada una autoaprobación (D6): alguien distinto de quien la firmó la miró después. */
    public function revisarAutoaprobacion(int $firmaId, User $usuario): FirmaAprobacion
    {
        return DB::transaction(function () use ($firmaId, $usuario): FirmaAprobacion {
            $firma = FirmaAprobacion::query()->lockForUpdate()->findOrFail($firmaId);

            if (! $firma->es_autoaprobacion || $firma->revisada_at !== null) {
                throw AprobacionRechazada::autoaprobacion('Esa firma no es una autoaprobación por revisar.');
            }

            if ($firma->user_id === $usuario->id) {
                throw AprobacionRechazada::autoaprobacion('Una autoaprobación la revisa otra persona.');
            }

            $firma->update(['revisada_por_user_id' => $usuario->id, 'revisada_at' => now()]);

            return $firma;
        });
    }

    public function crearRegla(DatosRegla $datos): ReglaAprobacion
    {
        $this->validarRegla($datos);

        return DB::transaction(function () use ($datos): ReglaAprobacion {
            $regla = ReglaAprobacion::query()->create($this->columnas($datos) + ['es_activa' => true]);
            $this->guardarPasos($regla, $datos);

            return $regla;
        });
    }

    /** Cambia la regla y sus pasos. Las rondas ya abiertas siguen con las firmas que se calcularon al pedirlas (D5). */
    public function editarRegla(int $reglaId, DatosRegla $datos, int $versionLeida): ReglaAprobacion
    {
        $this->validarRegla($datos);

        return DB::transaction(function () use ($reglaId, $datos, $versionLeida): ReglaAprobacion {
            $regla = $this->reglaBloqueada($reglaId, $versionLeida);
            $regla->fill($this->columnas($datos));
            $regla->version++;
            $regla->save();
            $regla->pasos()->get()->each(fn (PasoReglaAprobacion $paso): ?bool => $paso->delete());
            $this->guardarPasos($regla, $datos);

            return $regla;
        });
    }

    public function activarRegla(int $reglaId, int $versionLeida, bool $activa): ReglaAprobacion
    {
        return DB::transaction(function () use ($reglaId, $versionLeida, $activa): ReglaAprobacion {
            $regla = $this->reglaBloqueada($reglaId, $versionLeida);
            $regla->es_activa = $activa;
            $regla->version++;
            $regla->save();

            return $regla;
        });
    }

    /**
     * Bloquea el documento y la ronda, revisa que nada cambió y entrega la firma que sigue (D9).
     *
     * @template T
     *
     * @param  callable(Model&DocumentoConEstados, RondaAprobacion, FirmaAprobacion, string): T  $hacer
     * @return T
     */
    private function conLaFirmaQueSigue(string $documentoType, int $documentoId, string $transicionCodigo, User $usuario, ?int $versionLeida, callable $hacer): mixed
    {
        $reiniciada = new \stdClass;

        $resultado = DB::transaction(fn (): mixed => $this->auditoria->comoUsuario($usuario, function () use ($documentoType, $documentoId, $transicionCodigo, $versionLeida, $hacer, $reiniciada): mixed {
            $definicion = $this->definicionDe($documentoType);
            $modelo = $definicion->modelo();
            /** @var Model&DocumentoConEstados $documento */
            $documento = $modelo::query()->lockForUpdate()->findOrFail($documentoId);
            $nombre = ucfirst($definicion->nombreConArticulo());

            // Plan 2.22, P6: se firma lo que se vio. Si el documento cambió desde que se abrió la bandeja, no se firma ni
            // se toca la ronda: se avisa y se vuelve a mirar.
            if ($versionLeida !== null && $versionLeida !== (int) $documento->getAttribute('version')) {
                throw AprobacionRechazada::cambioDesdeQueLoAbriste($nombre);
            }

            $ronda = RondaAprobacion::query()
                ->whereKey($this->reglas->rondaAbierta($documento, $transicionCodigo)?->id)
                ->lockForUpdate()
                ->first() ?? throw AprobacionRechazada::sinRonda($definicion->nombreConArticulo());

            // El documento cambió desde que se pidió (D5): la ronda empieza de cero y esta firma no se da. Se confirma el
            // reinicio y después se avisa.
            if ($ronda->version_documento !== (int) $documento->getAttribute('version')) {
                $this->reglas->reiniciar($documento, $ronda);

                return $reiniciada;
            }

            $firma = $ronda->firmas()->where('estado', EstadoFirmaAprobacion::Pendiente)->first()
                ?? throw AprobacionRechazada::sinRonda($definicion->nombreConArticulo());
            $paso = $this->resolutor->descripcion($firma, $ronda);

            if ($this->resolutor->titular($firma, $ronda) === null) {
                throw AprobacionRechazada::sinFirmante($paso);
            }

            return $hacer($documento, $ronda, $firma, $paso);
        }));

        if ($resultado === $reiniciada) {
            throw AprobacionRechazada::documentoCambio(ucfirst($this->definicionDe($documentoType)->nombreConArticulo()));
        }

        return $resultado;
    }

    private function aprobar(Model&DocumentoConEstados $documento, RondaAprobacion $ronda, FirmaAprobacion $firma, User $usuario, ?string $motivoAutoaprobacion): FirmaAprobacion
    {
        $firma->update([
            'estado' => EstadoFirmaAprobacion::Aprobada,
            'user_id' => $usuario->id,
            'firmado_at' => now(),
            'es_autoaprobacion' => $motivoAutoaprobacion !== null,
            'motivo_autoaprobacion' => $motivoAutoaprobacion,
        ]);

        if (! $ronda->firmas()->where('estado', EstadoFirmaAprobacion::Pendiente)->exists()) {
            $this->transiciones->transicionarComoSistema($documento, $ronda->transicion_codigo, $usuario);
        }

        return $firma;
    }

    private function validarRegla(DatosRegla $datos): void
    {
        $definicion = $this->registro->definicionDe($datos->documentoType)
            ?? throw AprobacionRechazada::regla('Ese tipo de documento no existe.');
        $transicion = $definicion->transicion($datos->transicionCodigo);

        if ($transicion === null || ! $transicion->requiereAprobacion) {
            throw AprobacionRechazada::regla("«{$datos->transicionCodigo}» no es un paso de {$definicion->nombreConArticulo()} que requiera aprobación.");
        }

        if (trim($datos->nombre) === '') {
            throw AprobacionRechazada::regla('Ponle un nombre a la regla.');
        }

        foreach ([$datos->montoMasDe, $datos->montoHasta, $datos->topeAutoaprobacion] as $monto) {
            if ($monto !== null && preg_match('/^\d{1,12}(\.\d{1,2})?$/', $monto) !== 1) {
                throw AprobacionRechazada::regla('Los montos van en soles, sin signo y con hasta dos decimales.');
            }
        }

        // Planes 2.2 y 2.6 (desarrollador, 2026-11-06): el exceso y el gasto no presupuestado los firma siempre otra
        // persona; la base lo exige igual.
        if (! $datos->condicion->admiteAutoaprobacion() && $datos->topeAutoaprobacion !== null) {
            $que = match ($datos->condicion) {
                CondicionRegla::ExcesoDePartida => 'El exceso de partida',
                CondicionRegla::MargenBajoElMinimo => 'El margen bajo el mínimo',
                default => 'El gasto no presupuestado',
            };
            throw AprobacionRechazada::regla("{$que} no admite autoaprobación: lo firma siempre otra persona. Deja el tope vacío.");
        }

        if ($datos->topeAutoaprobacion !== null && bccomp($datos->topeAutoaprobacion, '0', 2) <= 0) {
            throw AprobacionRechazada::regla('El tope de autoaprobación tiene que ser mayor que cero; déjalo vacío si no se permite.');
        }

        if ($datos->montoHasta !== null && bccomp($datos->montoHasta, $datos->montoMasDe ?? '0', 2) <= 0) {
            throw AprobacionRechazada::regla('«Hasta» tiene que ser mayor que «más de».');
        }

        if ($datos->pasos === []) {
            throw AprobacionRechazada::regla('Una regla necesita al menos una firma.');
        }

        foreach ($datos->pasos as $paso) {
            if (preg_match('/^(documento|unidad:[1-9]\d*)$/', $paso) !== 1) {
                throw AprobacionRechazada::regla("Paso no válido: «{$paso}».");
            }
        }

        foreach ($datos->pasosLeidos() as $paso) {
            if ($paso['unidad'] !== null && ! UnidadOrganizativa::query()->whereKey($paso['unidad'])->where('es_activa', true)->exists()) {
                throw AprobacionRechazada::regla('Una de las unidades no existe o está desactivada.');
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function columnas(DatosRegla $datos): array
    {
        return [
            'nombre' => trim($datos->nombre),
            'documento_type' => $datos->documentoType,
            'transicion_codigo' => $datos->transicionCodigo,
            'monto_mas_de' => $datos->montoMasDe,
            'monto_hasta' => $datos->montoHasta,
            'tope_autoaprobacion' => $datos->topeAutoaprobacion,
            'condicion' => $datos->condicion,
        ];
    }

    private function guardarPasos(ReglaAprobacion $regla, DatosRegla $datos): void
    {
        foreach ($datos->pasosLeidos() as $i => $paso) {
            PasoReglaAprobacion::query()->create([
                'regla_aprobacion_id' => $regla->id,
                'orden' => $i + 1,
                'firmante' => $paso['firmante'],
                'unidad_organizativa_id' => $paso['unidad'],
            ]);
        }
    }

    private function reglaBloqueada(int $reglaId, int $versionLeida): ReglaAprobacion
    {
        $regla = ReglaAprobacion::query()->lockForUpdate()->findOrFail($reglaId);

        if ($regla->version !== $versionLeida) {
            throw VersionDesactualizada::de('la regla de aprobación');
        }

        return $regla;
    }

    private function definicionDe(Model|string $documento): DefinicionDocumento
    {
        $tipo = $documento instanceof Model ? $documento->getMorphClass() : $documento;

        if (Relation::getMorphedModel($tipo) === null) {
            throw new LogicException("El tipo de documento «{$tipo}» no está en el morph map.");
        }

        return $this->registro->definicionDe($tipo)
            ?? throw new LogicException("El documento {$tipo} no está registrado en RegistroDocumentos.");
    }
}
