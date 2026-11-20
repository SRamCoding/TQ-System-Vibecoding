<?php

declare(strict_types=1);

namespace App\Modules\Crm\Licitaciones\Filament\Resources\Licitaciones\Pages;

use App\Modules\Crm\Cuentas\Services\CuentasService;
use App\Modules\Crm\Licitaciones\Enums\TipoProcedimiento;
use App\Modules\Crm\Licitaciones\Filament\Resources\Licitaciones\LicitacionResource;
use App\Modules\Crm\Licitaciones\Services\LicitacionesService;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Tiempo\Calendario;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** MVP rápido (t1-licitaciones): la lista, con «Registrar convocatoria» (carga manual, pública o privada). */
final class ListarLicitaciones extends ListRecords
{
    protected static string $resource = LicitacionResource::class;

    /** MV-023: la pestaña «Licitaciones» de la portada de Oportunidades. */
    public function content(Schema $schema): Schema
    {
        return $schema->components([
            View::make('oportunidades::portada.embudo-pestanas')->viewData(['actual' => 'licitaciones']),
            $this->getTabsContentComponent(),
            EmbeddedTable::make(),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('registrar')->label('Registrar convocatoria')->icon('heroicon-o-plus')
                ->visible(fn (): bool => LicitacionResource::puede(LicitacionesService::PERMISO_GESTIONAR))
                ->slideOver()->modalHeading('Registrar convocatoria')
                ->fillForm(fn (): array => [
                    'tipo' => TipoProcedimiento::AdjudicacionSimplificada->value,
                    'fecha_convocatoria' => Calendario::hoy(),
                    'cronograma' => array_map(fn (string $etapa, int $i): array => ['etapa' => $etapa, 'fecha' => CarbonImmutable::parse(Calendario::hoy())->addDays([0, 6, 11, 14, 19, 26][$i])->toDateString()], LicitacionesService::ETAPAS, array_keys(LicitacionesService::ETAPAS)),
                ])
                ->schema([
                    Select::make('cuenta_id')->label('Entidad o empresa')->required()->searchable()
                        ->getSearchResultsUsing(fn (string $search): array => collect(app(CuentasService::class)->buscar($search))->mapWithKeys(fn ($c): array => [$c->id => $c->etiqueta])->all())
                        ->getOptionLabelUsing(fn ($value): ?string => $value === null ? null : app(CuentasService::class)->resumen((int) $value)->etiqueta)
                        ->helperText('Una cuenta del CRM. Si no está, créala en Cuentas.'),
                    TextInput::make('nomenclatura')->label('Nomenclatura')->required()->maxLength(80)->placeholder('AS-SM-14-2026-MPVN-1'),
                    Select::make('tipo')->label('Procedimiento')->options(TipoProcedimiento::opciones())->required(),
                    Textarea::make('objeto')->label('Objeto')->required()->maxLength(500)->rows(2),
                    Select::make('linea_solucion_id')->label('Línea de solución')->options(fn (): array => DB::table('lineas_solucion')->orderBy('nombre')->pluck('nombre', 'id')->all()),
                    TextInput::make('valor_referencial')->label('Valor referencial (S/)')->numeric()->minValue(0),
                    DatePicker::make('fecha_convocatoria')->label('Fecha de convocatoria')->required()->format('Y-m-d'),
                    TextInput::make('url_bases')->label('Dirección de las bases')->url()->maxLength(500),
                    Repeater::make('cronograma')->label('Cronograma')->columns(2)->reorderable(false)
                        ->schema([
                            TextInput::make('etapa')->label('Etapa')->required()->maxLength(80),
                            DatePicker::make('fecha')->label('Fecha')->required()->format('Y-m-d'),
                        ]),
                ])
                ->action(function (array $data, Action $action): void {
                    try {
                        /** @var User $usuario */
                        $usuario = auth()->user();
                        $l = app(LicitacionesService::class)->registrar(
                            (int) $data['cuenta_id'], $data['nomenclatura'], TipoProcedimiento::from($data['tipo']), $data['objeto'],
                            filled($data['linea_solucion_id'] ?? null) ? (int) $data['linea_solucion_id'] : null,
                            filled($data['valor_referencial'] ?? null) ? (string) $data['valor_referencial'] : null,
                            CarbonImmutable::parse($data['fecha_convocatoria']), $data['url_bases'] ?? null,
                            array_map(fn (array $h): array => [$h['etapa'], $h['fecha']], array_values($data['cronograma'] ?? [])),
                            $usuario,
                        );
                    } catch (RuntimeException $e) {
                        Notification::make()->danger()->title($e->getMessage())->send();
                        $action->halt();

                        return;
                    }

                    Notification::make()->success()->title("Registrada como {$l->codigo}.")->send();
                    $this->redirect(VerLicitacion::getUrl(['record' => $l]));
                }),
        ];
    }
}
