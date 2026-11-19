<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Aprobaciones;

use App\Modules\Nucleo\Enums\CondicionRegla;
use App\Modules\Nucleo\Enums\EstadoFirmaAprobacion;
use App\Modules\Nucleo\Enums\EstadoRondaAprobacion;
use App\Modules\Nucleo\Enums\FirmanteAprobacion;
use App\Modules\Nucleo\Estados\AprobacionDeTransicion;
use App\Modules\Nucleo\Estados\DefinicionDocumento;
use App\Modules\Nucleo\Estados\DocumentoConEstados;
use App\Modules\Nucleo\Estados\RegistroDocumentos;
use App\Modules\Nucleo\Excepciones\AprobacionRechazada;
use App\Modules\Nucleo\Imputacion\FuenteDeDestinos;
use App\Modules\Nucleo\Imputacion\LineaDeCosto;
use App\Modules\Nucleo\Models\FirmaAprobacion;
use App\Modules\Nucleo\Models\ReglaAprobacion;
use App\Modules\Nucleo\Models\RondaAprobacion;
use App\Modules\Nucleo\Models\TransicionEstado;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Services\ImputacionService;
use App\Modules\Nucleo\Services\TipoCambioService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * Las aprobaciones vistas desde las transiciones (plan 1.7). Al llegar el documento a un estado del que sale una
 * transición con aprobación, abre una ronda con las firmas que piden las reglas activas que calzan con el monto en soles
 * (todas acumuladas, en orden: D2 y D3), atada a la versión del documento (D5). Al salir de ese estado la cierra:
 * completa si salió por la transición aprobada, anulada si no. Una transición con aprobación queda pendiente mientras
 * falte alguna firma.
 */
