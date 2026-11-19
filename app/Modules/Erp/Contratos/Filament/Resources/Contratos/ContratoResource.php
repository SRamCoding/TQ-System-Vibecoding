<?php

declare(strict_types=1);

namespace App\Modules\Erp\Contratos\Filament\Resources\Contratos;

use App\Modules\Erp\Contratos\Filament\Resources\Contratos\Pages\ListarContratos;
use App\Modules\Erp\Contratos\Filament\Resources\Contratos\Pages\VerContrato;
use App\Modules\Erp\Contratos\Filament\Resources\Contratos\RelationManagers\AdendasRelationManager;
use App\Modules\Erp\Contratos\Filament\Resources\Contratos\RelationManagers\GarantiasRelationManager;
use App\Modules\Erp\Contratos\Filament\Resources\Contratos\RelationManagers\HitosRelationManager;
use App\Modules\Erp\Contratos\Filament\Resources\Contratos\RelationManagers\PenalidadesRelationManager;
use App\Modules\Erp\Contratos\Models\Contrato;
use App\Modules\Erp\Contratos\Services\ContratosService;
use App\Modules\Erp\Proyectos\Filament\Resources\Proyectos\Pages\VerProyecto;
use App\Modules\Nucleo\Filament\EtiquetasEnEspanol;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Tiempo\Calendario;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/** MVP rápido (ficha t2-contratos): la lista de contratos y su ficha con los tres números de todos los días. */
final class ContratoResource extends Resource
{
    use EtiquetasEnEspanol;

    protected static ?string $model = Contrato::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-check';

    protected static ?string $modelLabel = 'contrato';

    protected static ?string $pluralModelLabel = 'contratos';

    protected static ?string $slug = 'contratos';

    public static function canViewAny(): bool
    {
        return self::puede(ContratosService::PERMISO_VER);
    }

    public static function canView(Model $record): bool
    {
        return self::canViewAny();
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
        $u = Filament::auth()->user();

        return $u instanceof User && $u->can($permiso);
    }

    public static function soles(mixed $m): string
    {
        return $m === null ? '—' : 'S/ '.number_format((float) $m, 2);
    }

