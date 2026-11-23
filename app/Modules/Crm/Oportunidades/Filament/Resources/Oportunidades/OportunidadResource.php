<?php

declare(strict_types=1);

namespace App\Modules\Crm\Oportunidades\Filament\Resources\Oportunidades;

use App\Modules\Crm\Cuentas\Services\CuentasService;
use App\Modules\Crm\Oportunidades\Enums\EtapaAbierta;
use App\Modules\Crm\Oportunidades\Estados\DefinicionOportunidad;
use App\Modules\Crm\Oportunidades\Estados\SobreLoPropio;
use App\Modules\Crm\Oportunidades\Filament\PanelesOportunidad;
use App\Modules\Crm\Oportunidades\Filament\Resources\Oportunidades\Pages\LineaDeTiempoOportunidades;
use App\Modules\Crm\Oportunidades\Filament\Resources\Oportunidades\Pages\TableroOportunidades;
use App\Modules\Crm\Oportunidades\Filament\Resources\Oportunidades\Pages\TarjetasOportunidades;
use App\Modules\Crm\Oportunidades\Filament\Resources\Oportunidades\Pages\VerOportunidad;
use App\Modules\Crm\Oportunidades\Filament\ResumenDeLaFicha;
use App\Modules\Crm\Oportunidades\Models\Oportunidad;
use App\Modules\Crm\Oportunidades\OportunidadesServiceProvider;
use App\Modules\Crm\Oportunidades\Pestanas\PestanaOportunidad;
use App\Modules\Crm\Oportunidades\Pestanas\RegistroPestanasOportunidad;
use App\Modules\Crm\Oportunidades\Services\PortadaOportunidades;
use App\Modules\Nucleo\Enums\Catalogo;
use App\Modules\Nucleo\Filament\EtiquetasEnEspanol;
use App\Modules\Nucleo\Filament\PestanaHistorial;
use App\Modules\Nucleo\Models\Estado;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Services\CatalogosService;
use App\Modules\Nucleo\Services\SeguridadService;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * CRM-02 · «Oportunidades» (plan 2.21, D11; vistas de la 2.21c): Tablero, Línea de tiempo, Lista y Faja 3D, la ficha con
 * sus pestañas y los paneles Nueva, Avanzar y Cerrar. Solo presentación: todo cambio pasa por las acciones registradas `crm.oportunidades.*`. La cuenta se
 * lee con CuentasService, nunca con el modelo de Cuentas (regla 2).
 */
final class OportunidadResource extends Resource
{
    use EtiquetasEnEspanol;

    protected static ?string $model = Oportunidad::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-presentation-chart-line';

    protected static ?string $navigationLabel = 'Oportunidades';

    protected static ?string $modelLabel = 'oportunidad';

    protected static ?string $pluralModelLabel = 'oportunidades';

    protected static ?string $slug = 'oportunidades';

    public static function canViewAny(): bool
    {
        return self::usuarioPuede(OportunidadesServiceProvider::PERMISO_VER);
    }

    public static function canView(Model $record): bool
    {
        return self::usuarioPuede(OportunidadesServiceProvider::PERMISO_VER);
    }

    // El alta es un panel y los cambios van por acciones registradas.
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

    /** D10: editar todas, o las propias si es su ejecutivo. */
    public static function puedeEditar(Oportunidad $oportunidad): bool
    {
        $usuario = self::usuario();

        return $usuario !== null && app(SeguridadService::class)->puedeSobrePropio($usuario, SobreLoPropio::EDITAR, SobreLoPropio::EDITAR_PROPIAS, $oportunidad->ejecutivo_user_id);
    }

    /**
     * «Razón social · RUC» de la cuenta, por CuentasService. Se recuerda solo durante la petición (en sus atributos), no
     * en el proceso: en la cola o entre pruebas no queda una etiqueta vieja.
     */
    public static function cuenta(int $cuentaId): string
    {
        return self::cuentas([$cuentaId])[$cuentaId];
    }

