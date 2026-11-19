<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Filament\Resources\Parties\Pages;

use App\Modules\Nucleo\Filament\Resources\Parties\PartyResource;
use App\Modules\Nucleo\Models\Party;
use App\Modules\Nucleo\Models\RolParty;
use App\Modules\Nucleo\Seguridad\GrupoSensible;
use App\Modules\Nucleo\Services\CalidadPartiesService;
use App\Modules\Nucleo\Tiempo\Calendario;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Locked;

/**
 * La portada de Personas y empresas (plan 1.4m): pestañas por tipo con su cantidad (D1, sin cajas), los avisos de
 * fichas incompletas y de posibles duplicados con «Ver cuáles» (D2 y D3), la tabla y los últimos cambios (D4). Todo
 * filtra en el sitio, sin recargar. Solo lee: lo que se cambia sigue pasando por la ficha y sus acciones registradas.
 */
final class ListarParties extends ListRecords
{
    protected static string $resource = PartyResource::class;

    protected static ?string $title = 'Personas y empresas';

    /**
     * Los avisos, calculados al abrir y al presionar «Ver cuáles» (no en cada tecla del buscador ni cada página: son
     * varias consultas).
     *
     * @var array{incompletas: array<string, array{texto: string, cantidad: int}>, totalIncompletas: int, duplicados: int}
     */
    #[Locked]
    public array $avisos = ['incompletas' => [], 'totalIncompletas' => 0, 'duplicados' => 0];

    public function mount(): void
    {
        parent::mount();
        $this->calcularAvisos();
    }

    /**
     * @return list<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('registrar')
                ->label('Registrar')
                ->icon('heroicon-o-plus')
                ->url(NuevaParty::getUrl())
                ->visible(fn (): bool => PartyResource::usuarioPuede('parties.crear')),
        ];
    }

    /**
     * «Todos» y una pestaña por cada tipo activo del catálogo, con cuántas fichas activas lo tienen hoy. Un tipo nuevo
     * del catálogo suma su pestaña solo (D1).
     *
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        $hoy = Calendario::hoy();
        $pestanas = ['todos' => Tab::make('Todos')->badge(Party::query()->where('es_activo', true)->count())];

        foreach (RolParty::query()->where('es_activo', true)->orderBy('nombre')->get(['codigo', 'nombre']) as $tipo) {
            $codigo = (string) $tipo->codigo;
            $pestanas[$codigo] = Tab::make($tipo->nombre)
                ->badge(Party::query()->where('es_activo', true)->scopes(['conRolVigente' => [$codigo, $hoy]])->count())
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->scopes(['conRolVigente' => [$codigo, $hoy]]));
        }

        return $pestanas;
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            $this->getTabsContentComponent(),
            View::make('nucleo::filament.parties.avisos')->viewData(fn (): array => [...$this->avisos, 'filtroCalidad' => $this->filtroCalidad()]),
            EmbeddedTable::make(),
            View::make('nucleo::filament.parties.ultimos-cambios')->viewData(fn (): array => [
                'cambios' => array_map(fn (array $c): array => [
                    ...$c,
                    'url' => $c['party_id'] === null ? null : VerParty::getUrl(['record' => $c['party_id']]),
                ], app(CalidadPartiesService::class)->ultimosCambios()),
            ]),
        ]);
    }

    /** «Ver cuáles» de un aviso: pone el filtro «Revisar» de la tabla (o lo quita con null), sin recargar. */
    public function verCalidad(?string $calidad): void
    {
        $this->tableFilters['calidad']['value'] = in_array($calidad, ['incompletas', 'duplicados'], true) ? $calidad : null;
        $this->resetPage();
        $this->calcularAvisos();
    }

    private function filtroCalidad(): ?string
    {
        $filtro = $this->tableFilters['calidad']['value'] ?? null;

        return is_string($filtro) ? $filtro : null;
    }

    /** La regla de la cuenta bancaria solo cuenta para quien puede ver datos bancarios (como la pestaña de cuentas). */
    private function calcularAvisos(): void
    {
        $calidad = app(CalidadPartiesService::class);
        $conDatosBancarios = PartyResource::usuarioPuede(GrupoSensible::DatosBancarios->permiso());

        $this->avisos = [
            'incompletas' => $calidad->resumenIncompletas($conDatosBancarios),
            'totalIncompletas' => $calidad->cuantasIncompletas($conDatosBancarios),
            'duplicados' => count($calidad->idsPosiblesDuplicados()),
        ];
    }
}