    /** «13 días restantes de 30», «vencido hace 5 días» o «culminado». */
    public static function dias(Contrato $c): string
    {
        $s = app(ContratosService::class);

        if ($c->estado !== 'vigente') {
            return Contrato::ESTADOS[$c->estado];
        }

        $restan = (int) CarbonImmutable::parse(Calendario::hoy())->diffInDays($s->fin($c), false);

        return $restan >= 0 ? "{$restan} días restantes de {$s->plazoVigente($c)}" : 'vencido hace '.abs($restan).' días';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('cliente'))
            ->defaultSort('codigo', 'desc')
            ->columns([
                TextColumn::make('codigo')->label('Contrato')->fontFamily('mono')->searchable()->description(fn (Contrato $record): ?string => $record->numero_entidad),
                TextColumn::make('objeto')->label('Objeto')->wrap()->searchable()->limit(80)->description(fn (Contrato $record): string => (string) $record->cliente?->razon_social),
                TextColumn::make('monto')->label('Monto con IGV')->alignEnd()->fontFamily('mono')
                    ->state(fn (Contrato $record): string => self::soles(app(ContratosService::class)->montoVigente($record))),
                TextColumn::make('plazo')->label('Plazo')->state(fn (Contrato $record): string => self::dias($record))
                    ->color(fn (Contrato $record): ?string => str_starts_with(self::dias($record), 'vencido') ? 'danger' : null),
                TextColumn::make('diaria')->label('Penalidad diaria')->alignEnd()->fontFamily('mono')
                    ->state(fn (Contrato $record): string => self::soles(app(ContratosService::class)->penalidadDiaria($record))),
                TextColumn::make('estado')->label('Estado')->badge()->formatStateUsing(fn (string $state): string => Contrato::ESTADOS[$state])->color(fn (string $state): string => Contrato::COLORES[$state]),
            ])
            ->filters([SelectFilter::make('estado')->label('Estado')->options(Contrato::ESTADOS)])
            ->recordUrl(fn (Contrato $record): string => VerContrato::getUrl(['record' => $record]));
    }

    public static function infolist(Schema $schema): Schema
    {
        $s = fn (): ContratosService => app(ContratosService::class);

        return $schema->components([
            Section::make('Lo que hay que mirar todos los días')->columns(4)->schema([
                TextEntry::make('d_plazo')->label('Plazo')->state(fn (Contrato $record): string => self::dias($record))->weight('bold')->size('lg'),
                TextEntry::make('d_diaria')->label('Penalidad diaria')->fontFamily('mono')->weight('bold')->size('lg')
                    ->state(fn (Contrato $record): string => self::soles($s()->penalidadDiaria($record)))
                    ->helperText(fn (Contrato $record): string => '0.10 × monto ÷ ('.$record->penalidad_factor.' × '.$s()->plazoVigente($record).' días) · tope '.self::soles($s()->tope($record)).' ('.rtrim(rtrim((string) $record->penalidad_tope_pct, '0'), '.').' %)'),
                TextEntry::make('d_aplicada')->label('Penalidad aplicada')->fontFamily('mono')->state(fn (Contrato $record): string => self::soles($s()->penalidadAplicada($record)))
                    ->color(fn (Contrato $record): ?string => bccomp($s()->penalidadAplicada($record), '0', 2) > 0 ? 'danger' : null),
                TextEntry::make('d_garantia')->label('Garantía')->state(function (Contrato $record): string {
                    $g = $record->garantias()->whereIn('estado', ['vigente', 'renovada'])->orderBy('vencimiento')->first();

                    return $g === null ? 'Sin garantía vigente' : ContratosService::TIPOS_GARANTIA[$g->tipo].' · '.self::soles($g->monto).' · vence '.$g->vencimiento->format('d/m/Y');
                }),
            ]),
            Section::make('El contrato')->columns(3)->schema([
                TextEntry::make('cliente.razon_social')->label('Entidad o cliente'),
                TextEntry::make('numero_entidad')->label('N.° de contrato de la entidad')->placeholder('Por completar'),
                TextEntry::make('origen')->label('Origen')->state(fn (Contrato $record): string => $record->oportunidad_id === null ? '—' : 'Oportunidad '.DB::table('oportunidades')->where('id', $record->oportunidad_id)->value('numero')),
                TextEntry::make('objeto')->label('Objeto')->columnSpanFull(),
                TextEntry::make('m_original')->label('Monto contractual')->fontFamily('mono')->state(fn (Contrato $record): string => self::soles($record->monto).' inc. IGV'),
                TextEntry::make('m_vigente')->label('Con adendas')->fontFamily('mono')->state(fn (Contrato $record): string => self::soles($s()->montoVigente($record))),
                TextEntry::make('venta')->label('Vendido neto (sin IGV, menos penalidades)')->fontFamily('mono')->state(fn (Contrato $record): string => self::soles($s()->ventaNeta($record->proyecto_id))),
                TextEntry::make('fecha_firma')->label('Firma')->date('d/m/Y')->placeholder('—'),
                TextEntry::make('fecha_inicio')->label('Inicio')->date('d/m/Y'),
                TextEntry::make('fin')->label('Fin (con adendas)')->state(fn (Contrato $record): string => $s()->fin($record)->format('d/m/Y')),
                TextEntry::make('forma_pago')->label('Forma de pago')->placeholder('—'),
                TextEntry::make('garantia_meses')->label('Garantía técnica')->formatStateUsing(fn ($state): string => $state === null ? '—' : "{$state} meses")->placeholder('—'),
                TextEntry::make('proyecto')->label('Proyecto')->state(fn (Contrato $record): string => (string) DB::table('proyectos')->where('id', $record->proyecto_id)->value('codigo'))
                    ->url(fn (Contrato $record): string => VerProyecto::getUrl(['record' => $record->proyecto_id]))->color('primary'),
            ]),
        ]);
    }

    public static function getRelations(): array
    {
        return [HitosRelationManager::class, GarantiasRelationManager::class, AdendasRelationManager::class, PenalidadesRelationManager::class];
    }

    public static function getPages(): array
    {
        return ['index' => ListarContratos::route('/'), 'view' => VerContrato::route('/{record}')];
    }
}
