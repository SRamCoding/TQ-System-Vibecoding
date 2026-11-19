<?php

declare(strict_types=1);

namespace Tests\Modules\Nucleo\Fixtures;

use App\Modules\Nucleo\Aprobaciones\MontoAprobable;
use App\Modules\Nucleo\Enums\CondicionRegla;
use App\Modules\Nucleo\Enums\TonoEstado;
use App\Modules\Nucleo\Estados\DefinicionDocumento;
use App\Modules\Nucleo\Estados\EstadoDefinido;
use App\Modules\Nucleo\Estados\RevisionAntesDeAnular;
use App\Modules\Nucleo\Estados\TransicionDefinida;
use App\Modules\Nucleo\Numeracion\FormatoNumeracion;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * Máquina de estados del documento de prueba. Cada prueba puede cambiar sus estados o transiciones para simular
 * una definición que cambió entre dos despliegues.
 */
final class DefinicionPrueba extends DefinicionDocumento
{
    /**
     * @param  list<EstadoDefinido>|null  $estados
     * @param  list<TransicionDefinida>|null  $transiciones
     * @param  list<class-string<RevisionAntesDeAnular>>  $revisiones
     * @param  list<CondicionRegla>  $condiciones  las que dice cumplir al pedir aprobación (plan 2.22, D14)
     */
    public function __construct(
        private ?array $estados = null,
        private ?array $transiciones = null,
        private array $revisiones = [],
        private ?FormatoNumeracion $numeracion = new FormatoNumeracion('DPR'),
        private ?string $alObservar = null,
        private bool $conMonto = true,
        private array $condiciones = [],
    ) {}

    public function condicionesDeAprobacion(Model $documento): array
    {
        return $this->condiciones;
    }

    /** Su monto, en soles o en la moneda que diga la prueba (`moneda`, si la tabla la tiene). */
    public function montoParaAprobacion(Model $documento): ?MontoAprobable
    {
        if (! $this->conMonto) {
            return null;
        }

        return new MontoAprobable((string) $documento->getAttribute('monto'), (string) ($documento->getAttribute('moneda') ?? 'PEN'));
    }

    public function unidadDeAprobacion(Model $documento): ?int
    {
        $unidad = $documento->getAttribute('unidad_id');

        return $unidad === null ? null : (int) $unidad;
    }

    public function transicionAlObservar(string $transicionConAprobacion): ?string
    {
        return $this->alObservar;
    }

    public function numeracion(): ?FormatoNumeracion
    {
        return $this->numeracion;
    }

    /** La fecha del documento, si se la dieron (para probar el cambio de año); si no, hoy. */
    public function fechaDeNumeracion(Model $documento): CarbonImmutable
    {
        $fecha = $documento->getAttribute('fecha');

        return $fecha === null ? CarbonImmutable::now() : CarbonImmutable::parse((string) $fecha, 'America/Lima');
    }

    /** Una serie por unidad, para probar los ámbitos (D4). */
    public function ambitoDeNumeracion(Model $documento): string
    {
        $unidad = $documento->getAttribute('unidad_id');

        return $unidad === null ? '' : "U{$unidad}";
    }

    public function modelo(): string
    {
        return DocumentoPrueba::class;
    }

    public function clave(): string
    {
        return 'nucleo.prueba';
    }

    public function nombre(): string
    {
        return 'documento de prueba';
    }

    public function estados(): array
    {
        return $this->estados ?? self::estadosBase();
    }

    public function transiciones(): array
    {
        return $this->transiciones ?? self::transicionesBase();
    }

    public function revisionesAntesDeAnular(): array
    {
        return $this->revisiones;
    }

    /**
     * @return list<EstadoDefinido>
     */
    public static function estadosBase(): array
    {
        return [
            new EstadoDefinido('borrador', 'Borrador', TonoEstado::Neutro, esInicial: true, esEditable: true),
            new EstadoDefinido('por_aprobar', 'Por aprobar', TonoEstado::PorAprobar),
            new EstadoDefinido('aprobado', 'Aprobado', TonoEstado::Aprobado),
            new EstadoDefinido('enviado', 'Enviado', TonoEstado::EnCurso),
            new EstadoDefinido('anulado', 'Anulado', TonoEstado::Anulado, esFinal: true, esCancelacion: true),
        ];
    }

    /**
     * @return list<TransicionDefinida>
     */
    public static function transicionesBase(): array
    {
        return [
            new TransicionDefinida('enviar', 'Enviar', 'borrador', 'enviado', condiciones: [MontoHastaMil::class]),
            new TransicionDefinida('pedir_aprobacion', 'Pedir aprobación', 'borrador', 'por_aprobar'),
            new TransicionDefinida('aprobar', 'Aprobar', 'por_aprobar', 'aprobado', requiereAprobacion: true),
            new TransicionDefinida('cerrar_por_sistema', 'Cerrar', 'aprobado', 'enviado', esDelSistema: true),
            new TransicionDefinida('anular', 'Anular', ['borrador', 'enviado'], 'anulado', requiereMotivo: true),
        ];
    }
}
