<?php

declare(strict_types=1);

namespace App\Modules\Crm\Licitaciones\Filament\Resources\Licitaciones;

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
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

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

    public static function getPages(): array
    {
        return [
            'index' => ListarLicitaciones::route('/'),
            'view' => VerLicitacion::route('/{record}'),
        ];
    }
}
