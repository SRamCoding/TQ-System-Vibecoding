<?php

declare(strict_types=1);

namespace App\Modules\Crm\Oportunidades\Filament\Resources\Oportunidades\Pages;

use App\Modules\Crm\Oportunidades\Filament\MuestraLasVistas;
use App\Modules\Crm\Oportunidades\Filament\PanelesOportunidad;
use App\Modules\Crm\Oportunidades\Filament\Resources\Oportunidades\OportunidadResource;
use App\Modules\Crm\Oportunidades\Filament\SelectoresOportunidad;
use App\Modules\Crm\Oportunidades\Filament\VistasDeOportunidades;
use App\Modules\Crm\Oportunidades\Models\Oportunidad;
use App\Modules\Crm\Oportunidades\Services\OportunidadesService;
use App\Modules\Crm\Oportunidades\Services\PortadaOportunidades;
use App\Modules\Crm\Oportunidades\Services\ResumenOportunidad;
use App\Modules\Nucleo\Excepciones\ErrorDeNegocio;
use App\Modules\Nucleo\RegistroAcciones\EjecutorAcciones;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;

/**
 * La vista «Lista» de Oportunidades (plan 2.21, D11.1; desde la 2.21c, una de las cuatro vistas y ya no la de
 * entrada): con el patrón de Cuentas, pestañas de texto con su cantidad, avisos con «Ver cuáles» y la tabla. Todo
 * filtra en el sitio, sin recargar. Arriba, las pestañas de vista y la fila de cifras (`MuestraLasVistas`); las anuladas
 * no cuentan en las cifras (D11.2): solo salen en su pestaña y en «Todas». `?aviso=` abre la tabla ya filtrada.
 */
final class ListarOportunidades extends ListRecords
{
    use MuestraLasVistas;

    protected static string $resource = OportunidadResource::class;

    protected static ?string $title = 'Oportunidades';

    protected static ?string $breadcrumb = 'Lista';

    #[Url(as: 'aviso')]
    public ?string $aviso = null;

    /**
     * Calculados al abrir, al presionar «Ver cuáles» y tras un cambio (no en cada tecla del buscador).
     *
     * @var array<string, int>
     */
    #[Locked]
    public array $avisos = [];

    public static function vista(): string
    {
        return VistasDeOportunidades::LISTA;
    }

    public function mount(): void
    {
        parent::mount();

        if (in_array($this->aviso, PortadaOportunidades::AVISOS, true)) {
            $this->tableFilters['revisar']['value'] = $this->aviso;
        }

        $this->calcularPortada();
    }

    /**
     * @return list<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            PanelesOportunidad::nueva(null, function (mixed $resultado): void {
                Notification::make()->success()->title('Oportunidad creada.')->send();

                if ($resultado instanceof ResumenOportunidad) {
                    $this->redirect(VerOportunidad::getUrl(['record' => $resultado->id]), navigate: true);
                }
            }),
        ];
    }

    /**
     * D11.1: Abiertas, Mías, Estancadas, Ganadas, Perdidas, Anuladas y Todas, con su cantidad.
     *
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        $portada = app(PortadaOportunidades::class);
        $usuario = OportunidadResource::usuario();
        $nombres = ['abiertas' => 'Abiertas', 'mias' => 'Mías', 'estancadas' => 'Estancadas', 'ganadas' => 'Ganadas', 'perdidas' => 'Perdidas', 'anuladas' => 'Anuladas', 'todas' => 'Todas'];
        $pestanas = [];

        foreach (PortadaOportunidades::PESTANAS as $clave) {
            $pestanas[$clave] = Tab::make($nombres[$clave])
                ->badge($portada->enPestana(Oportunidad::query(), $clave, $usuario)->count())
                ->modifyQueryUsing(fn (Builder $query): Builder => $portada->enPestana($query, $clave, $usuario));
        }

        return $pestanas;
    }

    public function table(Table $table): Table
    {
        return parent::table($table)
            ->toolbarActions([
                // D5: todo o nada; solo abiertas (el servicio lo revisa).
                BulkAction::make('reasignar')->label('Reasignar')->icon('heroicon-o-arrows-right-left')
                    ->visible(fn (): bool => OportunidadResource::usuarioPuede(OportunidadesService::PERMISO_REASIGNAR))
                    ->schema([SelectoresOportunidad::ejecutivo()->label('Nuevo ejecutivo')->required()])
                    ->action(function (Collection $records, array $data): void {
                        $this->reasignar($records->modelKeys(), (int) $data['ejecutivo_user_id']);
                    })
                    ->deselectRecordsAfterCompletion(),
            ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            // MV-023 y MV-024: Por calificar (leads) · Oportunidades · Licitaciones.
            View::make('oportunidades::portada.embudo-pestanas')->viewData(['actual' => 'oportunidades']),
            $this->cabeceraDeVistas(),
            $this->getTabsContentComponent(),
            View::make('oportunidades::portada.avisos')->viewData(fn (): array => ['avisos' => $this->avisos, 'filtro' => $this->tableFilters['revisar']['value'] ?? null]),
            EmbeddedTable::make(),
        ]);
    }

    /** «Ver cuáles» de un aviso: pone el filtro «Revisar» (o lo quita con null), sin recargar. */
    public function verAviso(?string $aviso): void
    {
        $this->tableFilters['revisar']['value'] = in_array($aviso, PortadaOportunidades::AVISOS, true) ? $aviso : null;
        $this->resetPage();
        $this->calcularPortada();
    }

    /**
     * @param  list<int>  $oportunidadIds
     */
    private function reasignar(array $oportunidadIds, int $ejecutivoId): void
    {
        try {
            $cambiadas = app(EjecutorAcciones::class)->ejecutar('crm.oportunidades.reasignar', [
                'oportunidad_ids' => $oportunidadIds,
                'ejecutivo_user_id' => $ejecutivoId,
            ], OportunidadResource::usuario() ?? throw new AuthorizationException);
        } catch (ErrorDeNegocio $e) {
            Notification::make()->danger()->title($e->getMessage())->send();

            return;
        }

        Notification::make()->success()->title($cambiadas === 1 ? 'Se reasignó 1 oportunidad.' : "Se reasignaron {$cambiadas} oportunidades.")->send();
        $this->calcularPortada();
    }

    private function calcularPortada(): void
    {
        $this->avisos = app(PortadaOportunidades::class)->avisos();
        $this->calcularCifras();
    }
}
