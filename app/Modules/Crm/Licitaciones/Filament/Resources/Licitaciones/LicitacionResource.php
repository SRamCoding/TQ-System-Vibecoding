<?php

declare(strict_types=1);

namespace App\Modules\Crm\Licitaciones\Filament\Resources\Licitaciones;

use App\Modules\Crm\Cuentas\Services\CuentasService;
use App\Modules\Crm\Licitaciones\Enums\EstadoLicitacion;
use App\Modules\Crm\Licitaciones\Enums\TipoProcedimiento;
use App\Modules\Crm\Licitaciones\Filament\Resources\Licitaciones\Pages\ListarLicitaciones;
use App\Modules\Crm\Licitaciones\Filament\Resources\Licitaciones\Pages\VerLicitacion;
use App\Modules\Crm\Licitaciones\Filament\Resources\Licitaciones\RelationManagers\ConsultasRelationManager;
use App\Modules\Crm\Licitaciones\Filament\Resources\Licitaciones\RelationManagers\CronogramaRelationManager;
use App\Modules\Crm\Licitaciones\Filament\Resources\Licitaciones\RelationManagers\ExpedienteRelationManager;
use App\Modules\Crm\Licitaciones\Filament\Resources\Licitaciones\RelationManagers\ResultadoRelationManager;
use App\Modules\Crm\Licitaciones\Models\Licitacion;
use App\Modules\Crm\Licitaciones\Services\LicitacionesService;
use App\Modules\Crm\Oportunidades\Filament\Resources\Oportunidades\Pages\VerOportunidad;
use App\Modules\Nucleo\Filament\EtiquetasEnEspanol;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Tiempo\Calendario;
use BackedEnum;
use Carbon\CarbonImmutable;
use Closure;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** MVP rápido (ficha t1-licitaciones): la lista de licitaciones y su ficha (CRM-04). */
final class LicitacionResource extends Resource
{
    use EtiquetasEnEspanol;

    protected static ?string $model = Licitacion::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-building-library';

    protected static ?string $navigationLabel = 'Licitaciones';

    protected static ?string $modelLabel = 'licitación';

    protected static ?string $pluralModelLabel = 'licitaciones';

    protected static ?string $slug = 'licitaciones';

    public static function canViewAny(): bool
    {
        return self::puede(LicitacionesService::PERMISO_VER);
    }

