<?php

declare(strict_types=1);

namespace App\Modules\Erp\Presupuestos\Filament\Resources\Presupuestos\Pages;

use App\Modules\Erp\Presupuestos\Filament\Resources\Presupuestos\PresupuestoResource;
use App\Modules\Erp\Presupuestos\Models\Presupuesto;
use App\Modules\Erp\Presupuestos\Services\PortadaPresupuestos;
use App\Modules\Nucleo\Filament\AccionFilament;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;

/**
 * La portada de Presupuestos (plan 2.10, D1 y D2), con el patrón de Cuentas: pestañas por estado con su cantidad, el
 * aviso de materiales sin producto con «Ver cuáles», la tabla y los últimos cambios. «Nuevo presupuesto» se abre en el
 * panel deslizante; «Insumos» abre el catálogo en el panel que se desliza encima de la lista, atenuada detrás, como en
 * Catálogos. Sin recargar; `?panel=insumos` y `?aviso=` abren directo.
 */
final class ListarPresupuestos extends ListRecords
{
    /** Los paneles que se abren encima de la lista. */
    public const array PANELES = ['insumos'];

    protected static string $resource = PresupuestoResource::class;

    protected static ?string $title = 'Presupuestos';

    #[Url]
    public ?string $panel = null;

    #[Url(as: 'aviso')]
    public ?string $aviso = null;

    /**
     * Calculados al abrir y al presionar «Ver cuáles».
     *
     * @var array<string, int>
     */
    #[Locked]
    public array $avisos = [];

    public function mount(): void
    {
        parent::mount();

        if (! in_array($this->panel, self::PANELES, true)) {
            $this->panel = null;
        }

        if (in_array($this->aviso, PortadaPresupuestos::AVISOS, true)) {
            $this->tableFilters['revisar']['value'] = $this->aviso;
        }

        $this->avisos = app(PortadaPresupuestos::class)->avisos();
    }

    /**
     * @return list<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            AccionFilament::desde('erp.presupuestos.crear-presupuesto', alTerminar: function (mixed $presupuesto): void {
                Notification::make()->success()->title('Presupuesto creado.')->send();

                if ($presupuesto instanceof Presupuesto) {
                    $this->redirect(VerPresupuesto::getUrl(['record' => $presupuesto->id]), navigate: true);
                }
            }, ocultos: fn (): array => ['oportunidad_id'], controles: ['moneda_id' => fn () => PresupuestoResource::selectorMoneda()])
                ->name('nuevo')->label('Nuevo presupuesto')->icon('heroicon-o-plus')->slideOver()
                ->modalDescription('Empieza en borrador. Con análisis de precios, el P.U. de cada partida sale de su APU.')
                ->fillForm(fn (): array => PresupuestoResource::valoresIniciales()),
            Action::make('insumos')->label('Insumos')->icon('heroicon-o-cube')->color('gray')
                ->action(fn () => $this->abrirPanel('insumos')),
        ];
    }

    /**
     * Todos, Borradores, Ofertados, Vigentes, Reemplazados y Anulados, con su cantidad.
     *
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        $portada = app(PortadaPresupuestos::class);
        $nombres = ['todos' => 'Todos', 'borradores' => 'Borradores', 'ofertados' => 'Ofertados', 'vigentes' => 'Vigentes', 'reemplazados' => 'Reemplazados', 'anulados' => 'Anulados'];
        $pestanas = [];

        foreach (PortadaPresupuestos::PESTANAS as $clave) {
            $pestanas[$clave] = Tab::make($nombres[$clave])
                ->badge($portada->enPestana(Presupuesto::query(), $clave)->count())
                ->modifyQueryUsing(fn (Builder $query): Builder => $portada->enPestana($query, $clave));
        }

        return $pestanas;
    }

    public function abrirPanel(string $panel): void
    {
        $this->panel = in_array($panel, self::PANELES, true) ? $panel : null;
    }

    public function cerrarPanel(): void
    {
        $this->panel = null;
    }

    /** «Ver cuáles» de un aviso: pone el filtro «Revisar» (o lo quita con null), sin recargar. */
    public function verAviso(?string $aviso): void
    {
        $this->tableFilters['revisar']['value'] = in_array($aviso, PortadaPresupuestos::AVISOS, true) ? $aviso : null;
        $this->resetPage();
        $this->avisos = app(PortadaPresupuestos::class)->avisos();
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Group::make([
                Group::make([
                    $this->getTabsContentComponent(),
                    View::make('presupuestos::portada.avisos')->viewData(fn (): array => ['avisos' => $this->avisos, 'filtro' => $this->tableFilters['revisar']['value'] ?? null]),
                    EmbeddedTable::make(),
                    View::make('nucleo::filament.parties.ultimos-cambios')->viewData(fn (): array => [
                        'cambios' => array_map(fn (array $c): array => [
                            ...$c,
                            'url' => $c['presupuesto_id'] === null ? null : VerPresupuesto::getUrl(['record' => $c['presupuesto_id']]),
                        ], app(PortadaPresupuestos::class)->ultimosCambios()),
                    ]),
                ])->extraAttributes(fn (): array => ['class' => $this->panel === null ? '' : 'tq-catalogos-detras']),
                View::make('presupuestos::portada.panel')->viewData(fn (): array => ['panel' => $this->panel]),
            ])->extraAttributes(['class' => 'tq-catalogos']),
        ]);
    }
}
