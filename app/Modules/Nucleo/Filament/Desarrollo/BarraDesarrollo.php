<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Filament\Desarrollo;

use App\Modules\Nucleo\Desarrollo\BasesDeDatos;
use App\Modules\Nucleo\Desarrollo\DatosDemo;
use App\Modules\Nucleo\Desarrollo\ModoDesarrollo;
use App\Modules\Nucleo\Desarrollo\Piezas\EstacionDelInicio;
use App\Modules\Nucleo\Desarrollo\Piezas\GranMomentoDeFecha;
use App\Modules\Nucleo\Desarrollo\RegistroPiezasDecorativas;
use App\Modules\Nucleo\Filament\Pages\VistaPreviaFechas;
use App\Modules\Nucleo\Tiempo\Calendario;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Throwable;

/**
 * La barra fija al pie del modo desarrollo (plan 1.4i, D5): la fecha y hora simuladas, «Volver a hoy», «Vista previa»
 * y «Apagar». También la ventana para cambiar una pieza decorativa (D3): se abre desde la barra o con un clic sobre la
 * pieza en la página (`data-tq-pieza`). Sin el modo, no existe (D7): responde 404 a quien la pida, también por Livewire. Nada pasa por acciones
 * registradas (D6): no toca datos, solo la sesión.
 */
final class BarraDesarrollo extends Component
{
    public string $fecha = '';

    /** La pieza cuya ventana está abierta, o null. */
    public ?string $pieza = null;

    public string $opcion = '';

    /** D7: se revisa en cada petición, no solo al dibujarla. */
    public function boot(): void
    {
        abort_unless(ModoDesarrollo::activo(), 404);
    }

    public function mount(): void
    {
        $this->fecha = ModoDesarrollo::fechaSimulada()?->format('Y-m-d\TH:i') ?? '';
    }

    public function simular(): void
    {
        try {
            $momento = $this->fecha === '' ? null : CarbonImmutable::createFromFormat('Y-m-d\TH:i', $this->fecha, Calendario::ZONA);
        } catch (Throwable) {
            $momento = null;
        }

        ModoDesarrollo::simular($momento ?: null);
        $this->recargar();
    }

    public function volverAHoy(): void
    {
        ModoDesarrollo::simular(null);
        $this->recargar();
    }

    public function abrir(string $pieza): void
    {
        if (app(RegistroPiezasDecorativas::class)->pieza($pieza) === null) {
            return;
        }

        $this->pieza = $pieza;
        $this->opcion = ModoDesarrollo::eleccion($pieza) ?? '';
    }

    public function cerrar(): void
    {
        $this->pieza = null;
        $this->opcion = '';
    }

    /** Solo se guarda un valor que la pieza ofrece. El gran momento no se guarda: se abre en la vista previa. */
    public function aplicar(): void
    {
        $pieza = $this->pieza;

        if ($pieza === null || ! app(RegistroPiezasDecorativas::class)->ofrece($pieza, $this->opcion)) {
            return;
        }

        if ($pieza === GranMomentoDeFecha::CLAVE) {
            [$ocasion, $variante] = explode(':', $this->opcion, 2);
            $this->redirect(VistaPreviaFechas::getUrl(['ocasion' => $ocasion, 'variante' => $variante]));

            return;
        }

        ModoDesarrollo::elegir($pieza, $this->opcion);
        $this->recargar();
    }

    public function quitar(): void
    {
        if ($this->pieza !== null) {
            ModoDesarrollo::elegir($this->pieza, null);
        }

        $this->recargar();
    }

    /**
     * Plan 1.4r, D6: la estación de las cajas del inicio con un clic, para revisar las cuatro; nulo vuelve a la de la
     * fecha. Solo en la sesión, como toda pieza.
     */
    public function elegirEstacion(?string $estacion): void
    {
        if ($estacion !== null && ! app(RegistroPiezasDecorativas::class)->ofrece(EstacionDelInicio::CLAVE, $estacion)) {
            return;
        }

        ModoDesarrollo::elegir(EstacionDelInicio::CLAVE, $estacion);
        $this->recargar();
    }

    /** Observación 9.8: «Datos de demo» encendidos o apagados (cambia la base de la sesión; no borra nada). */
    public function alternarDemo(): void
    {
        DatosDemo::encendidos() ? DatosDemo::apagar() : DatosDemo::prender();
        $this->js('window.location.href = "/"');
    }

    /** Observación 9.8: crea (o rehace) la base limpia, con la misma configuración y lo real del OECE. */
    public function crearBaseLimpia(): void
    {
        app(BasesDeDatos::class)->crearLimpia();
        $this->recargar();
    }

    public function apagar(): void
    {
        ModoDesarrollo::apagar();
        $this->recargar();
    }

    public function render(): View
    {
        $piezas = app(RegistroPiezasDecorativas::class);

        return view('nucleo::filament.desarrollo.barra', [
            'simulada' => ModoDesarrollo::fechaSimulada(),
            'vistaPrevia' => VistaPreviaFechas::getUrl(),
            'piezas' => $piezas->todas(),
            'abierta' => $this->pieza === null ? null : $piezas->pieza($this->pieza),
            'elegida' => $this->pieza === null ? null : ModoDesarrollo::eleccion($this->pieza),
            'estaciones' => (new EstacionDelInicio)->opciones()['Estación'],
            'estacionElegida' => ModoDesarrollo::eleccion(EstacionDelInicio::CLAVE),
        ]);
    }

    /** Lo decorativo se arma al dibujar la página: se recarga para verlo con lo nuevo. */
    private function recargar(): void
    {
        $this->js('window.location.reload()');
    }

    /** El interruptor del menú de la foto (D1): solo con las tres llaves (D7). */
    public static function accionDelMenu(): Action
    {
        $activo = ModoDesarrollo::activo();

        return Action::make('modoDesarrollo')
            ->label('Modo desarrollo')
            ->icon($activo ? 'heroicon-o-check' : 'heroicon-o-stop')
            ->hidden(fn (): bool => ! ModoDesarrollo::disponible())
            ->action(function () use ($activo): void {
                $activo ? ModoDesarrollo::apagar() : ModoDesarrollo::prender();
            })
            ->after(fn (Component $livewire) => $livewire->js('window.location.reload()'));
    }
}
