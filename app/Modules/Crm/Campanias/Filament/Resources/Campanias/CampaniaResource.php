<?php

declare(strict_types=1);

namespace App\Modules\Crm\Campanias\Filament\Resources\Campanias;

use App\Modules\Crm\Campanias\Filament\Resources\Campanias\Pages\ListarCampanias;
use App\Modules\Crm\Campanias\Filament\Resources\Campanias\Pages\VerCampania;
use App\Modules\Crm\Campanias\Filament\Resources\Leads\LeadResource;
use App\Modules\Crm\Campanias\Models\Campania;
use App\Modules\Crm\Campanias\Services\LeadsService;
use App\Modules\Nucleo\Filament\EtiquetasEnEspanol;
use BackedEnum;
use Filament\Resources\Resource;
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
        // Observación 9.10: la ficha completa (cabecera, cifras y pestañas) la dibuja su vista.
        return $schema->components([
            View::make('campanias::ficha-completa')->columnSpanFull()->viewData(fn ($livewire): array => $livewire->datosFicha()),
        ]);
    }

    public static function getRelations(): array
    {
        // Observación 9.10: los contactos van en la pestaña «Contactos» de la ficha.
        return [];
    }

    public static function getPages(): array
    {
        return ['index' => ListarCampanias::route('/'), 'view' => VerCampania::route('/{record}')];
    }
}
