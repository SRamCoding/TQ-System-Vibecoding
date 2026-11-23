<?php

declare(strict_types=1);

namespace App\Modules\Crm\Campanias\Filament\Resources\Campanias;

use App\Modules\Crm\Campanias\Filament\Resources\Campanias\Pages\ListarCampanias;
use App\Modules\Crm\Campanias\Filament\Resources\Campanias\Pages\VerCampania;
use App\Modules\Crm\Campanias\Filament\Resources\Campanias\RelationManagers\LeadsRelationManager;
use App\Modules\Crm\Campanias\Filament\Resources\Leads\LeadResource;
use App\Modules\Crm\Campanias\Models\Campania;
use App\Modules\Crm\Campanias\Services\CampaniasService;
use App\Modules\Crm\Campanias\Services\LeadsService;
use App\Modules\Nucleo\Filament\EtiquetasEnEspanol;
use BackedEnum;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/** MVP rápido (t1-campanias): las campañas y su detalle con cifras. */
final class CampaniaResource extends Resource
{
    use EtiquetasEnEspanol;

    protected static ?string $model = Campania::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-megaphone';

    protected static ?string $modelLabel = 'campaña';

    protected static ?string $pluralModelLabel = 'campañas';

    protected static ?string $slug = 'campanias';

    protected static bool $shouldRegisterNavigation = false;

    public static function canViewAny(): bool
    {
        return LeadResource::puede(LeadsService::PERMISO_VER);
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

    public static function table(Table $table): Table
    {
        $cifra = fn (string $campo): \Closure => fn (Campania $record): string => (string) app(LeadsService::class)->cifras($record->id)[$campo];

        return $table->defaultSort('desde', 'desc')
            ->columns([
                TextColumn::make('nombre')->label('Campaña')->weight('bold')->description(fn (Campania $record): string => Campania::TIPOS[$record->tipo] ?? $record->tipo),
                TextColumn::make('desde')->label('Periodo')->formatStateUsing(fn (Campania $record): string => $record->desde->format('d/m/Y').' – '.($record->hasta?->format('d/m/Y') ?? 'sin fin')),
                TextColumn::make('presupuesto')->label('Presupuesto')->alignEnd()->fontFamily('mono')->formatStateUsing(fn ($state): string => $state === null ? '—' : 'S/ '.number_format((float) $state, 2)),
                TextColumn::make('leads')->label('Leads')->alignEnd()->state($cifra('leads')),
                TextColumn::make('convertidos')->label('Convertidos')->alignEnd()->state(fn (Campania $record): string => ($c = app(LeadsService::class)->cifras($record->id))['convertidos']." · {$c['tasa']} %"),
                // MV-024: los resultados de la campaña, de un vistazo.
                TextColumn::make('monto')->label('Monto generado')->alignEnd()->fontFamily('mono')->state(fn (Campania $record): string => 'S/ '.number_format((float) app(LeadsService::class)->cifras($record->id)['monto'], 2)),
                TextColumn::make('costo_por_lead')->label('Costo por lead')->alignEnd()->fontFamily('mono')->state(fn (Campania $record): string => ($c = app(LeadsService::class)->cifras($record->id)['costo_por_lead']) === null ? '—' : 'S/ '.number_format((float) $c, 2)),
                TextColumn::make('es_activa')->label('')->badge()->formatStateUsing(fn (bool $state): string => $state ? 'activa' : 'cerrada')->color(fn (bool $state): string => $state ? 'success' : 'gray'),
            ])
            ->recordUrl(fn (Campania $record): string => VerCampania::getUrl(['record' => $record]));
    }

    public static function infolist(Schema $schema): Schema
    {
        $cifra = fn (string $campo) => fn (Campania $record): string => (string) app(LeadsService::class)->cifras($record->id)[$campo];

        return $schema->components([
            Section::make('La campaña')->columns(4)->schema([
                TextEntry::make('tipo')->label('Tipo')->formatStateUsing(fn (string $state): string => Campania::TIPOS[$state] ?? $state),
                TextEntry::make('desde')->label('Desde')->date('d/m/Y'),
                TextEntry::make('hasta')->label('Hasta')->date('d/m/Y')->placeholder('sin fin'),
                TextEntry::make('presupuesto')->label('Presupuesto')->fontFamily('mono')->formatStateUsing(fn ($state): string => $state === null ? '—' : 'S/ '.number_format((float) $state, 2)),
                TextEntry::make('objetivo')->label('Objetivo')->columnSpanFull()->placeholder('—'),
            ]),
            Section::make('Resultado')->columns(5)->schema([
                TextEntry::make('c_leads')->label('Leads')->state($cifra('leads')),
                TextEntry::make('c_conv')->label('Convertidos')->state($cifra('convertidos')),
                TextEntry::make('c_tasa')->label('Tasa de conversión')->state(fn (Campania $record): string => app(LeadsService::class)->cifras($record->id)['tasa'].' %'),
                TextEntry::make('c_monto')->label('En oportunidades')->fontFamily('mono')->state(fn (Campania $record): string => 'S/ '.number_format((float) app(LeadsService::class)->cifras($record->id)['monto'], 2)),
                TextEntry::make('c_cpl')->label('Costo por lead')->fontFamily('mono')->state(fn (Campania $record): string => ($v = app(LeadsService::class)->cifras($record->id)['costo_por_lead']) === null ? '—' : 'S/ '.number_format((float) $v, 2)),
            ]),
            // Observación 7.6: el retorno (gasto real y margen de lo ganado) y, en las campañas a clientes, su seguimiento.
            View::make('campanias::ficha')->columnSpanFull()
                ->viewData(fn (Campania $record): array => app(CampaniasService::class)->ficha($record)),
        ]);
    }

    public static function getRelations(): array
    {
        return [LeadsRelationManager::class];
    }

    public static function getPages(): array
    {
        return ['index' => ListarCampanias::route('/'), 'view' => VerCampania::route('/{record}')];
    }
}
