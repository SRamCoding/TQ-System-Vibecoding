<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Filament\Resources\Numeracion;

use App\Modules\Nucleo\Estados\DefinicionDocumento;
use App\Modules\Nucleo\Estados\RegistroDocumentos;
use App\Modules\Nucleo\Filament\Clusters\Configuracion;
use App\Modules\Nucleo\Filament\EtiquetasEnEspanol;
use App\Modules\Nucleo\Filament\Resources\Numeracion\Pages\ListarSeriesDocumento;
use App\Modules\Nucleo\Models\SerieDocumento;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Numeracion\ReinicioNumeracion;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Numeración (plan 1.6, D5): cada contador con su último número, por documento, ámbito y periodo. Solo lectura: las
 * series las declara el código de cada módulo y los números los toma `NumeracionService` al crear cada documento.
 */
final class SerieDocumentoResource extends Resource
{
    use EtiquetasEnEspanol;

    protected static ?string $model = SerieDocumento::class;

    protected static ?string $cluster = Configuracion::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-hashtag';

    protected static ?string $modelLabel = 'serie';

    protected static ?string $pluralModelLabel = 'series de numeración';

    protected static ?string $navigationLabel = 'Numeración';

    protected static ?string $slug = 'numeracion';

    protected static ?int $navigationSort = 12;

    public static function canViewAny(): bool
    {
        $usuario = Filament::auth()->user();

        return $usuario instanceof User && $usuario->can('catalogos.ver');
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

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort(fn (Builder $query) => $query->orderBy('documento_type')->orderBy('ambito')->orderByDesc('periodo'))
            ->columns([
                TextColumn::make('documento_type')->label('Documento')
                    ->formatStateUsing(fn (string $state): string => self::nombre($state)),
                TextColumn::make('serie')->label('Serie')->fontFamily('mono')->searchable(),
                TextColumn::make('ambito')->label('Ámbito')
                    ->formatStateUsing(fn (string $state): string => $state === '' ? 'Toda la empresa' : $state)
                    ->placeholder('Toda la empresa'),
                TextColumn::make('periodo')->label('Periodo')->placeholder('No reinicia'),
                TextColumn::make('ultimo_numero')->label('Último número')
                    ->formatStateUsing(fn (SerieDocumento $record): string => self::ultimo($record))
                    ->fontFamily('mono'),
                TextColumn::make('reinicio')->label('Reinicia')
                    ->state(fn (SerieDocumento $record): string => match (self::definicion($record->documento_type)?->numeracion()?->reinicio) {
                        ReinicioNumeracion::Anual => 'Cada año',
                        ReinicioNumeracion::Mensual => 'Cada mes',
                        ReinicioNumeracion::Nunca => 'Nunca',
                        null => 'Ya no se numera',
                    }),
            ])
            ->filters([
                SelectFilter::make('documento_type')->label('Documento')
                    ->options(fn (): array => collect(app(RegistroDocumentos::class)->definiciones())
                        ->filter(fn (DefinicionDocumento $definicion): bool => $definicion->numeracion() !== null)
                        ->mapWithKeys(fn (DefinicionDocumento $definicion): array => [$definicion->tipo() => ucfirst($definicion->nombre())])
                        ->all()),
            ])
            ->emptyStateHeading('Todavía no se emitió ningún número')
            ->emptyStateDescription('Cada documento recibe su número al crearse; aquí aparece su serie con el último número usado.');
    }

    private static function definicion(string $tipo): ?DefinicionDocumento
    {
        return app(RegistroDocumentos::class)->definicionDe($tipo);
    }

    private static function nombre(string $tipo): string
    {
        $definicion = self::definicion($tipo);

        return $definicion === null ? $tipo : ucfirst($definicion->nombre());
    }

    /** `REQ-2026-0012`, con el formato que declara el documento; sin declaración, el número solo. */
    private static function ultimo(SerieDocumento $serie): string
    {
        $formato = self::definicion($serie->documento_type)?->numeracion();

        return $formato === null ? (string) $serie->ultimo_numero : $formato->numero($serie->periodo, $serie->ultimo_numero, $serie->ambito);
    }

    public static function getPages(): array
    {
        return ['index' => ListarSeriesDocumento::route('/')];
    }
}
