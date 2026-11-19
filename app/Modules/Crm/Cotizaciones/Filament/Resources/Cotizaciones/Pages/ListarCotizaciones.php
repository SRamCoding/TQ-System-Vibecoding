<?php

declare(strict_types=1);

namespace App\Modules\Crm\Cotizaciones\Filament\Resources\Cotizaciones\Pages;

use App\Modules\Crm\Cotizaciones\Filament\Resources\Cotizaciones\CotizacionResource;
use App\Modules\Crm\Cotizaciones\Filament\SelectoresCotizacion;
use App\Modules\Crm\Cotizaciones\Models\Cotizacion;
use App\Modules\Crm\Cotizaciones\Services\PortadaCotizaciones;
use App\Modules\Crm\Oportunidades\Services\OportunidadesService;
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
 * La portada de Cotizaciones (D19, con la convención de `interfaz.md`; desarrollador, 2026-11-12): pestañas por estado
 * con su cantidad, el aviso «Por vencer» con «Ver cuáles», la barra de carpetas Cuenta › Oportunidad › Cotización ›
 * Versión (la versión más reciente primero; en la oportunidad, también sus presupuestos), «Solo mías», la tabla y los
 * últimos cambios. Elegir una carpeta recorta la tabla sin recargar y la URL lo guarda (`?carpeta=`). «Nueva
 * cotización» se abre en el panel deslizante y elige una oportunidad abierta que ya existe (D4).
 */
final class ListarCotizaciones extends ListRecords
{
    protected static string $resource = CotizacionResource::class;

    protected static ?string $title = 'Cotizaciones';

    #[Url(as: 'aviso')]
    public ?string $aviso = null;

    /** «cuenta:12», «oportunidad:34» o «numero:COT-2026-0001»; nulo, todas. */
    #[Url]
    public ?string $carpeta = null;

    #[Url(as: 'mias')]
    public bool $soloMias = false;

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

        if (in_array($this->aviso, PortadaCotizaciones::AVISOS, true)) {
            $this->tableFilters['revisar']['value'] = $this->aviso;
        }