    /**
     * Las etiquetas de varias cuentas en una sola lectura: el tablero las pide todas antes de dibujar.
     *
     * @param  list<int>  $cuentaIds
     * @return array<int, string>
     */
    public static function cuentas(array $cuentaIds): array
    {
        $atributos = request()->attributes;
        /** @var array<int, string> $leidas */
        $leidas = $atributos->get('oportunidades.cuentas', []);
        $faltan = array_values(array_diff($cuentaIds, array_keys($leidas)));

        if ($faltan !== []) {
            foreach (app(CuentasService::class)->resumenesDe($faltan) as $id => $resumen) {
                $leidas[$id] = $resumen->etiquetaParaSelector();
            }
            $atributos->set('oportunidades.cuentas', $leidas);
        }

        return array_intersect_key($leidas, array_flip($cuentaIds));
    }

    /** «Calificada · 25 %» en una abierta; el nombre de la etapa en una cerrada. */
    public static function etapa(Oportunidad $oportunidad): string
    {
        $etapa = EtapaAbierta::tryFrom($oportunidad->estado->codigo);

        return $etapa === null ? $oportunidad->estado->nombre : "{$oportunidad->estado->nombre} · ".app(PortadaOportunidades::class)->probabilidad($etapa).' %';
    }

    /** Monto × probabilidad de su etapa, en su moneda; nulo en una cerrada (D2). */
    public static function ponderado(Oportunidad $oportunidad): ?string
    {
        $etapa = EtapaAbierta::tryFrom($oportunidad->estado->codigo);

        return $etapa === null ? null : PanelesOportunidad::dinero(
            bcdiv(bcmul((string) $oportunidad->monto_estimado, (string) app(PortadaOportunidades::class)->probabilidad($etapa), 4), '100', 2),
            $oportunidad->moneda->simbolo,
        );
    }

    public static function monto(Oportunidad $oportunidad): string
    {
        return PanelesOportunidad::dinero((string) $oportunidad->monto_estimado, $oportunidad->moneda->simbolo);
    }

