<?php

declare(strict_types=1);

namespace Tests\Modules\Crm\Cotizaciones\Fixtures;

use App\Modules\Crm\Cotizaciones\CotizacionesServiceProvider;
use App\Modules\Crm\Cotizaciones\Estados\DefinicionCotizacion;
use App\Modules\Crm\Cotizaciones\Models\Cotizacion;
use App\Modules\Crm\Cotizaciones\Services\CotizacionesService;
use App\Modules\Crm\Cotizaciones\Services\DatosCotizacion;
use App\Modules\Crm\Cotizaciones\Services\DatosLinea;
use App\Modules\Crm\Cuentas\Models\Cuenta;
use App\Modules\Crm\Oportunidades\Models\Oportunidad;
use App\Modules\Nucleo\Enums\OrigenTipoCambio;
use App\Modules\Nucleo\Models\Motivo;
use App\Modules\Nucleo\Models\UnidadMedida;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Services\AdjuntosService;
use App\Modules\Nucleo\Services\TipoCambioService;
use App\Modules\Nucleo\Tiempo\Calendario;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\Modules\Crm\Oportunidades\Fixtures\OportunidadesDePrueba;

/**
 * Cotizaciones de prueba (plan 2.22): un ejecutivo con los permisos de cotizar, su oportunidad y el tipo de cambio de
 * hoy (emitir lo congela). Una clase, no funciones globales: cada archivo de pruebas pasa solo. Nombres inventados.
 */
final class CotizacionesDePrueba
{
    /** Los permisos del ejecutivo comercial (D21), sin «todas» ni «anular aceptación». */
    public const array PERMISOS_EJECUTIVO = [
        CotizacionesService::PERMISO_CREAR, CotizacionesService::PERMISO_EDITAR, CotizacionesService::PERMISO_NUEVA_VERSION,
        DefinicionCotizacion::CLAVE.'.solicitar_aprobacion', DefinicionCotizacion::CLAVE.'.reabrir', DefinicionCotizacion::CLAVE.'.emitir',
        DefinicionCotizacion::CLAVE.'.aceptar', DefinicionCotizacion::CLAVE.'.rechazar', DefinicionCotizacion::CLAVE.'.anular',
    ];

    public function __construct(public User $ejecutivo, public Oportunidad $oportunidad) {}

    /** Con NucleoSeeder ya sembrado. Emitir guarda el PDF como adjunto (plan 2.22c): el disco de adjuntos, de prueba. */
    public static function nueva(): self
    {
        Storage::fake(AdjuntosService::DISCO);
        $ejecutivo = User::factory()->create();

        foreach (self::PERMISOS_EJECUTIVO as $permiso) {
            $ejecutivo->givePermissionTo(Permission::findOrCreate($permiso));
        }

        $oportunidad = OportunidadesDePrueba::crear(Cuenta::factory()->create()->id, ['ejecutivo_user_id' => $ejecutivo->id]);
        self::tipoDeCambioDeHoy();

        return new self($ejecutivo, $oportunidad);
    }

    public static function tipoDeCambioDeHoy(string $venta = '3.7500'): void
    {
        $hoy = CarbonImmutable::parse(Calendario::hoy(), 'America/Lima');

        if (! app(TipoCambioService::class)->hayDe($hoy)) {
            app(TipoCambioService::class)->registrar('USD', $hoy, '3.7400', $venta, OrigenTipoCambio::Manual);
        }
    }

    public function servicio(): CotizacionesService
    {
        return app(CotizacionesService::class);
    }

    public function crear(?DatosCotizacion $datos = null): Cotizacion
    {
        return $this->servicio()->crear($this->oportunidad->id, $datos ?? new DatosCotizacion, $this->ejecutivo);
    }

    /** Una línea libre, «por catalogar». */
    public function libre(Cotizacion $c, string $descripcion, string $cantidad, string $precio, string $costo, string $descuento = '0'): Cotizacion
    {
        $this->servicio()->agregarLinea($c->id, $c->refresh()->version, new DatosLinea($cantidad, $precio, $costo, $descuento, descripcion: $descripcion, unidadMedidaId: self::unidad()), $this->ejecutivo);

        return $c->refresh();
    }

    /** COT v1 con las cuatro líneas del diseño (D17.1): margen 19.40 %. */
    public function delDiseno(): Cotizacion
    {
        $c = $this->crear();
        $this->libre($c, 'Cámara IP bullet 4 MP', '24', '1180.00', '807.12', '5');
        $this->libre($c, 'Switch PoE 24 puertos', '3', '4250.00', '3104.20', '12');
        $this->libre($c, 'NVR 32 canales', '1', '6900.00', '5188.80', '20');

        return $this->libre($c, 'Instalación y puesta en marcha', '1', '8000.00', '7752.78');
    }

    /** Una versión con margen sobre el mínimo, emitida. */
    public function emitida(): Cotizacion
    {
        $c = $this->libre($this->crear(), 'Cableado estructurado', '10', '100.00', '60.00');

        return $this->servicio()->emitir($c->id, $c->version, null, $this->ejecutivo);
    }

    public static function unidad(): int
    {
        return (int) UnidadMedida::query()->where('codigo', 'NIU')->value('id');
    }

    public static function motivo(string $familia, string $codigo): int
    {
        return (int) Motivo::query()->whereHas('tipoMotivo', fn ($q) => $q->where('codigo', $familia))->where('codigo', $codigo)->value('id');
    }

    public static function motivoVersion(): int
    {
        return self::motivo(CotizacionesServiceProvider::FAMILIA_NUEVA_VERSION, 'pedido_del_cliente');
    }
}