final readonly class ReglasDeAprobacion implements AprobacionDeTransicion
{
    public function __construct(
        private RegistroDocumentos $registro,
        private TipoCambioService $tiposCambio,
        private ResolutorDeFirmante $resolutor,
    ) {}

    public function pendiente(Model $documento, TransicionEstado $transicion): ?string
    {
        $definicion = $this->definicionDe($documento);
        $ronda = $this->rondaAbierta($documento, $transicion->codigo);

        if ($ronda === null) {
            return "No hay una aprobación pedida para {$definicion->nombreConArticulo()}.";
        }

        if ($ronda->version_documento !== (int) $documento->getAttribute('version')) {
            return AprobacionRechazada::documentoCambio(ucfirst($definicion->nombreConArticulo()))->getMessage();
        }

        $firmas = $ronda->firmas()->get();

        if ($firmas->isEmpty()) {
            return sprintf(
                'No hay reglas de aprobación para %s por S/ %s: un administrador debe configurarlas en Configuración → Aprobaciones y recalcular sus firmas.',
                $definicion->nombreConArticulo(),
                number_format((float) $ronda->monto_soles, 2),
            );
        }

        $falta = $firmas->first(fn (FirmaAprobacion $firma): bool => $firma->estado !== EstadoFirmaAprobacion::Aprobada);

        return $falta === null ? null : 'Falta la firma de '.$this->resolutor->descripcion($falta, $ronda).'.';
    }

    public function alCambiarEstado(Model&DocumentoConEstados $documento, ?TransicionEstado $hecha, User $quien): void
    {
        // Si llegó aquí por una aprobación, la ronda siguiente la pide la misma persona que pidió la anterior: no el
        // último firmante, que entonces no podría firmar la suya.
        $solicitante = $this->cerrarLasQueNoSiguen($documento, $hecha);
        $quien = $solicitante === null ? $quien : User::query()->findOrFail($solicitante);

        $conAprobacion = TransicionEstado::query()
            ->where('documento_type', $documento->getMorphClass())
            ->where('desde_estado_id', $documento->getAttribute('estado_id'))
            ->where('requiere_aprobacion', true)
            ->where('es_activa', true)
            ->orderBy('orden')
            ->get();

        foreach ($conAprobacion as $transicion) {
            $this->abrir($documento, $transicion, $quien);
        }
    }

    /** La ronda abierta del documento para esa transición, si la hay. */
    public function rondaAbierta(Model $documento, string $transicionCodigo): ?RondaAprobacion
    {
        return RondaAprobacion::query()
            ->where('aprobable_type', $documento->getMorphClass())
            ->where('aprobable_id', $documento->getKey())
            ->where('transicion_codigo', $transicionCodigo)
            ->where('estado', EstadoRondaAprobacion::Abierta)
            ->first();
    }

    /**
     * Las reglas activas que calzan, en el orden en que firman: primero las de montos más bajos (la jefatura antes que
     * la gerencia, D2).
     *
     * @param  list<CondicionRegla>  $propias  las que el documento dice cumplir (`condicionesDeAprobacion`)
     * @return list<ReglaAprobacion>
     */
    public function reglasQueCalzan(string $documentoType, string $transicionCodigo, string $montoSoles, bool $conExceso = false, bool $conNoPresupuestado = false, array $propias = []): array
    {
        // Plan 2.2, D5: la del exceso de partida entra solo si alguna línea excede; plan 2.6, D2: la del gasto no
        // presupuestado, solo si alguna línea va a la PC-99; plan 2.22, D14: las propias del documento, si las cumple.
        $condiciones = array_values(array_unique(array_filter([
            CondicionRegla::Ninguna,
            $conExceso ? CondicionRegla::ExcesoDePartida : null,
            $conNoPresupuestado ? CondicionRegla::GastoNoPresupuestado : null,
            ...$propias,
        ]), SORT_REGULAR));

        return ReglaAprobacion::query()
            ->where('documento_type', $documentoType)
            ->where('transicion_codigo', $transicionCodigo)
            ->where('es_activa', true)
            ->whereIn('condicion', $condiciones)
            ->where(fn ($query) => $query->whereNull('monto_mas_de')->orWhere('monto_mas_de', '<', $montoSoles))
            ->where(fn ($query) => $query->whereNull('monto_hasta')->orWhere('monto_hasta', '>=', $montoSoles))
            ->orderByRaw('COALESCE(monto_mas_de, 0)')
            ->orderBy('id')
            ->with('pasos')
            ->get()
            ->all();
    }

    /**
     * Cierra la ronda abierta sin efecto y abre otra con las reglas y la versión de ahora (D5): cuando el documento
     * cambió, o cuando no había reglas al pedirla. La pide la misma persona.
     */
    public function reiniciar(Model&DocumentoConEstados $documento, RondaAprobacion $ronda): void
    {
        $ronda->update(['estado' => EstadoRondaAprobacion::Anulada, 'cerrada_at' => now()]);
        $ronda->firmas()->where('estado', EstadoFirmaAprobacion::Pendiente)->get()
            ->each(fn (FirmaAprobacion $firma): bool => $firma->update(['estado' => EstadoFirmaAprobacion::Anulada]));

        $transicion = TransicionEstado::query()
            ->where('documento_type', $documento->getMorphClass())
            ->where('desde_estado_id', $documento->getAttribute('estado_id'))
            ->where('codigo', $ronda->transicion_codigo)
            ->where('es_activa', true)
            ->firstOrFail();

        $this->abrir($documento, $transicion, User::query()->findOrFail($ronda->solicitante_user_id));
    }

    private function abrir(Model&DocumentoConEstados $documento, TransicionEstado $transicion, User $quien): void
    {
        $definicion = $this->definicionDe($documento);
        $monto = $definicion->montoParaAprobacion($documento)
            ?? throw new LogicException("{$definicion->nombreConArticulo()} tiene un paso con aprobación y no declara su monto (montoParaAprobacion): las reglas no sabrían cuáles calzan.");
        $montoSoles = $this->enSoles($monto);
        $unidad = $definicion->unidadDeAprobacion($documento);
        $origenes = array_map(fn (LineaDeCosto $linea): Model => $linea->origen, $definicion->lineasDeCosto($documento));
        $imputaciones = app(ImputacionService::class);
        $conExceso = $imputaciones->algunaExcedida($origenes);
        // Sin líneas de costo no se pregunta a Presupuestos: así el núcleo no lo necesita para sus propios documentos.
        $conNoPresupuestado = $origenes !== [] && $imputaciones->algunaNoPresupuestada($origenes, app(FuenteDeDestinos::class));
        $propias = $definicion->condicionesDeAprobacion($documento);
        $reglas = $this->reglasQueCalzan($documento->getMorphClass(), $transicion->codigo, $montoSoles, $conExceso, $conNoPresupuestado, $propias);
        $tiene = fn (CondicionRegla $condicion): bool => collect($reglas)->contains(fn (ReglaAprobacion $regla): bool => $regla->condicion === $condicion);

        // El exceso y el gasto no presupuestado nunca pasan sin su regla: con solo las de monto, se aprobarían sin que
        // nadie los vea aparte.
        if ($conExceso && ! $tiene(CondicionRegla::ExcesoDePartida)) {
            throw AprobacionRechazada::excesoSinRegla();
        }

        if ($conNoPresupuestado && ! $tiene(CondicionRegla::GastoNoPresupuestado)) {
            throw AprobacionRechazada::noPresupuestadoSinRegla();
        }

        foreach ($propias as $condicion) {
            if (! $tiene($condicion)) {
                throw AprobacionRechazada::condicionSinRegla($condicion);
            }
        }

        $ronda = RondaAprobacion::query()->create([
            'aprobable_type' => $documento->getMorphClass(),
            'aprobable_id' => $documento->getKey(),
            'transicion_codigo' => $transicion->codigo,
            'version_documento' => (int) $documento->getAttribute('version'),
            'solicitante_user_id' => $quien->id,
            'monto_soles' => $montoSoles,
            'unidad_organizativa_id' => $unidad,
            'estado' => EstadoRondaAprobacion::Abierta,
        ]);

        $orden = 0;
        $vistos = [];

        foreach ($reglas as $regla) {
            foreach ($regla->pasos as $paso) {
                if ($paso->firmante === FirmanteAprobacion::UnidadDelDocumento && $unidad === null) {
                    throw AprobacionRechazada::sinUnidad($definicion->nombreConArticulo());
                }

                // La misma jefatura en dos reglas firma una sola vez, la nombren como unidad fija o como la del documento.
                // Si una de las dos es de una condición sin autoaprobación (exceso, PC-99), la firma queda con esa: si no,
                // el tope de la regla del monto dejaría autoaprobar lo que nunca se autoaprueba (plan 2.6, D2).
                $clave = $paso->unidad_organizativa_id ?? $unidad;
                if (isset($vistos[$clave])) {
                    if (! $regla->condicion->admiteAutoaprobacion() && $vistos[$clave]['regla']->condicion->admiteAutoaprobacion()) {
                        $vistos[$clave]['firma']->update(['regla_aprobacion_id' => $regla->id]);
                        $vistos[$clave]['regla'] = $regla;
                    }

                    continue;
                }

                $vistos[$clave] = ['regla' => $regla, 'firma' => FirmaAprobacion::query()->create([
                    'ronda_aprobacion_id' => $ronda->id,
                    'regla_aprobacion_id' => $regla->id,
                    'orden' => ++$orden,
                    'firmante' => $paso->firmante,
                    'unidad_organizativa_id' => $paso->unidad_organizativa_id,
                    'estado' => EstadoFirmaAprobacion::Pendiente,
                ])];
            }
        }
    }

    /** Cierra las rondas abiertas del documento; devuelve quien pidió la que se completó, si alguna. */
    private function cerrarLasQueNoSiguen(Model $documento, ?TransicionEstado $hecha): ?int
    {
        $solicitante = null;

        $abiertas = RondaAprobacion::query()
            ->where('aprobable_type', $documento->getMorphClass())
            ->where('aprobable_id', $documento->getKey())
            ->where('estado', EstadoRondaAprobacion::Abierta)
            ->lockForUpdate()
            ->get();

        foreach ($abiertas as $ronda) {
            $completa = $hecha !== null && $hecha->codigo === $ronda->transicion_codigo && $hecha->requiere_aprobacion;
            $solicitante = $completa ? $ronda->solicitante_user_id : $solicitante;
            $ronda->update(['estado' => $completa ? EstadoRondaAprobacion::Completa : EstadoRondaAprobacion::Anulada, 'cerrada_at' => now()]);

            // Una por una, para que cada cambio quede con su autor (ConAuditoria).
            FirmaAprobacion::query()
                ->where('ronda_aprobacion_id', $ronda->id)
                ->where('estado', EstadoFirmaAprobacion::Pendiente)
                ->get()
                ->each(fn (FirmaAprobacion $firma): bool => $firma->update(['estado' => EstadoFirmaAprobacion::Anulada]));
        }

        return $solicitante;
    }

    /** En soles con el tipo de cambio de venta de hoy (D3), redondeado al céntimo (medio hacia arriba). */
    private function enSoles(MontoAprobable $monto): string
    {
        $tc = $this->tiposCambio->del($monto->moneda, CarbonImmutable::now('America/Lima'));

        return bcadd(bcmul($monto->monto, $tc->venta, 6), '0.005', 2);
    }

    private function definicionDe(Model $documento): DefinicionDocumento
    {
        return $this->registro->definicionDe($documento->getMorphClass())
            ?? throw new LogicException("El documento {$documento->getMorphClass()} no está registrado en RegistroDocumentos.");
    }
}
