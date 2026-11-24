<?php

declare(strict_types=1);

namespace App\Modules\Crm\Oportunidades\Filament\Resources\Oportunidades\Pages;

use App\Modules\Crm\Oportunidades\Filament\MuestraLasVistas;
use App\Modules\Crm\Oportunidades\Filament\Resources\Oportunidades\OportunidadResource;
use App\Modules\Crm\Oportunidades\Filament\VistasDeOportunidades;
use App\Modules\Crm\Oportunidades\OportunidadesServiceProvider;
use App\Modules\Crm\Oportunidades\Services\ResultadosOportunidades;
use App\Modules\Nucleo\Tiempo\Calendario;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;

/**
 * Observación 9.5: la cuarta vista de Oportunidades, «Resultados» (lienzos `oportunidades-mejoras/Perdidas.dc.html` y
 * `Pronostico.dc.html`): ¿por qué perdemos? y contra quién, el pronóstico de cierres de los próximos 6 meses y las metas
 * de cada ejecutivo, con selector de periodo. Las metas se cargan en «Metas» (persona, trimestre y monto).
 */
final class VistaResultados extends Page
{
    use MuestraLasVistas;

    protected static string $resource = OportunidadResource::class;

    protected static ?string $title = 'Resultados';

    protected static ?string $breadcrumb = 'Resultados';

    #[Url(as: 'periodo', except: 'trimestre')]
    public string $periodo = 'trimestre';

    public static function canAccess(array $parameters = []): bool
    {
        return OportunidadResource::usuarioPuede(OportunidadesServiceProvider::PERMISO_VER);
    }

    public static function vista(): string
    {
        return VistasDeOportunidades::RESULTADOS;
    }

    public function mount(): void
    {
        $this->calcularCifras();
    }

    /** Observación 9.5 (5): la pantalla simple de metas: persona, trimestre y monto, del año en curso. */
    public function metasAction(): Action
    {
        $anio = CarbonImmutable::parse(Calendario::hoy())->year;

        return Action::make('metas')->label('Metas')->icon('heroicon-o-flag')->color('gray')
            ->modalHeading("Metas comerciales de {$anio}")->modalDescription('Cada ejecutivo con su meta por trimestre, en soles. Lo ganado se compara contra ella.')
            ->fillForm(fn (): array => ['filas' => app(ResultadosOportunidades::class)->metasGuardadas($anio)])
            ->schema([
                Repeater::make('filas')->hiddenLabel()->addActionLabel('Agregar meta')->columns(3)->defaultItems(0)->schema([
                    Select::make('user_id')->label('Persona')->required()->searchable()
                        ->options(fn (): array => DB::table('users')->where('es_sistema', false)->orderBy('name')->pluck('name', 'id')->all()),
                    Select::make('trimestre')->label('Trimestre')->required()->options([1 => '1.º trimestre', 2 => '2.º trimestre', 3 => '3.º trimestre', 4 => '4.º trimestre']),
                    TextInput::make('monto')->label('Monto (S/)')->required()->numeric()->minValue(0),
                ]),
            ])
            ->action(function (array $data) use ($anio): void {
                app(ResultadosOportunidades::class)->guardarMetas($anio, array_map(fn (array $f): array => [...$f, 'anio' => $anio], $data['filas'] ?? []), (int) auth()->id());
                Notification::make()->success()->title('Metas guardadas.')->send();
            });
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            $this->cabeceraDeVistas(),
            View::make('oportunidades::resultados.pantalla')->viewData(function (): array {
                $r = app(ResultadosOportunidades::class);
                $periodo = array_key_exists($this->periodo, ResultadosOportunidades::PERIODOS) ? $this->periodo : 'trimestre';

                return ['rango' => $r->rango($periodo)[2], 'perdemos' => $r->porQuePerdemos($periodo), 'pronostico' => $r->pronostico(), 'metas' => $r->metas($periodo), 'periodos' => ResultadosOportunidades::PERIODOS];
            }),
        ]);
    }
}