        $this->carpeta = self::carpetaValida($this->carpeta);
        $this->avisos = app(PortadaCotizaciones::class)->avisos();
    }

    /** @return list<Action> */
    protected function getHeaderActions(): array
    {
        return [
            AccionFilament::desde('crm.cotizaciones.crear', alTerminar: function (mixed $cotizacion): void {
                Notification::make()->success()->title('Cotización creada, en borrador.')->send();

                if ($cotizacion instanceof Cotizacion) {
                    $this->redirect(VerCotizacion::getUrl(['record' => $cotizacion->id]), navigate: true);
                }
            }, controles: [
                'oportunidad_id' => fn () => SelectoresCotizacion::oportunidad(),
                'contacto_id' => fn () => SelectoresCotizacion::contacto(),
            ])
                ->name('nueva')->label('Nueva cotización')->icon('heroicon-o-plus')->slideOver()
                ->modalDescription('Nace en borrador, de una oportunidad abierta. Las condiciones vacías toman los valores por defecto.')
                ->fillForm(fn (): array => ['oportunidad_id' => $this->oportunidadDeLaCarpeta()]),
        ];
    }

    /** @return array<string, Tab> */
    public function getTabs(): array
    {
        $portada = app(PortadaCotizaciones::class);
        $pestanas = [];

        foreach (PortadaCotizaciones::PESTANAS as $clave) {
            $pestanas[$clave] = Tab::make(PortadaCotizaciones::NOMBRES[$clave])
                ->badge(fn (): int => $portada->enPestana($this->recortar(Cotizacion::query()), $clave)->count())
                ->modifyQueryUsing(fn (Builder $query): Builder => $portada->enPestana($query, $clave));
        }

        return $pestanas;
    }

    /**
     * Lo que la carpeta y «Solo mías» dejan ver: lo usan la tabla, las cantidades de las pestañas y las carpetas.
     *
     * @param  Builder<Cotizacion>  $query
     * @return Builder<Cotizacion>
     */
    public function recortar(Builder $query): Builder
    {
        if ($this->soloMias) {
            $usuario = CotizacionResource::usuario();
            $query->whereIn('cotizaciones.oportunidad_id', $usuario === null ? [] : app(OportunidadesService::class)->idsDelEjecutivo($usuario->id));
        }

        [$tipo, $valor] = array_pad(explode(':', (string) $this->carpeta, 2), 2, '');

        return match ($tipo) {
            'cuenta' => $query->where('cotizaciones.cuenta_id', (int) $valor),
            'oportunidad' => $query->where('cotizaciones.oportunidad_id', (int) $valor),
            'numero' => $query->where('cotizaciones.numero', $valor),
            default => $query,
        };
    }

    /** Elige una carpeta (o la quita con null), sin recargar. */
    public function verCarpeta(?string $carpeta): void
    {
        $this->carpeta = self::carpetaValida($carpeta);
        $this->resetPage();
    }

    public function cambiarSoloMias(): void
    {
        $this->soloMias = ! $this->soloMias;
        $this->resetPage();
    }

    /** «Ver cuáles» de un aviso: pone el filtro «Revisar» (o lo quita con null), sin recargar. */
    public function verAviso(?string $aviso): void
    {
        $this->tableFilters['revisar']['value'] = in_array($aviso, PortadaCotizaciones::AVISOS, true) ? $aviso : null;
        $this->resetPage();
        $this->avisos = app(PortadaCotizaciones::class)->avisos();
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Group::make([
                View::make('cotizaciones::portada.carpetas')->viewData(fn (): array => [
                    // Las carpetas muestran lo que dejan la pestaña, el buscador, los filtros y «Solo mías» (interfaz.md),
                    // pero no la carpeta elegida: así se puede cambiar de carpeta sin volver atrás.
                    'arbol' => app(PortadaCotizaciones::class)->carpetas($this->consultaSinCarpeta()),
                    'carpeta' => $this->carpeta,
                    'soloMias' => $this->soloMias,
                ]),
                Group::make([
                    $this->getTabsContentComponent(),
                    View::make('cotizaciones::portada.avisos')->viewData(fn (): array => ['avisos' => $this->avisos, 'filtro' => $this->tableFilters['revisar']['value'] ?? null]),
                    EmbeddedTable::make(),
                    View::make('nucleo::filament.parties.ultimos-cambios')->viewData(fn (): array => [
                        'cambios' => array_map(fn (array $c): array => [
                            ...$c,
                            'url' => $c['cotizacion_id'] === null ? null : VerCotizacion::getUrl(['record' => $c['cotizacion_id']]),
                        ], app(PortadaCotizaciones::class)->ultimosCambios()),
                    ]),
                ])->extraAttributes(['class' => 'tq-cotizaciones-principal']),
            ])->extraAttributes(['class' => 'tq-cotizaciones-portada']),
        ]);
    }

    /** @return Builder<Cotizacion> */
    private function consultaSinCarpeta(): Builder
    {
        $carpeta = $this->carpeta;
        $this->carpeta = null;

        try {
            return $this->getFilteredTableQuery() ?? Cotizacion::query();
        } finally {
            $this->carpeta = $carpeta;
        }
    }

    /** «+ Nueva cotización» desde la carpeta de una oportunidad la trae elegida. */
    private function oportunidadDeLaCarpeta(): ?int
    {
        return str_starts_with((string) $this->carpeta, 'oportunidad:') ? (int) substr((string) $this->carpeta, 12) : null;
    }

    private static function carpetaValida(?string $carpeta): ?string
    {
        return $carpeta !== null && preg_match('/^(cuenta|oportunidad):\d+$|^numero:[A-Z0-9-]{1,40}$/', $carpeta) === 1 ? $carpeta : null;
    }
}
