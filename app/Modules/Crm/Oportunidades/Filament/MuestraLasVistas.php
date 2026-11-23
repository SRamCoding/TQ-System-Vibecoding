<?php

declare(strict_types=1);

namespace App\Modules\Crm\Oportunidades\Filament;

use Filament\Schemas\Components\View;
use Livewire\Attributes\Locked;

/**
 * Para las cuatro páginas de vista de Oportunidades (plan 2.21c, D1): arriba de su contenido, las pestañas y la fila de
 * cifras (`oportunidades::vistas.cabecera`). Las cifras se calculan al abrir y tras un cambio (`calcularCifras`), no en
 * cada tecla de un buscador.
 */
trait MuestraLasVistas
{
    /**
     * @var array<string, array{titulo: string, valor: string, valorCompleto: ?string, detalle: string, detalleCompleto?: string, notas: list<string>, alerta: bool}>
     */
    #[Locked]
    public array $cifras = [];

    /** La clave de esta vista en `VistasDeOportunidades` (la pestaña activa). */
    abstract public static function vista(): string;

    protected function calcularCifras(): void
    {
        $this->cifras = VistasDeOportunidades::cifras();
    }

    /**
     * Las cifras al día y la vista redibujada, tras mover una oportunidad: la faja lo llama al recargar (revisión de
     * arquitectura de la 2.21c; antes la fila quedaba vieja) y `MuestraLaFicha::despuesDeMover` lo usa en el tablero y
     * la línea de tiempo.
     */
    public function actualizarCifras(): void
    {
        $this->calcularCifras();
    }

    /** Observación 8.4: sin título de página; el proceso y la pestaña elegida ya dicen dónde se está. */
    public function getHeading(): string
    {
        return '';
    }

    protected function cabeceraDeVistas(): View
    {
        return View::make('oportunidades::vistas.cabecera')->viewData(fn (): array => [
            'pestanas' => VistasDeOportunidades::pestanas(static::vista()),
            'cifras' => $this->cifras,
        ]);
    }
}
