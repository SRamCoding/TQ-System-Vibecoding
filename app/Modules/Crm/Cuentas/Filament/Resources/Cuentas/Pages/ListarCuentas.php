<?php

declare(strict_types=1);

namespace App\Modules\Crm\Cuentas\Filament\Resources\Cuentas\Pages;

use App\Modules\Crm\Cuentas\Filament\Resources\Cuentas\CuentaResource;
use App\Modules\Crm\Cuentas\Filament\SelectoresCuenta;
use App\Modules\Crm\Cuentas\Models\Cuenta;
use App\Modules\Crm\Cuentas\Services\PortadaCuentas;
use App\Modules\Nucleo\Excepciones\ErrorDeNegocio;
use App\Modules\Nucleo\Filament\AccionFilament;
use App\Modules\Nucleo\RegistroAcciones\EjecutorAcciones;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\Toggle;
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
 * La portada de Cuentas (plan 2.20, D11), con el patrón de Personas y empresas: pestañas de texto con su cantidad,
 * avisos con «Ver cuáles», cartera por ejecutivo, la tabla y los últimos cambios. Todo filtra en el sitio, sin recargar.
 * `?ejecutivo=` y `?aviso=` abren la tabla ya filtrada (el aviso de cuentas asignadas y la alerta, D7).
 */
final class ListarCuentas extends ListRecords
{
    protected static string $resource = CuentaResource::class;

    protected static ?string $title = 'Cuentas';

    #[Url(as: 'ejecutivo')]
    public ?string $ejecutivo = null;

    #[Url(as: 'aviso')]
    public ?string $aviso = null;

    /**
     * Calculados al abrir y al presionar «Ver cuáles» (no en cada tecla del buscador).
     *
     * @var array{avisos: array<string, int>, cartera: list<array{id: int, nombre: string, activo: bool, cuentas: int}>}
     */
    #[Locked]
    public array $portada = ['avisos' => [], 'cartera' => []];

    public function mount(): void
    {
        parent::mount();

        if (is_numeric($this->ejecutivo)) {
            $this->tableFilters['ejecutivo_user_id']['value'] = (int) $this->ejecutivo;
        }

        if (in_array($this->aviso, PortadaCuentas::AVISOS, true)) {
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
            Action::make('nueva')->label('Nueva cuenta')->icon('heroicon-o-plus')
                ->url(NuevaCuenta::getUrl())
                ->visible(fn (): bool => CuentaResource::usuarioPuede('crm.cuentas.crear')),
        ];
    }