    public static function canView(Model $record): bool
    {
        return self::puede(LicitacionesService::PERMISO_VER);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function puede(string $permiso): bool
    {
        $usuario = Filament::auth()->user();

        return $usuario instanceof User && $usuario->can($permiso);
    }

    public static function soles(?string $monto): string
    {
        return $monto === null ? '—' : 'S/ '.number_format((float) $monto, 2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['cuenta.party', 'responsable', 'hitos']))
            ->defaultSort('fecha_convocatoria', 'desc')
            ->columns([
                TextColumn::make('codigo')->label('Código')->fontFamily('mono')->searchable()->sortable()
                    ->description(fn (Licitacion $record): string => $record->nomenclatura),
                TextColumn::make('objeto')->label('Objeto')->wrap()->searchable()->limit(90)
                    ->description(fn (Licitacion $record): string => (string) $record->cuenta?->party?->razon_social),
                TextColumn::make('tipo_procedimiento')->label('Procedimiento')->formatStateUsing(fn (TipoProcedimiento $state): string => $state->etiqueta())->toggleable(),
                TextColumn::make('valor_referencial')->label('Valor referencial')->alignEnd()->fontFamily('mono')->sortable()
                    ->formatStateUsing(fn (?string $state): string => self::soles($state)),
                TextColumn::make('proximo')->label('Próximo hito')
                    ->state(function (Licitacion $record): string {
                        $hito = $record->hitos->first(fn ($h): bool => ! $h->es_cumplido);

                        return $hito === null || ! $record->estado->esAbierta() ? '—' : "{$hito->etapa} · {$hito->fecha->format('d/m/Y')}";
                    }),
                TextColumn::make('estado')->label('Estado')->badge()
                    ->formatStateUsing(fn (EstadoLicitacion $state): string => $state->etiqueta())
                    ->color(fn (EstadoLicitacion $state): string => $state->color()),
                TextColumn::make('responsable.name')->label('Responsable')->toggleable(),
            ])
            ->filters([
                SelectFilter::make('estado')->label('Estado')->options(EstadoLicitacion::opciones()),
                SelectFilter::make('tipo_procedimiento')->label('Procedimiento')->options(TipoProcedimiento::opciones()),
            ])
            ->recordUrl(fn (Licitacion $record): string => VerLicitacion::getUrl(['record' => $record]))
            ->emptyStateHeading('Todavía no hay licitaciones')
            ->emptyStateDescription('Registra una convocatoria con «Registrar convocatoria».');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('La convocatoria')->columns(3)->schema([
                TextEntry::make('nomenclatura')->label('Nomenclatura')->fontFamily('mono')->copyable(),
                TextEntry::make('cuenta.party.razon_social')->label('Entidad o empresa'),
                TextEntry::make('tipo_procedimiento')->label('Procedimiento')->formatStateUsing(fn (TipoProcedimiento $state): string => $state->etiqueta()),
                TextEntry::make('objeto')->label('Objeto')->columnSpanFull(),
                TextEntry::make('valor_referencial')->label('Valor referencial')->fontFamily('mono')->formatStateUsing(fn (?string $state): string => self::soles($state)),
                TextEntry::make('fecha_convocatoria')->label('Convocada el')->date('d/m/Y'),
                TextEntry::make('es_publica')->label('Tipo')->formatStateUsing(fn (bool $state): string => $state ? 'Pública' : 'Privada'),
                TextEntry::make('estado')->label('Estado')->badge()
                    ->formatStateUsing(fn (EstadoLicitacion $state): string => $state->etiqueta())
                    ->color(fn (EstadoLicitacion $state): string => $state->color()),
                TextEntry::make('responsable.name')->label('Responsable')->placeholder('—'),
                TextEntry::make('oportunidad')->label('Oportunidad')
                    ->state(fn (Licitacion $record): string => $record->oportunidad_id === null ? 'Sin oportunidad (aún no se decide participar)' : 'Abrir la oportunidad')
                    ->url(fn (Licitacion $record): ?string => $record->oportunidad_id === null ? null : VerOportunidad::getUrl(['record' => $record->oportunidad_id]))
                    ->color(fn (Licitacion $record): ?string => $record->oportunidad_id === null ? null : 'primary'),
                TextEntry::make('monto_ofertado')->label('Nuestra oferta')->fontFamily('mono')->formatStateUsing(fn (?string $state): string => self::soles($state))->placeholder('—'),
                TextEntry::make('motivo_no_participa')->label('Por qué no participamos')->placeholder('—')
                    ->visible(fn (Licitacion $record): bool => $record->estado === EstadoLicitacion::NoParticipa),
            ]),
            Section::make('Histórico de la entidad')->collapsible()
                ->description(function (Licitacion $record): string {
                    $h = app(LicitacionesService::class)->historico($record->cuenta_id, $record->id);

                    return $h['minimo'] === null
                        ? 'Esta entidad aún no tiene resultados registrados con nosotros.'
                        : "Esta entidad adjudica entre el {$h['minimo']} % y el {$h['maximo']} % del referencial · ".count($h['filas']).' convocatorias.';
                })
                ->schema([
                    RepeatableEntry::make('historico')->hiddenLabel()->columns(6)
                        ->state(fn (Licitacion $record): array => app(LicitacionesService::class)->historico($record->cuenta_id, $record->id)['filas'])
                        ->schema([
                            TextEntry::make('codigo')->label('Código')->fontFamily('mono'),
                            TextEntry::make('objeto')->label('Objeto')->columnSpan(2),
                            TextEntry::make('adjudicado')->label('Adjudicado')->fontFamily('mono')->formatStateUsing(fn (?string $state): string => self::soles($state))->placeholder('—'),
                            TextEntry::make('porcentaje')->label('% del ref.')->formatStateUsing(fn (?string $state): string => $state === null ? '—' : "{$state} %")->placeholder('—'),
                            TextEntry::make('ganador')->label('Ganó')->placeholder('—'),
                        ]),
                ]),
        ]);
    }

    public static function getRelations(): array
    {
        return [CronogramaRelationManager::class, ExpedienteRelationManager::class, ConsultasRelationManager::class, ResultadoRelationManager::class];
    }

    /**
     * «Registrar convocatoria» (carga manual, pública o privada). Observación 7.1: la licitación entra a Oportunidades
     * como tarjeta «Por calificar». `$despues` recibe la licitación registrada.
     *
     * @param  (Closure(Licitacion): void)|null  $despues
     */
    public static function accionRegistrar(?Closure $despues = null): Action
    {
        return Action::make('registrar')->label('Registrar convocatoria')->icon('heroicon-o-plus')
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
                if ($despues !== null) {
                    $despues($l);
                }
            });
    }

    public static function getPages(): array
    {
        return [
            'index' => ListarLicitaciones::route('/'),
            'view' => VerLicitacion::route('/{record}'),
        ];
    }
}
