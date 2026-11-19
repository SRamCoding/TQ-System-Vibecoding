<?php

declare(strict_types=1);

namespace App\Modules\Erp\Presupuestos\Filament\Resources\Presupuestos;

use App\Modules\Erp\Presupuestos\Filament\FormulaDePresupuesto;
use App\Modules\Erp\Presupuestos\Filament\ListaDeInsumosDePresupuesto;
use App\Modules\Erp\Presupuestos\Filament\Montos;
use App\Modules\Erp\Presupuestos\Filament\PartidasDePresupuesto;
use App\Modules\Erp\Presupuestos\Filament\Resources\Presupuestos\Pages\ListarPresupuestos;
use App\Modules\Erp\Presupuestos\Filament\Resources\Presupuestos\Pages\VerPresupuesto;
use App\Modules\Erp\Presupuestos\Models\Presupuesto;
use App\Modules\Erp\Presupuestos\PresupuestosServiceProvider;
use App\Modules\Erp\Presupuestos\Services\ConsultaPresupuestos;
use App\Modules\Erp\Presupuestos\Services\PortadaPresupuestos;
use App\Modules\Erp\Proyectos\Services\ProyectoRechazado;
use App\Modules\Erp\Proyectos\Services\ProyectosService;
use App\Modules\Nucleo\Filament\EtiquetasEnEspanol;
use App\Modules\Nucleo\Filament\PestanaHistorial;
use App\Modules\Nucleo\Models\Moneda;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Seguridad\GrupoSensible;
use App\Modules\Nucleo\Services\ParametrosService;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Select;
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

/**
 * «Presupuestos» (plan 2.10, D1, D2 y D4): la lista con su portada y el catálogo de insumos en el panel deslizante, y
 * la ficha con sus totales, sus partidas e historial. Solo presentación: todo cambio pasa por las acciones
 * `erp.presupuestos.*`.
 */
final class PresupuestoResource extends Resource
{
    use EtiquetasEnEspanol;

    protected static ?string $model = Presupuesto::class;

    protected static string|BackedEnum|null $navigationIcon = PresupuestosServiceProvider::ICONO;

    protected static ?string $navigationLabel = 'Presupuestos';

    protected static ?string $modelLabel = 'presupuesto';

    protected static ?string $pluralModelLabel = 'presupuestos';

    protected static ?string $slug = 'presupuestos';

    public static function canViewAny(): bool
    {
        return self::usuarioPuede(PresupuestosServiceProvider::PERMISO_VER);
    }

