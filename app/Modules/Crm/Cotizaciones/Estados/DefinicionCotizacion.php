<?php

declare(strict_types=1);

namespace App\Modules\Crm\Cotizaciones\Estados;

use App\Modules\Crm\Cotizaciones\Condiciones\MargenPermiteEmitir;
use App\Modules\Crm\Cotizaciones\Condiciones\MargenPideAprobacion;
use App\Modules\Crm\Cotizaciones\Condiciones\OportunidadAbierta;
use App\Modules\Crm\Cotizaciones\Condiciones\OportunidadNoGanada;
use App\Modules\Crm\Cotizaciones\Condiciones\SobreLaOportunidadPropia;
use App\Modules\Crm\Cotizaciones\Condiciones\TieneLineas;
use App\Modules\Crm\Cotizaciones\Models\Cotizacion;
use App\Modules\Crm\Cotizaciones\Services\CalculoCotizacion;
use App\Modules\Crm\Oportunidades\Services\OportunidadesService;
use App\Modules\Nucleo\Aprobaciones\MontoAprobable;
use App\Modules\Nucleo\Enums\CondicionRegla;
use App\Modules\Nucleo\Enums\TonoEstado;
use App\Modules\Nucleo\Estados\DefinicionDocumento;
use App\Modules\Nucleo\Estados\EstadoDefinido;
use App\Modules\Nucleo\Estados\TransicionDefinida;
use App\Modules\Nucleo\Models\Moneda;
use App\Modules\Nucleo\Numeracion\FormatoNumeracion;
use App\Modules\Nucleo\Numeracion\NumeroPorDueno;
use Illuminate\Database\Eloquent\Model;

/**
 * La cotización como documento (plan 2.22, D5). Cada fila es una versión; las versiones comparten número (D2). Se arma
 * en borrador; bajo el margen mínimo pide aprobación; al emitirse se congela y sale al cliente: ya no se edita ni se
 * anula (se hace otra versión, el cliente la rechaza o vence). Solo una versión queda aceptada (D7).
 */
final class DefinicionCotizacion extends DefinicionDocumento
{
    public const string CLAVE = 'crm.cotizaciones.cotizacion';

    public const string BORRADOR = 'borrador';

    public const string POR_APROBAR = 'por_aprobar';

    public const string APROBADA = 'aprobada';

    public const string EMITIDA = 'emitida';

    public const string ACEPTADA = 'aceptada';

    public const string RECHAZADA = 'rechazada';

    public const string VENCIDA = 'vencida';

    public const string REEMPLAZADA = 'reemplazada';

    public const string ANULADA = 'anulada';

    public const string SOLICITAR_APROBACION = 'solicitar_aprobacion';

    public const string APROBAR = 'aprobar';

    public const string DEVOLVER = 'devolver';

    public const string REABRIR = 'reabrir';

    public const string EMITIR = 'emitir';

    public const string ACEPTAR = 'aceptar';

    public const string RECHAZAR = 'rechazar';

    public const string VENCER = 'vencer';

    public const string REEMPLAZAR = 'reemplazar';

    public const string ANULAR = 'anular';

    public const string ANULAR_ACEPTACION = 'anular_aceptacion';

    public const string EFECTO_CONGELAR = 'crm.cotizaciones.congelar-al-emitir';

    public const string EFECTO_OFERTAR_PRESUPUESTO = 'crm.cotizaciones.ofertar-presupuesto';

    public const string EFECTO_REEMPLAZAR_ANTERIOR = 'crm.cotizaciones.reemplazar-version-anterior';

    public const string EFECTO_GUARDAR_PDF = 'crm.cotizaciones.guardar-pdf-al-emitir';

    public const string EFECTO_MARCAR_ACEPTADA = 'crm.cotizaciones.marcar-aceptada';

    public const string EFECTO_QUITAR_ACEPTACION = 'crm.cotizaciones.quitar-aceptacion';

    /** Las versiones desde las que sale una versión nueva (D5): ya salieron al cliente y no están aceptadas. */
    public const array SALE_VERSION_NUEVA = [self::EMITIDA, self::RECHAZADA, self::VENCIDA];

    /** En curso: todavía no salió al cliente (D5). */
    public const array EN_CURSO = [self::BORRADOR, self::POR_APROBAR, self::APROBADA];

    public function modelo(): string
    {
        return Cotizacion::class;
    }

    public function clave(): string
    {
        return self::CLAVE;
    }

    public function nombre(): string
    {
        return 'cotización';
    }

    public function articulo(): string
    {
        return 'la';
    }

    public function numeracion(): FormatoNumeracion
    {
        return NumeroPorDueno::formato('COT');
    }

    /** MV-007: la cotización se numera dentro de su oportunidad: «OP-2026-0007 · COT-01». */
    public function ambitoDeNumeracion(Model $documento): string
    {
        return NumeroPorDueno::ambito(self::codigoDeLaOportunidad($documento));
    }

    public function numeroVisible(string $numero, Model $documento): string
    {
        return NumeroPorDueno::visible($numero, self::codigoDeLaOportunidad($documento), 'COT');
    }

    private static function codigoDeLaOportunidad(Model $documento): string
    {
        /** @var Cotizacion $documento */
        return app(OportunidadesService::class)->resumen((int) $documento->oportunidad_id)->numero;
    }

    public function versionesCompartenNumero(): bool
    {
        return true;
    }

    public function identificador(Model $documento): string
    {
        return $documento->getAttribute('numero').' v'.$documento->getAttribute('numero_version');
    }

