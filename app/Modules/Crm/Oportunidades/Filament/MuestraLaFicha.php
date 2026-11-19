<?php

declare(strict_types=1);

namespace App\Modules\Crm\Oportunidades\Filament;

use App\Modules\Crm\Oportunidades\OportunidadesServiceProvider;
use App\Modules\Crm\Oportunidades\Services\OportunidadesService;
use App\Modules\Nucleo\Models\User;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\View;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;

/**
 * La ficha de una oportunidad en un panel que se desliza desde la derecha, encima de la vista (plan 2.21c, D6): la abren
 * el tablero de gemas al tocar una tarjeta o una fila (`tq-tablero-abrir-ficha`) y, en el paso 8, la línea de tiempo.
 * Para usarla, la página pone `fichaDeOportunidad()` en su contenido y llama `abrirFicha(id)` (desde la vista:
 * `$wire.abrirFicha(id)`); `?ficha=<id>` la abre directo (un enlace guardado). Sus botones van por el mismo camino que el
 * arrastre (`MueveOportunidades`, con el controlador `oportunidades::ficha.movimiento` que trae la vista de la ficha).
 *
 * La versión se toma al abrir la ficha (`versionDeLaFicha`): los botones la llevan, nunca la de ese momento. Una anulada
 * no abre (las anuladas no están en ningún lugar, D11.2 de la 2.21): un aviso y nada más.
 */
trait MuestraLaFicha
{
    use MueveOportunidades;

    /** La oportunidad abierta en el panel; nula, cerrado. Cambia solo por `abrirFicha` y `cerrarFicha`. */
    #[Locked]
    #[Url(as: 'ficha')]
    public ?int $ficha = null;

    /** La versión leída al abrir la ficha: la que llevan sus botones (bloqueo optimista). */
    #[Locked]
    public ?int $versionDeLaFicha = null;

    public const string FICHA_NO_ESTA = 'Esa oportunidad ya no está: otra persona la anuló o no existe.';

    public function abrirFicha(int $oportunidadId): void
    {
        $usuario = Filament::auth()->user();
        abort_unless($usuario instanceof User && $usuario->can(OportunidadesServiceProvider::PERMISO_VER), 403);

        $version = app(OportunidadesService::class)->versionEnLaFaja($oportunidadId);

        if ($version === null) {
            Notification::make()->warning()->title(self::FICHA_NO_ESTA)->send();

            return;
        }

        $this->ficha = $oportunidadId;
        $this->versionDeLaFicha = $version;
    }

    /** Cierra el panel (la vista ya lo deslizó afuera) y devuelve el foco a la tarjeta de la que se abrió. */
    public function cerrarFicha(): void
    {
        $anterior = $this->ficha;
        $this->ficha = null;
        $this->versionDeLaFicha = null;

        if ($anterior !== null) {
            $this->js('document.querySelector('.json_encode('[data-tarjeta="'.$anterior.'"], [data-ficha-origen="'.$anterior.'"]').')?.focus()');
        }
    }

    /**
     * Tras mover una oportunidad (arrastre o botón): las cifras de arriba al día y, si su ficha está abierta, la versión
     * nueva para sus botones. Redibuja la vista: la gema llega tallada a su lugar nuevo.
     */
    public function despuesDeMover(int $oportunidadId): void
    {
        $this->actualizarCifras();

        if ($this->ficha === $oportunidadId) {
            $this->versionDeLaFicha = app(OportunidadesService::class)->versionEnLaFaja($oportunidadId);
            $this->ficha = $this->versionDeLaFicha === null ? null : $oportunidadId;
        }
    }

    /** Lo que se abre con `?ficha=<id>`: si ya no está, la vista abre sin panel. */
    protected function abrirFichaDeLaDireccion(): void
    {
        $version = $this->ficha === null ? null : app(OportunidadesService::class)->versionEnLaFaja($this->ficha);
        $this->ficha = $version === null ? null : $this->ficha;
        $this->versionDeLaFicha = $version;
    }

    /** El panel (vacío si no hay ficha abierta) y el controlador de los movimientos, para el contenido de la página. */
    protected function fichaDeOportunidad(): View
    {
        return View::make('oportunidades::ficha.panel')->viewData(fn (): array => [
            'ficha' => $this->datosDeLaFicha(),
            'version' => $this->versionDeLaFicha,
            'textos' => ['perdida' => MovimientoDeOportunidad::PERDIDA],
        ]);
    }

    /**
     * Los datos de la ficha abierta; nulos si no hay o si otra persona la anuló desde que se abrió.
     *
     * @return array<string, mixed>|null
     */
    private function datosDeLaFicha(): ?array
    {
        if ($this->ficha === null) {
            return null;
        }

        try {
            return app(FichaDeOportunidad::class)->datos($this->ficha);
        } catch (ModelNotFoundException) {
            return null;
        }
    }
}