    public static function canView(Model $record): bool
    {
        return self::usuarioPuede(PresupuestosServiceProvider::PERMISO_VER);
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

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('estado'))
            ->defaultSort(fn (Builder $query): Builder => $query->orderByDesc('numero')->orderByDesc('numero_version'))
            ->columns([
                TextColumn::make('numero')->label('Número')->fontFamily('mono')->searchable()
                    ->description(fn (Presupuesto $record): string => "Versión {$record->numero_version}"),
                TextColumn::make('nombre')->label('Nombre')->wrap()->searchable(),
                // El color va con el nombre del estado (interfaz.md, «Reglas»).
                TextColumn::make('estado_id')->label('Estado')->badge()
                    ->formatStateUsing(fn (Presupuesto $record): string => $record->estado->nombre.($record->es_vigente ? ' · vigente' : ''))
                    ->color(fn (Presupuesto $record): string => $record->estado->colorEnPantalla()),
                TextColumn::make('proyecto_id')->label('Proyecto')->placeholder('Sin proyecto')
                    ->formatStateUsing(fn (?int $state): string => self::proyecto($state) ?? 'Sin proyecto'),
                TextColumn::make('total')->label('Total')->alignEnd()->fontFamily('mono')
                    ->formatStateUsing(fn (string $state): string => Montos::siVisible($state)),
            ])
            ->filters([
                SelectFilter::make('revisar')->label('Revisar')
                    ->options(['sin-producto' => 'Con materiales sin producto'])
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null) ? app(PortadaPresupuestos::class)->enAviso($query, (string) $data['value']) : $query),
            ])
            ->recordUrl(fn (Presupuesto $record): string => VerPresupuesto::getUrl(['record' => $record]))
            ->emptyStateHeading('Sin presupuestos');
    }

    /** D4: los totales arriba, como en obra pública (gastos generales y utilidad al pie), y en pestañas lo demás. */
    public static function infolist(Schema $schema): Schema
    {
        $monto = fn (string $campo, string $etiqueta, GrupoSensible $grupo = GrupoSensible::Costos): TextEntry => TextEntry::make($campo)->label($etiqueta)->fontFamily('mono')
            ->formatStateUsing(fn (string $state): string => Montos::siVisible($state, $grupo));

        return $schema->components([
            Section::make('Totales')->columns(['default' => 2, 'md' => 3, 'xl' => 6])->compact()
                ->description(fn (): ?string => match (true) {
                    ! Montos::visibles() => 'Los montos se ven con el permiso de ver costos.',
                    ! Montos::visibles(GrupoSensible::Margen) => 'La utilidad se ve con el permiso de ver márgenes.',
                    default => null,
                })
                ->schema([
                    $monto('costo_directo', 'Costo directo'),
                    $monto('gastos_generales', 'Gastos generales')
                        ->helperText(fn (Presupuesto $record): string => Montos::texto($record->gastos_generales_pct).' %'),
                    // ADR 0016: la utilidad y su porcentaje son del grupo «margen», no de «costos».
                    $monto('utilidad', 'Utilidad', GrupoSensible::Margen)
                        ->helperText(fn (Presupuesto $record): ?string => Montos::visibles(GrupoSensible::Margen) ? Montos::texto($record->utilidad_pct).' %' : null),
                    $monto('igv', 'IGV')
                        ->helperText(fn (Presupuesto $record): string => Montos::texto($record->igv_pct).' %'),
                    $monto('total', 'Total')->weight('bold'),
                    // D10 de la 2.9: cuánto del costo directo va sin detallar, para verlo antes de ofertar o aprobar.
                    TextEntry::make('sin_detallar')->label('Sin detallar')->fontFamily('mono')
                        ->state(fn (Presupuesto $record): string => Montos::siVisible(app(ConsultaPresupuestos::class)->sinDetallar($record->id)))
                        ->visible(fn (Presupuesto $record): bool => $record->usa_apu),
                ]),
            Tabs::make('ficha')->columnSpanFull()->tabs([
                Tab::make('Partidas')->icon('heroicon-o-numbered-list')->schema([
                    Livewire::make(PartidasDePresupuesto::class, fn (Presupuesto $record): array => ['presupuestoId' => $record->id])->key('partidas-de-presupuesto'),
                ]),
                // Paso 7: con APU, lo que lleva todo el presupuesto por insumo (D5 y D10 de la 2.9).
                Tab::make('Lista de insumos')->icon('heroicon-o-square-3-stack-3d')
                    ->visible(fn (Presupuesto $record): bool => $record->usa_apu)
                    ->schema([
                        Livewire::make(ListaDeInsumosDePresupuesto::class, fn (Presupuesto $record): array => ['presupuestoId' => $record->id])->key('lista-de-insumos'),
                    ]),
                // MVP rápido (t2-formula-polinomica): la fórmula de reajuste, que se aplica en cada valorización.
                Tab::make('Fórmula polinómica')->icon('heroicon-o-variable')->schema([
                    Livewire::make(FormulaDePresupuesto::class, fn (Presupuesto $record): array => ['presupuestoId' => $record->id])->key('formula-polinomica'),
                ]),
                PestanaHistorial::make(HistorialDePresupuesto::class, fn (Presupuesto $record): int => $record->id),
            ]),
        ]);
    }

    /** «Borrador · Con APU · Soles · Proyecto PRY-2026-0012» (D4). */
    public static function subtitulo(Presupuesto $presupuesto): string
    {
        return implode(' · ', array_filter([
            $presupuesto->estado->nombre.($presupuesto->es_vigente ? ' (vigente)' : ''),
            $presupuesto->usa_apu ? 'Con análisis de precios' : 'Precio por partida',
            Moneda::query()->whereKey($presupuesto->moneda_id)->value('nombre'),
            $presupuesto->proyecto_id === null ? 'Sin proyecto' : 'Proyecto '.self::proyecto($presupuesto->proyecto_id),
        ]));
    }

    /** El código y nombre del proyecto, por C2 (nunca su modelo). */
    public static function proyecto(?int $proyectoId): ?string
    {
        if ($proyectoId === null) {
            return null;
        }

        try {
            $proyecto = app(ProyectosService::class)->resumen($proyectoId);

            return "{$proyecto->codigo} · {$proyecto->nombre}";
        } catch (ProyectoRechazado) {
            return "#{$proyectoId}";
        }
    }

    /** Las monedas activas y, si la del presupuesto se desactivó, también esa. */
    public static function selectorMoneda(): Field
    {
        return Select::make('moneda_id')->options(function (mixed $state): array {
            $consulta = Moneda::query()->where('es_activa', true);

            if (is_numeric($state)) {
                $consulta->orWhere('id', (int) $state);
            }

            return $consulta->orderBy('codigo')->get()->mapWithKeys(fn (Moneda $m): array => [$m->id => "{$m->codigo} · {$m->nombre}"])->all();
        });
    }

    /**
     * Lo que «Nuevo presupuesto» trae escrito: soles, la tasa de IGV vigente (`tributario.igv_porcentaje`, para que un
     * cambio de tasa llegue sin tocar código) y con análisis de precios. Los porcentajes de gastos generales y utilidad
     * los escribe quien presupuesta.
     *
     * @return array<string, mixed>
     */
    public static function valoresIniciales(): array
    {
        return ['moneda_id' => Moneda::query()->where('codigo', 'PEN')->value('id'), 'igv_pct' => app(ParametrosService::class)->decimal('tributario.igv_porcentaje'), 'usa_apu' => true];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListarPresupuestos::route('/'),
            'view' => VerPresupuesto::route('/{record}'),
        ];
    }
}
