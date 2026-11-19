<?php

declare(strict_types=1);

namespace App\Modules\Crm\Cotizaciones\Filament\Resources\Cotizaciones;

use App\Modules\Crm\Cotizaciones\Condiciones\MargenPideAprobacion;
use App\Modules\Crm\Cotizaciones\CotizacionesServiceProvider;
use App\Modules\Crm\Cotizaciones\Filament\LineasDeCotizacion;
use App\Modules\Crm\Cotizaciones\Filament\Resources\Cotizaciones\Pages\ListarCotizaciones;
use App\Modules\Crm\Cotizaciones\Filament\Resources\Cotizaciones\Pages\VerCotizacion;
use App\Modules\Crm\Cotizaciones\Filament\VersionesDeCotizacion;
use App\Modules\Crm\Cotizaciones\Models\Cotizacion;
use App\Modules\Crm\Cotizaciones\Services\PortadaCotizaciones;
use App\Modules\Crm\Cuentas\Services\CuentasService;
use App\Modules\Crm\Oportunidades\Services\OportunidadesService;
use App\Modules\Nucleo\Filament\EtiquetasEnEspanol;
use App\Modules\Nucleo\Filament\PestanaHistorial;
use App\Modules\Nucleo\Models\FormaPago;
use App\Modules\Nucleo\Models\Moneda;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Seguridad\GrupoSensible;
use App\Modules\Nucleo\Services\ParametrosService;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Livewire\Component;

/**
 * «Cotizaciones» (D19): la lista con su portada y sus carpetas, y la ficha de cada versión. Solo presentación: todo
 * cambio pasa por las acciones `crm.cotizaciones.*`. Costo y margen solo con el permiso de su grupo (D15).
 */
final class CotizacionResource extends Resource
{
    use EtiquetasEnEspanol;

    protected static ?string $model = Cotizacion::class;

    protected static string|BackedEnum|null $navigationIcon = CotizacionesServiceProvider::ICONO;

    protected static ?string $navigationLabel = 'Cotizaciones';

    protected static ?string $modelLabel = 'cotización';

    protected static ?string $pluralModelLabel = 'cotizaciones';

    protected static ?string $slug = 'cotizaciones';

    public static function canViewAny(): bool
    {
        return self::usuarioPuede(CotizacionesServiceProvider::PERMISO_VER);
    }