    /**
     * D11.1: Todas (de verdad todas), Mías, Clientes activos, Prospectos, Nuevas este mes, Sin actividad (solo si alguien
     * aporta el último contacto) e Inactivas, con su cantidad.
     *
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        $portada = app(PortadaCuentas::class);
        $usuario = CuentaResource::usuario();
        $nombres = ['todas' => 'Todas', 'mias' => 'Mías', 'clientes' => 'Clientes activos', 'prospectos' => 'Prospectos', 'nuevas' => 'Nuevas este mes', 'sin-actividad' => 'Sin actividad', 'inactivas' => 'Inactivas'];
        $pestanas = [];

        foreach ($portada->pestanas() as $clave) {
            $pestanas[$clave] = Tab::make($nombres[$clave])
                ->badge($portada->enPestana(Cuenta::query(), $clave, $usuario)->count())
                ->modifyQueryUsing(fn (Builder $query): Builder => $portada->enPestana($query, $clave, $usuario));
        }

        return $pestanas;
    }

    public function table(Table $table): Table
    {
        // D4: el nivel solo se pide en una pública.
        $sinNivelSiPrivada = fn (Cuenta $record): array => str_starts_with(CuentaResource::tipo($record), 'Pública') ? [] : ['nivel_gobierno_id'];

        return parent::table($table)
            ->recordActions([
                AccionFilament::desde('crm.cuentas.editar', fn (Cuenta $record): array => ['cuenta_id' => $record->id], $this->avisarGuardado(...), ocultos: $sinNivelSiPrivada)
                    ->label('Editar')->icon('heroicon-o-pencil-square')
                    ->fillForm(fn (Cuenta $record): array => [...$record->only(['origen_comercial_id', 'nivel_gobierno_id', 'notas']), 'version' => $record->version])
                    ->hidden(fn (Cuenta $record): bool => ! $record->es_activa),
                AccionFilament::desde('crm.cuentas.editar-propias', fn (Cuenta $record): array => ['cuenta_id' => $record->id], $this->avisarGuardado(...), ocultos: $sinNivelSiPrivada)
                    ->label('Editar')->icon('heroicon-o-pencil-square')
                    ->fillForm(fn (Cuenta $record): array => [...$record->only(['origen_comercial_id', 'nivel_gobierno_id', 'notas']), 'version' => $record->version])
                    // Solo si no tiene el de todas y es su ejecutivo (D7.6).
                    ->hidden(fn (Cuenta $record): bool => ! $record->es_activa || CuentaResource::usuarioPuede('crm.cuentas.editar') || ! CuentaResource::puedeEditar($record)),
            ])
            ->toolbarActions([
                BulkAction::make('reasignar')->label('Reasignar')->icon('heroicon-o-arrows-right-left')
                    ->visible(fn (): bool => CuentaResource::usuarioPuede('crm.cuentas.reasignar'))
                    ->schema([
                        SelectoresCuenta::ejecutivo()->label('Nuevo ejecutivo')->required(),
                        Toggle::make('incluir_oportunidades_abiertas')->label('Pasar también sus oportunidades abiertas')->default(true),
                    ])
                    ->action(function (Collection $records, array $data): void {
                        $this->reasignar($records->modelKeys(), (int) $data['ejecutivo_user_id'], (bool) $data['incluir_oportunidades_abiertas']);
                    })
                    ->deselectRecordsAfterCompletion(),
            ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            $this->getTabsContentComponent(),
            View::make('cuentas::portada.avisos')->viewData(fn (): array => ['avisos' => $this->portada['avisos'], 'filtro' => $this->filtro('revisar')]),
            View::make('cuentas::portada.cartera')->viewData(fn (): array => ['cartera' => $this->portada['cartera'], 'filtro' => $this->filtro('ejecutivo_user_id')]),
            EmbeddedTable::make(),
            View::make('nucleo::filament.parties.ultimos-cambios')->viewData(fn (): array => [
                'cambios' => array_map(fn (array $c): array => [
                    ...$c,
                    'url' => $c['cuenta_id'] === null ? null : VerCuenta::getUrl(['record' => $c['cuenta_id']]),
                ], app(PortadaCuentas::class)->ultimosCambios()),
            ]),
        ]);
    }

    /** «Ver cuáles» de un aviso: pone el filtro «Revisar» (o lo quita con null), sin recargar. */
    public function verAviso(?string $aviso): void
    {
        $this->tableFilters['revisar']['value'] = in_array($aviso, PortadaCuentas::AVISOS, true) ? $aviso : null;
        $this->resetPage();
        $this->calcularPortada();
    }

    /** Un clic en la cartera filtra por ese ejecutivo (o lo quita con null). */
    public function verEjecutivo(?int $ejecutivoId): void
    {
        $this->tableFilters['ejecutivo_user_id']['value'] = $ejecutivoId;
        $this->resetPage();
    }

    /**
     * @param  list<int>  $cuentaIds
     */
    private function reasignar(array $cuentaIds, int $ejecutivoId, bool $incluirAbiertas): void
    {
        try {
            $cambiadas = app(EjecutorAcciones::class)->ejecutar('crm.cuentas.reasignar', [
                'cuenta_ids' => $cuentaIds,
                'ejecutivo_user_id' => $ejecutivoId,
                'incluir_oportunidades_abiertas' => $incluirAbiertas,
            ], CuentaResource::usuario() ?? throw new AuthorizationException);
        } catch (ErrorDeNegocio $e) {
            Notification::make()->danger()->title($e->getMessage())->send();

            return;
        }

        Notification::make()->success()->title($cambiadas === 1 ? 'Se reasignó 1 cuenta.' : "Se reasignaron {$cambiadas} cuentas.")->send();
        $this->calcularPortada();
    }

    private function avisarGuardado(): void
    {
        Notification::make()->success()->title('Guardado.')->send();
        $this->calcularPortada();
    }

    private function filtro(string $nombre): mixed
    {
        return $this->tableFilters[$nombre]['value'] ?? null;
    }

    private function calcularPortada(): void
    {
        $portada = app(PortadaCuentas::class);
        $this->portada = ['avisos' => $portada->avisos(), 'cartera' => $portada->cartera()];
    }
}