    /** D14: el total con IGV en su moneda; el núcleo lo pasa a soles con el TC del día («las reglas van en soles»). */
    public function montoParaAprobacion(Model $documento): MontoAprobable
    {
        /** @var Cotizacion $documento */
        return new MontoAprobable((string) $documento->total, (string) Moneda::query()->whereKey($documento->moneda_id)->value('codigo'));
    }

    /** D14: entra la regla «margen bajo el mínimo» cuando el margen de la versión queda bajo el mínimo. */
    public function condicionesDeAprobacion(Model $documento): array
    {
        /** @var Cotizacion $documento */
        return MargenPideAprobacion::bajoElMinimo($documento) ? [CondicionRegla::MargenBajoElMinimo] : [];
    }

    public function transicionAlObservar(string $transicionConAprobacion): ?string
    {
        return $transicionConAprobacion === self::APROBAR ? self::DEVOLVER : null;
    }

    public function estados(): array
    {
        return [
            new EstadoDefinido(self::BORRADOR, 'Borrador', TonoEstado::Neutro, esInicial: true, esEditable: true),
            new EstadoDefinido(self::POR_APROBAR, 'Por aprobar', TonoEstado::PorAprobar),
            new EstadoDefinido(self::APROBADA, 'Aprobada', TonoEstado::Aprobado),
            new EstadoDefinido(self::EMITIDA, 'Emitida', TonoEstado::EnCurso),
            new EstadoDefinido(self::ACEPTADA, 'Aceptada', TonoEstado::Aprobado),
            new EstadoDefinido(self::RECHAZADA, 'Rechazada', TonoEstado::Cerrado),
            new EstadoDefinido(self::VENCIDA, 'Vencida', TonoEstado::Cerrado),
            new EstadoDefinido(self::REEMPLAZADA, 'Reemplazada', TonoEstado::Cerrado, esFinal: true),
            new EstadoDefinido(self::ANULADA, 'Anulada', TonoEstado::Anulado, esFinal: true, esCancelacion: true),
        ];
    }

    public function transiciones(): array
    {
        $propia = [SobreLaOportunidadPropia::class, OportunidadAbierta::class];

        return [
            new TransicionDefinida(self::SOLICITAR_APROBACION, 'Solicitar aprobación', self::BORRADOR, self::POR_APROBAR,
                condiciones: [...$propia, TieneLineas::class, MargenPideAprobacion::class], accionPropia: true),
            new TransicionDefinida(self::APROBAR, 'Aprobar', self::POR_APROBAR, self::APROBADA, requiereAprobacion: true),
            // Al observar la aprobación (comentario obligatorio), el sistema la devuelve a borrador.
            new TransicionDefinida(self::DEVOLVER, 'Devolver a borrador', self::POR_APROBAR, self::BORRADOR, esDelSistema: true),
            // D14: la aprobada no se edita; para cambiarla, vuelve a borrador y se pide otra vez.
            new TransicionDefinida(self::REABRIR, 'Volver a borrador', self::APROBADA, self::BORRADOR, requiereMotivo: true, condiciones: $propia),
            new TransicionDefinida(self::EMITIR, 'Emitir', [self::BORRADOR, self::APROBADA], self::EMITIDA,
                efectos: [self::EFECTO_CONGELAR, self::EFECTO_OFERTAR_PRESUPUESTO, self::EFECTO_REEMPLAZAR_ANTERIOR, self::EFECTO_GUARDAR_PDF],
                condiciones: [...$propia, TieneLineas::class, MargenPermiteEmitir::class], accionPropia: true),
            new TransicionDefinida(self::ACEPTAR, 'Aceptar', self::EMITIDA, self::ACEPTADA,
                efectos: [self::EFECTO_MARCAR_ACEPTADA], condiciones: $propia, accionPropia: true),
            new TransicionDefinida(self::RECHAZAR, 'Rechazada por el cliente', self::EMITIDA, self::RECHAZADA, requiereMotivo: true,
                condiciones: [SobreLaOportunidadPropia::class]),
            new TransicionDefinida(self::VENCER, 'Vencer', self::EMITIDA, self::VENCIDA, esDelSistema: true),
            new TransicionDefinida(self::REEMPLAZAR, 'Reemplazar', self::SALE_VERSION_NUEVA, self::REEMPLAZADA, esDelSistema: true),
            new TransicionDefinida(self::ANULAR, 'Anular', self::EN_CURSO, self::ANULADA, requiereMotivo: true,
                condiciones: [SobreLaOportunidadPropia::class]),
            // D5: solo Gerencia Comercial (su permiso); impedida si la oportunidad ya está ganada. Acción propia: el servicio
            // bloquea la oportunidad antes (no se cruza con ganar). No es una cancelación: el efecto de «aceptar» queda
            // aplicado en el registro y lo deshace `QuitarAceptacion`; la aceptada y la emitida nunca se anulan.
            new TransicionDefinida(self::ANULAR_ACEPTACION, 'Anular aceptación', self::ACEPTADA, self::EMITIDA, requiereMotivo: true,
                efectos: [self::EFECTO_QUITAR_ACEPTACION], condiciones: [OportunidadNoGanada::class], accionPropia: true),
        ];
    }

    /** El margen de la versión, con el cálculo de sus totales (para las condiciones). */
    public static function margen(Cotizacion $cotizacion): ?string
    {
        return $cotizacion->margen_porcentaje === null ? null : CalculoCotizacion::redondear((string) $cotizacion->margen_porcentaje, 2);
    }
}