    public static function table(Table $table): Table
    {
        $portada = app(PortadaOportunidades::class);

        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['estado', 'moneda', 'ejecutivo:id,name,es_activo']))
            ->defaultSort('oportunidades.id', 'desc')
            ->columns([
                TextColumn::make('numero')->label('Oportunidad')->fontFamily('mono')->wrap()
                    ->description(fn (Oportunidad $record): string => $record->nombre)
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->scopes(['buscarTexto' => [trim($search), ['numero', 'nombre']]])),
                TextColumn::make('cuenta_id')->label('Cuenta')->wrap()
                    ->formatStateUsing(fn (Oportunidad $record): string => self::cuenta($record->cuenta_id)),
                TextColumn::make('monto_estimado')->label('Monto')->alignEnd()
                    ->formatStateUsing(fn (Oportunidad $record): string => self::monto($record))
                    ->description(fn (Oportunidad $record): string => $record->moneda->codigo),
                // D11.2: las anuladas se ven marcadas con su etapa (el color va con el nombre).
                TextColumn::make('estado_id')->label('Etapa')->badge()
                    ->formatStateUsing(fn (Oportunidad $record): string => self::etapa($record))
                    ->color(fn (Oportunidad $record): string => $record->estado->colorEnPantalla()),
                TextColumn::make('fecha_cierre_estimada')->label('Cierre estimado')->date('d/m/Y'),
                TextColumn::make('en_etapa_desde')->label('Días en la etapa')->alignEnd()
                    ->formatStateUsing(fn (Oportunidad $record): string => (string) $portada->diasEnEtapa($record))
                    // Ámbar si pasa el máximo de su etapa (D2); el texto lo dice también.
                    ->color(fn (Oportunidad $record): ?string => $portada->pasaElMaximo($record, $record->estado->codigo) ? 'warning' : null)
                    ->description(fn (Oportunidad $record): ?string => $portada->pasaElMaximo($record, $record->estado->codigo) ? 'Estancada' : null),
                TextColumn::make('ejecutivo.name')->label('Ejecutivo')
                    ->description(fn (Oportunidad $record): ?string => $record->ejecutivo->es_activo ? null : 'Inactivo'),
            ])
            ->filters([
                SelectFilter::make('revisar')->label('Revisar')
                    ->options(['estancadas' => 'Estancadas', 'ejecutivo-inactivo' => 'Con ejecutivo inactivo'])
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null) ? $portada->enAviso($query, (string) $data['value']) : $query),
                // D18 (2.21c): varias etapas a la vez; las del documento, con las cerradas y la anulada (la Lista las muestra).
                SelectFilter::make('estado_id')->label('Etapa')->multiple()
                    ->options(fn (): array => Estado::query()->where('documento_type', (new DefinicionOportunidad)->tipo())->orderBy('orden')->pluck('nombre', 'id')->all()),
                SelectFilter::make('ejecutivo_user_id')->label('Ejecutivo')->relationship('ejecutivo', 'name')->searchable(),
                SelectFilter::make('linea_solucion_id')->label('Línea de solución')
                    ->options(fn (): array => app(CatalogosService::class)->paraFiltro(Catalogo::LineasSolucion)),
                SelectFilter::make('origen_comercial_id')->label('Origen')
                    ->options(fn (): array => app(CatalogosService::class)->paraFiltro(Catalogo::OrigenesComerciales)),
                Filter::make('cierre')->label('Cierre estimado')
                    ->schema([
                        DatePicker::make('desde')->label('Cierre estimado desde'),
                        DatePicker::make('hasta')->label('Cierre estimado hasta'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['desde'] ?? null, fn (Builder $q, mixed $desde): Builder => $q->whereDate('oportunidades.fecha_cierre_estimada', '>=', (string) $desde))
                        ->when($data['hasta'] ?? null, fn (Builder $q, mixed $hasta): Builder => $q->whereDate('oportunidades.fecha_cierre_estimada', '<=', (string) $hasta))),
            ])
            ->recordUrl(fn (Oportunidad $record): string => VerOportunidad::getUrl(['record' => $record]))
            ->emptyStateHeading('Sin oportunidades');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            // Plan 2.21d, D10: la cabecera con la foto de fondo, el monto y la línea de etapas.
            View::make('oportunidades::ficha.cabecera')->columnSpanFull()
                ->viewData(fn (Oportunidad $record): array => ['c' => app(ResumenDeLaFicha::class)->cabecera($record)]),
            Tabs::make('ficha')->columnSpanFull()->tabs([
                // D10: el Resumen va primero: recorrido, compuerta, negociación, línea de tiempo y atención.
                Tab::make('Resumen')->icon('heroicon-o-window')->schema([
                    View::make('oportunidades::ficha.resumen')->viewData(fn (Oportunidad $record): array => [
                        'r' => app(ResumenDeLaFicha::class)->resumen($record, self::usuario() ?? throw new AuthorizationException),
                        'oportunidadId' => $record->id,
                    ]),
                ]),
                Tab::make('Datos')->icon('heroicon-o-information-circle')->schema([
                    // D9: lo que exige calificar.
                    Section::make('Calificación')->columns(3)->schema([
                        IconEntry::make('necesidad_confirmada')->label('Necesidad confirmada')->boolean(),
                        IconEntry::make('presupuesto_confirmado')->label('Presupuesto confirmado')->boolean(),
                        TextEntry::make('decisor')->label('Decisor')->placeholder('Sin identificar')
                            ->state(fn (Oportunidad $record): ?string => $record->decisor?->nombreCompleto()),
                    ]),
                    Section::make('Datos')->columns(3)->schema([
                        TextEntry::make('cuenta_id')->label('Cuenta')->columnSpan(2)
                            ->formatStateUsing(fn (Oportunidad $record): string => self::cuenta($record->cuenta_id))
                            ->url(fn (Oportunidad $record): string => url('/personas/'.app(CuentasService::class)->resumen($record->cuenta_id)->partyId)),
                        TextEntry::make('ejecutivo.name')->label('Ejecutivo'),
                        TextEntry::make('monto_estimado')->label('Monto estimado')
                            ->formatStateUsing(fn (Oportunidad $record): string => self::monto($record)),
                        TextEntry::make('fecha_cierre_estimada')->label('Cierre estimado')->date('d/m/Y'),
                        TextEntry::make('fecha_cierre_real')->label('Cierre real')->date('d/m/Y')->placeholder('—'),
                        TextEntry::make('linea_solucion_id')->label('Línea de solución')
                            ->formatStateUsing(fn (Oportunidad $record): string => $record->valorLegibleBitacora('linea_solucion_id', $record->linea_solucion_id)),
                        TextEntry::make('origen_comercial_id')->label('Origen')
                            // Plan 2.0, D5: con «Otro», cuál es.
                            ->formatStateUsing(fn (Oportunidad $record): string => $record->valorLegibleBitacora('origen_comercial_id', $record->origen_comercial_id).($record->origen_detalle === null ? '' : ": {$record->origen_detalle}")),
                        TextEntry::make('ganador_conocido')->label('¿Lo ganó otra empresa?')
                            ->visible(fn (Oportunidad $record): bool => $record->ganador_conocido !== null)
                            ->formatStateUsing(fn (Oportunidad $record): ?string => $record->ganador_conocido?->etiqueta()),
                        TextEntry::make('descripcion')->label('Descripción')->placeholder('—')->columnSpanFull(),
                    ]),
                    // D2: monto × probabilidad de la etapa (parámetro); nadie la edita en la oportunidad.
                    Section::make('Ponderado')->columns(3)
                        ->visible(fn (Oportunidad $record): bool => EtapaAbierta::tryFrom($record->estado->codigo) !== null)
                        ->schema([
                            TextEntry::make('etapa')->label('Etapa')->state(fn (Oportunidad $record): string => self::etapa($record)),
                            TextEntry::make('ponderado')->label('Ponderado')->state(fn (Oportunidad $record): ?string => self::ponderado($record))
                                ->helperText('El porcentaje es el de su etapa; lo fija Administración en los parámetros.'),
                        ]),
                ]),
                Tab::make('Competidores')->icon('heroicon-o-trophy')->schema([
                    Livewire::make(CompetidoresDeOportunidad::class, fn (Oportunidad $record): array => ['oportunidadId' => $record->id])->key('competidores'),
                ]),
                Tab::make('Historial de etapas')->icon('heroicon-o-arrow-trending-up')->schema([
                    Livewire::make(EtapasDeOportunidad::class, fn (Oportunidad $record): array => ['oportunidadId' => $record->id])->key('etapas'),
                ]),
                // D12: las que suman otros módulos (Cotizaciones, Presupuesto, Actividades…), cada una con su permiso.
                ...array_map(fn (PestanaOportunidad $pestana): Tab => Tab::make($pestana->titulo)->icon($pestana->icono)
                    ->visible(fn (Oportunidad $record): bool => app(RegistroPestanasOportunidad::class)->puedeVer(self::usuario(), $pestana->permiso)
                        && ($pestana->aplicaA === null || ($pestana->aplicaA)($record->id)))
                    ->schema([
                        Livewire::make($pestana->nombreLivewire(), fn (Oportunidad $record): array => ['oportunidadId' => $record->id])->key('oportunidad-'.$pestana->clave),
                    ]), app(RegistroPestanasOportunidad::class)->pestanasPara(self::usuario())),
                PestanaHistorial::make(HistorialDeOportunidad::class, fn (Oportunidad $record): int => $record->id),
                // Observación 7.3: `?pestana=licitacion::tab` abre la pestaña (los avisos de Mien llevan ahí).
            ])->persistTabInQueryString('pestana'),
        ]);
    }

    public static function getPages(): array
    {
        return [
            // MV-057: tres vistas: Mis oportunidades (la de entrada), Embudo de oportunidades y Línea de tiempo.
            'index' => TarjetasOportunidades::route('/'),
            'embudo' => TableroOportunidades::route('/embudo'),
            'linea-de-tiempo' => LineaDeTiempoOportunidades::route('/linea-de-tiempo'),
            'view' => VerOportunidad::route('/{record}'),
        ];
    }
}
