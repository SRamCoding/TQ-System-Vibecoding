<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Filament;

use App\Modules\Nucleo\ConfiguracionInicial\DefinicionPaso;
use App\Modules\Nucleo\ConfiguracionInicial\RegistroPasosConfiguracion;
use App\Modules\Nucleo\Filament\ConfiguracionInicial\MuestraPasoAbierto;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Services\ConfiguracionInicialService;
use App\Modules\Nucleo\Services\NavegacionService;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * La bienvenida de la configuración inicial (plan 1.13b): una ventana al centro, encima de la primera pantalla tras
 * iniciar sesión, con los pasos de `RegistroPasosConfiguracion` uno a la vez (D3). Cada paso muestra su pantalla de
 * siempre, igual que el panel de Configuración inicial (`MuestraPasoAbierto`, D6). Cuándo sale lo decide
 * `ConfiguracionInicialService::muestraBienvenida()` y el panel la monta (`AppPanelProvider`); la ✕ y Esc solo la
 * cierran, y como sale solo al entrar, vuelve en el próximo ingreso (D1).
 */
final class BienvenidaConfiguracion extends Component implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use MuestraPasoAbierto;

    /** El paso a la vista, o null: la pantalla de cierre (D5). */
    public ?string $paso = null;

    /** La ruta de otra pantalla abierta dentro del paso (la ficha de una fila, por ejemplo), o null: la del paso. */
    public ?string $ficha = null;

    /** Cerrada con la ✕ o Esc: ya no dibuja nada (la pantalla del paso deja de estar montada). */
    public bool $cerrada = false;

    /**
     * Empieza por el primer obligatorio que falta (D3). Si a la persona no le toca (revisión de arquitectura: por si
     * alguien la monta en otro lado), nace cerrada y no muestra los pasos.
     */
    public function mount(): void
    {
        $usuario = Filament::auth()->user();

        if (! $usuario instanceof User || ! app(ConfiguracionInicialService::class)->muestraBienvenida($usuario)) {
            $this->cerrada = true;

            return;
        }

        $this->paso = (app(ConfiguracionInicialService::class)->faltan()[0] ?? null)?->clave;
    }

    public function ir(string $paso): void
    {
        $this->paso = app(RegistroPasosConfiguracion::class)->paso($paso) !== null ? $paso : $this->paso;
        $this->ficha = null;
    }

    /** «Saltar» y «Siguiente» (D4): después del último, el cierre. */
    public function siguiente(): void
    {
        $claves = $this->claves();
        $posicion = $this->paso === null ? null : array_search($this->paso, $claves, true);

        if ($posicion !== null && $posicion !== false) {
            $this->paso = $claves[$posicion + 1] ?? null;
            $this->ficha = null;
        }
    }

    /** «Anterior» (D4): desde el cierre, el último paso; desde el primero, nada. */
    public function anterior(): void
    {
        $claves = $this->claves();
        $posicion = $this->paso === null ? count($claves) : array_search($this->paso, $claves, true);

        if ($posicion !== false && $posicion > 0) {
            $this->paso = $claves[$posicion - 1];
            $this->ficha = null;
        }
    }

    public function cerrar(): void
    {
        $this->cerrada = true;
    }

    /** «Ir al inicio» del cierre (D5, precisado el 2026-11-03): no apaga nada; si faltan pasos, vuelve en el próximo ingreso. */
    public function irAlInicio(): void
    {
        $this->redirect($this->inicio());
    }

    /** «Listo, siguiente» (D4): marca el paso con su acción y avanza. Si ya está listo, el botón es «Siguiente». */
    public function listoSiguienteAction(): Action
    {
        return AccionFilament::desde('nucleo.configuracion-inicial.marcar-paso-listo', fn (): array => ['paso' => (string) $this->paso])
            ->name('listoSiguiente')
            ->label('Listo, siguiente')
            ->icon('heroicon-m-check')
            ->color('primary')
            ->after(function (): void {
                Notification::make()->success()->title('Paso listo.')->send();
                $this->siguiente();
            });
    }

    /** «Seguir después desde Configuración» (D1): la apaga para quien la presiona, con su acción, y lleva al inicio. */
    public function seguirDespuesAction(): Action
    {
        return AccionFilament::desde('nucleo.configuracion-inicial.seguir-despues')
            ->name('seguirDespues')
            ->label('Seguir después desde Configuración')
            ->link()
            ->color('gray')
            ->after(function (): void {
                Notification::make()->success()->title('La configuración inicial sigue en Configuración → Configuración inicial.')->send();
                $this->redirect($this->inicio());
            });
    }

    public function render(): View
    {
        if ($this->cerrada) {
            return view('nucleo::filament.configuracion-inicial.bienvenida', ['cerrada' => true]);
        }

        $pasos = array_values(app(RegistroPasosConfiguracion::class)->pasos());
        $listos = app(ConfiguracionInicialService::class)->listos();

        return view('nucleo::filament.configuracion-inicial.bienvenida', [
            'cerrada' => false,
            'pasos' => array_map(fn (DefinicionPaso $paso): array => ['definicion' => $paso, 'listo' => in_array($paso->clave, $listos, true)], $pasos),
            'numero' => $this->paso === null || ($posicion = array_search($this->paso, $this->claves(), true)) === false ? null : $posicion + 1,
            'faltan' => app(ConfiguracionInicialService::class)->faltan($listos),
            'abiertoListo' => $this->paso !== null && in_array($this->paso, $listos, true),
            ...$this->datosDelPasoAbierto(),
        ]);
    }

    /** @return list<string> */
    private function claves(): array
    {
        return array_keys(app(RegistroPasosConfiguracion::class)->pasos());
    }

    private function inicio(): string
    {
        $usuario = Filament::auth()->user();

        return ($usuario instanceof User ? app(NavegacionService::class)->pantallaDeInicio($usuario) : null) ?? Filament::getUrl();
    }
}