    public static function canView(Model $record): bool
    {
        return self::usuarioPuede(CotizacionesServiceProvider::PERMISO_VER);
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

    public static function usuario(): ?User
    {
        $usuario = Filament::auth()->user();

        return $usuario instanceof User ? $usuario : null;
    }

    public static function usuarioPuede(string $permiso): bool
    {
        return self::usuario()?->can($permiso) ?? false;
    }

    /** D15: el margen y el costo son del grupo sensible; sin el permiso, sus columnas no aparecen. */
    public static function ve(GrupoSensible $grupo): bool
    {
        return self::usuarioPuede($grupo->permiso());
    }

    public static function table(Table $table): Table
    {
        return $table
            // La carpeta elegida y «Solo mías» los pone la portada (`$livewire`, el nombre que inyecta Filament).
            ->modifyQueryUsing(fn (Builder $query, Component $livewire): Builder => $livewire instanceof ListarCotizaciones
                ? $livewire->recortar($query->with('estado'))
                : $query->with('estado'))
            ->defaultSort(fn (Builder $query): Builder => $query->orderByDesc('numero')->orderByDesc('numero_version'))
            ->columns([
                TextColumn::make('numero')->label('Número')->fontFamily('mono')->searchable()
                    ->formatStateUsing(fn (Cotizacion $record): string => "{$record->numero} v{$record->numero_version}"),
                TextColumn::make('cuenta_id')->label('Cliente')->wrap()
                    ->formatStateUsing(fn (int $state): string => self::cuenta($state))
                    ->description(fn (Cotizacion $record): string => self::oportunidad($record->oportunidad_id)),
                // El color va con el nombre del estado (interfaz.md, «Reglas»).
                TextColumn::make('estado_id')->label('Estado')->badge()
                    ->formatStateUsing(fn (Cotizacion $record): string => $record->estado->nombre.($record->es_aceptada ? ' · la aceptada' : ''))
                    ->color(fn (Cotizacion $record): string => $record->estado->colorEnPantalla()),
                TextColumn::make('total')->label('Total')->alignEnd()->fontFamily('mono')
                    ->formatStateUsing(fn (Cotizacion $record): string => self::monto($record->moneda_id, (string) $record->total)),
                TextColumn::make('margen_porcentaje')->label('Margen')->alignEnd()->fontFamily('mono')->placeholder('—')
                    ->formatStateUsing(fn (?string $state): string => $state === null ? '—' : "{$state} %")
                    ->visible(fn (): bool => self::ve(GrupoSensible::Margen)),
                TextColumn::make('fecha_vencimiento')->label('Vence')->date('d/m/Y')->placeholder('Sin emitir')->sortable(),
            ])
            ->filters([
                SelectFilter::make('revisar')->label('Revisar')
                    ->options(['por-vencer' => 'Por vencer'])
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null) ? app(PortadaCotizaciones::class)->enAviso($query, (string) $data['value']) : $query),
            ])
            ->recordUrl(fn (Cotizacion $record): string => VerCotizacion::getUrl(['record' => $record]))
            ->emptyStateHeading('Sin cotizaciones')
            ->emptyStateDescription('Una cotización nace de una oportunidad abierta: «Nueva cotización».');
    }

    /**
     * D19: totales y margen arriba (el margen con su objetivo y su mínimo, solo con «Márgenes»; el costo, con «Costos»),
     * las condiciones, y en pestañas las líneas, las versiones (con el comparador) y el historial.
     */
    public static function infolist(Schema $schema): Schema
    {
        $monto = fn (string $campo, string $etiqueta): TextEntry => TextEntry::make($campo)->label($etiqueta)->fontFamily('mono')
            ->formatStateUsing(fn (Cotizacion $record, ?string $state): string => self::monto($record->moneda_id, (string) $state));

        return $schema->components([
            Section::make('Totales')->columns(['default' => 2, 'md' => 3, 'xl' => 6])->compact()
                ->description(fn (): ?string => self::ve(GrupoSensible::Margen) ? null : 'El costo y el margen se ven con el permiso de ver costos y márgenes.')
                ->schema([
                    $monto('subtotal', 'Subtotal sin IGV'),
                    $monto('descuento_total', 'Descuentos'),
                    $monto('igv', 'IGV')->helperText(fn (Cotizacion $record): string => "{$record->igv_porcentaje} %"),
                    $monto('total', 'Total con IGV')->weight('bold'),
                    $monto('costo_total', 'Costo')->visible(fn (): bool => self::ve(GrupoSensible::Costos)),
                    TextEntry::make('margen_porcentaje')->label('Margen')->fontFamily('mono')->placeholder('—')
                        ->formatStateUsing(fn (?string $state): string => $state === null ? '—' : "{$state} %")
                        ->helperText(fn (Cotizacion $record): string => self::metaDeMargen($record))
                        ->color(fn (Cotizacion $record): ?string => self::bajoElMinimo($record) ? 'danger' : null)
                        ->visible(fn (): bool => self::ve(GrupoSensible::Margen)),
                ]),
            Section::make('Condiciones')->columns(['default' => 1, 'md' => 2, 'xl' => 4])->compact()->schema([
                TextEntry::make('plazo_entrega_dias')->label('Plazo de entrega')->placeholder('—')
                    ->formatStateUsing(fn (?int $state): string => $state === null ? '—' : "{$state} días calendario"),
                TextEntry::make('forma_pago_id')->label('Forma de pago')->placeholder('—')
                    ->state(fn (Cotizacion $record): ?string => self::formaDePago($record)),
                TextEntry::make('garantia_meses')->label('Garantía')->placeholder('—')
                    ->formatStateUsing(fn (?int $state): string => $state === null ? '—' : "{$state} meses"),
                TextEntry::make('lugar_entrega')->label('Lugar de entrega')->placeholder('—'),
            ]),
            Tabs::make('ficha')->columnSpanFull()->tabs([
                Tab::make('Líneas')->icon('heroicon-o-list-bullet')->schema([
                    Livewire::make(LineasDeCotizacion::class, fn (Cotizacion $record): array => ['cotizacionId' => $record->id])->key('lineas-de-cotizacion'),
                ]),
                Tab::make('Versiones')->icon('heroicon-o-document-duplicate')->schema([
                    Livewire::make(VersionesDeCotizacion::class, fn (Cotizacion $record): array => ['cotizacionId' => $record->id])->key('versiones-de-cotizacion'),
                ]),
                PestanaHistorial::make(HistorialDeCotizacion::class, fn (Cotizacion $record): int => $record->id),
            ]),
        ]);
    }

    /** «Borrador · PEN · TC 3.7500 · válida 15 días · vence 30/11/2026 · OP-2026-0012 · Videovigilancia» (D19). */
    public static function subtitulo(Cotizacion $c): string
    {
        return implode(' · ', array_filter([
            $c->estado->nombre.($c->es_aceptada ? ' (la aceptada)' : ''),
            self::moneda($c->moneda_id),
            $c->tipo_cambio === null ? null : "TC {$c->tipo_cambio}",
            "válida {$c->dias_validez} días",
            $c->fecha_vencimiento === null ? null : 'vence '.$c->fecha_vencimiento->format('d/m/Y'),
            self::oportunidad($c->oportunidad_id),
        ]));
    }

    /** «objetivo 25.00 % · mínimo 22.00 %», y si está bajo el mínimo, que pide aprobación (D14). */
    public static function metaDeMargen(Cotizacion $c): string
    {
        // Mientras no se fijan en la versión (al pedir o emitir), los del parámetro (D14).
        $objetivo = (string) ($c->margen_objetivo ?? app(ParametrosService::class)->decimal(CotizacionesServiceProvider::PARAMETRO_MARGEN_OBJETIVO));
        $meta = "objetivo {$objetivo} % · mínimo ".MargenPideAprobacion::minimo($c).' %';

        return self::bajoElMinimo($c) ? "{$meta} · requiere aprobación" : $meta;
    }

    /** D14: con líneas y el margen bajo el mínimo, se pide aprobación antes de emitir. */
    public static function bajoElMinimo(Cotizacion $c): bool
    {
        return $c->margen_porcentaje !== null && MargenPideAprobacion::bajoElMinimo($c);
    }

    private static function formaDePago(Cotizacion $c): ?string
    {
        $partes = array_filter([
            $c->forma_pago_id === null ? null : (string) FormaPago::query()->whereKey($c->forma_pago_id)->value('nombre'),
            $c->forma_pago_detalle,
        ]);

        return $partes === [] ? null : implode(' · ', $partes);
    }

    /** «PEN 60,939.92». */
    public static function monto(int $monedaId, string $monto): string
    {
        return self::moneda($monedaId).' '.number_format((float) $monto, 2);
    }

    public static function moneda(int $monedaId): string
    {
        return once(fn (): string => (string) Moneda::query()->whereKey($monedaId)->value('codigo'));
    }

    /** La razón social o nombre comercial de la cuenta, por C3 (nunca su modelo). */
    public static function cuenta(int $cuentaId): string
    {
        return once(fn (): string => app(CuentasService::class)->resumen($cuentaId)->etiqueta);
    }

    /** «OP-2026-0012 · Videovigilancia», por C13. */
    public static function oportunidad(int $oportunidadId): string
    {
        return once(fn (): string => app(OportunidadesService::class)->resumen($oportunidadId)->etiqueta());
    }

    public static function getPages(): array
    {
        return [
            'index' => ListarCotizaciones::route('/'),
            'view' => VerCotizacion::route('/{record}'),
        ];
    }
}
