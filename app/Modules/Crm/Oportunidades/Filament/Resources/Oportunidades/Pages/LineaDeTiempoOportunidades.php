<?php

declare(strict_types=1);

namespace App\Modules\Crm\Oportunidades\Filament\Resources\Oportunidades\Pages;

use App\Modules\Crm\Oportunidades\Filament\DibujoDelCronograma;
use App\Modules\Crm\Oportunidades\Filament\MuestraLaFicha;
use App\Modules\Crm\Oportunidades\Filament\MuestraLasVistas;
use App\Modules\Crm\Oportunidades\Filament\Resources\Oportunidades\OportunidadResource;
use App\Modules\Crm\Oportunidades\Filament\VistasDeOportunidades;
use App\Modules\Crm\Oportunidades\OportunidadesServiceProvider;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Url;
use Livewire\WithPagination;

/**
 * La vista «Línea de tiempo» de Oportunidades como la propone el prototipo aprobado (plan 2.21d, D11; reemplaza la de
 * gemas por carril de la 2.21c): una fila por oportunidad con una barra por cada etapa que pasó, lo que falta hasta el
 * cierre esperado y el hito del cierre (`DibujoDelCronograma`, vista `oportunidades::linea.cronograma`). Arriba, las
 * pestañas de vista y la fila de cifras (`MuestraLasVistas`). Tocar una fila abre su ficha en el panel deslizante
 * (`MuestraLaFicha`); `?ficha=<id>` la abre directo.
 */
final class LineaDeTiempoOportunidades extends Page
{
    use MuestraLaFicha;
    use MuestraLasVistas;
    use WithPagination;

    /** El buscador (pedido del desarrollador, 2026-11-16): lo que coincide sube arriba mientras se escribe. */
    #[Url(as: 'q', except: '')]
    public string $buscar = '';

    /** Al escribir, se vuelve a la página 1: ahí está la que mejor coincide. */
    public function updatedBuscar(): void
    {
        $this->resetPage();
    }

    protected static string $resource = OportunidadResource::class;

    protected static ?string $title = 'Línea de tiempo de oportunidades';

    protected static ?string $breadcrumb = 'Línea de tiempo';

    public static function canAccess(array $parameters = []): bool
    {
        return OportunidadResource::usuarioPuede(OportunidadesServiceProvider::PERMISO_VER);
    }

    public static function vista(): string
    {
        return VistasDeOportunidades::LINEA_DE_TIEMPO;
    }

    public function mount(): void
    {
        $this->calcularCifras();
        $this->abrirFichaDeLaDireccion();
    }

    public function getMaxContentWidth(): Width
    {
        return Width::Full;
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            $this->cabeceraDeVistas(),
            // Plan 2.21d (D11, arreglo): una fila por oportunidad con sus etapas en el tiempo, como el prototipo.
            // Plan 2.21d (D11, arreglo): una fila por oportunidad con sus etapas en el tiempo, como el prototipo; de
            // 10 en 10 y con el buscador que sube arriba lo que coincide mientras se escribe.
            View::make('oportunidades::linea.cronograma')->viewData(function (): array {
                $datos = app(DibujoDelCronograma::class)->datos($this->buscar);
                $pagina = new LengthAwarePaginator(
                    array_slice($datos['filas'], ($this->getPage() - 1) * DibujoDelCronograma::POR_PAGINA, DibujoDelCronograma::POR_PAGINA),
                    count($datos['filas']), DibujoDelCronograma::POR_PAGINA, $this->getPage(),
                );

                return [...$datos, 'filas' => $pagina->items(), 'pagina' => $pagina, 'buscar' => $this->buscar, 'fichaAbierta' => $this->ficha];
            }),
            $this->fichaDeOportunidad(),
        ]);
    }
}
