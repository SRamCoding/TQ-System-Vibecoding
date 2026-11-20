<?php

declare(strict_types=1);

namespace App\Modules\Crm\Cuentas\Filament\Resources\Cuentas;

use App\Modules\Crm\Cuentas\CuentasServiceProvider;
use App\Modules\Crm\Cuentas\Filament\Resources\Cuentas\Pages\ListarCuentas;
use App\Modules\Crm\Cuentas\Filament\Resources\Cuentas\Pages\NuevaCuenta;
use App\Modules\Crm\Cuentas\Filament\Resources\Cuentas\Pages\VerCuenta;
use App\Modules\Crm\Cuentas\Models\Cuenta;
use App\Modules\Crm\Cuentas\Pestanas\PestanaCuenta;
use App\Modules\Crm\Cuentas\Pestanas\RegistroPestanasCuenta;
use App\Modules\Crm\Cuentas\Services\CuentasService;
use App\Modules\Crm\Cuentas\Services\PortadaCuentas;
use App\Modules\Nucleo\Filament\EtiquetasEnEspanol;
use App\Modules\Nucleo\Filament\PestanaHistorial;
use App\Modules\Nucleo\Filament\Resources\Parties\ContactosDeParty;
use App\Modules\Nucleo\Filament\Resources\Parties\Pages\VerParty;
use App\Modules\Nucleo\Models\Party;
use App\Modules\Nucleo\Models\Rubro;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Parties\ResumenSede;
use App\Modules\Nucleo\Services\PartiesService;
use App\Modules\Nucleo\Services\SeguridadService;
use App\Modules\Nucleo\Tiempo\Calendario;
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
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * CRM-01 · «Cuentas» (plan 2.20, D11): lista con portada, alta en dos pasos y ficha con Resumen, Contactos e
 * Historial. Solo presentación: todo cambio pasa por las acciones registradas `crm.cuentas.*` y CuentasService.
 */
final class CuentaResource extends Resource
{
    use EtiquetasEnEspanol;

    protected static ?string $model = Cuenta::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-building-storefront';

    protected static ?string $navigationLabel = 'Cuentas';

    protected static ?string $modelLabel = 'cuenta';

    protected static ?string $pluralModelLabel = 'cuentas';

    protected static ?string $slug = 'cuentas';

    public static function canViewAny(): bool
    {
        return self::usuarioPuede(CuentasServiceProvider::PERMISO_VER);
    }

    public static function canView(Model $record): bool
    {
        return self::usuarioPuede(CuentasServiceProvider::PERMISO_VER);
    }

    // El alta es la página de dos pasos y los cambios van por acciones registradas.
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

    /** D7.6: editar todas, o las propias si es su ejecutivo. */
    public static function puedeEditar(Cuenta $cuenta): bool
    {
        $usuario = self::usuario();

        return $usuario !== null && app(SeguridadService::class)->puedeSobrePropio($usuario, CuentasService::PERMISO_EDITAR, CuentasService::PERMISO_EDITAR_PROPIAS, $cuenta->ejecutivo_user_id);
    }

    public static function table(Table $table): Table
    {
        $publicas = fn (): Builder => Party::query()->select('id')->scopes(['conRolVigente' => [CuentasService::ROL_ENTIDAD, Calendario::hoy()]]);

        return $table
            // D3: pública o privada en la misma consulta, sin una llamada por fila.
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['party.rubro', 'ejecutivo:id,name,es_activo', 'nivelGobierno'])
                ->select('cuentas.*')
                ->selectSub(Party::query()->selectRaw('true')->scopes(['conRolVigente' => [CuentasService::ROL_ENTIDAD, Calendario::hoy()]])->whereColumn('parties.id', 'cuentas.party_id')->limit(1), 'es_publica_hoy'))
            ->defaultSort(fn (Builder $query): Builder => $query->orderBy(Party::query()->select('razon_social')->whereColumn('parties.id', 'cuentas.party_id')))
            ->columns([
                TextColumn::make('party.razon_social')->label('Razón social')->wrap()
                    ->description(fn (Cuenta $record): ?string => $record->party->nombre_comercial)
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereIn('cuentas.party_id', Party::query()->select('id')->scopes(['buscarNombreODocumento' => [$search]]))),
                TextColumn::make('party.numero_documento')->label('RUC')->fontFamily('mono'),
                TextColumn::make('tipo')->label('Tipo')->badge()
                    ->state(fn (Cuenta $record): string => self::tipo($record))
                    ->color(fn (Cuenta $record): string => str_starts_with(self::tipo($record), 'Pública') ? 'info' : 'gray'),
                TextColumn::make('party.rubro.codigo')->label('Rubro')->placeholder('—')
                    ->tooltip(fn (Cuenta $record): ?string => $record->party->rubro?->nombre),
                TextColumn::make('ejecutivo.name')->label('Ejecutivo')
                    ->description(fn (Cuenta $record): ?string => $record->ejecutivo->es_activo ? null : 'Inactivo'),
                TextColumn::make('cliente_desde')->label('Cliente desde')->date('d/m/Y')->placeholder('Prospecto'),
                TextColumn::make('estado')->label('Estado')->badge()->color('gray')
                    ->state(fn (Cuenta $record): ?string => app(CuentasService::class)->motivoNoUsableDe($record)?->etiqueta()),
            ])
            ->filters([
                SelectFilter::make('revisar')->label('Revisar')
                    ->options(['sin-actividad' => 'Sin actividad', 'ejecutivo-inactivo' => 'Con ejecutivo inactivo', 'ficha-inactiva' => 'Con su ficha inactiva', 'sin-rubro' => 'Sin rubro'])
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null) ? app(PortadaCuentas::class)->enAviso($query, (string) $data['value']) : $query),
                TernaryFilter::make('publica')->label('Pública o privada')->trueLabel('Públicas')->falseLabel('Privadas')
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereIn('cuentas.party_id', $publicas()),
                        false: fn (Builder $query): Builder => $query->whereNotIn('cuentas.party_id', $publicas()),
                    ),
                SelectFilter::make('nivel_gobierno_id')->label('Nivel de gobierno')->relationship('nivelGobierno', 'nombre'),
                SelectFilter::make('departamento')->label('Departamento')
                    ->options(fn (): array => Party::query()->join('ubigeos', 'ubigeos.id', '=', 'parties.ubigeo_id')
                        ->whereIn('parties.id', Cuenta::query()->select('party_id'))->distinct()->orderBy('ubigeos.departamento')
                        ->pluck('ubigeos.departamento', 'ubigeos.departamento')->all())
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                        ? $query->whereIn('cuentas.party_id', Party::query()->select('parties.id')->join('ubigeos', 'ubigeos.id', '=', 'parties.ubigeo_id')->where('ubigeos.departamento', $data['value']))
                        : $query),
                SelectFilter::make('ejecutivo_user_id')->label('Ejecutivo')->relationship('ejecutivo', 'name')->searchable(),
                SelectFilter::make('rubro')->label('Rubro')
                    ->options(fn (): array => Rubro::query()->orderBy('codigo')->get()->mapWithKeys(fn (Rubro $r): array => [$r->id => "{$r->codigo} · {$r->nombre}"])->all())
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                        ? $query->whereIn('cuentas.party_id', Party::query()->select('id')->where('rubro_id', $data['value']))
                        : $query),
                SelectFilter::make('origen_comercial_id')->label('Origen')->relationship('origenComercial', 'nombre'),
            ])
            ->recordUrl(fn (Cuenta $record): string => VerCuenta::getUrl(['record' => $record]))
            ->emptyStateHeading('Sin cuentas');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Tabs::make('ficha')->columnSpanFull()->tabs([
                Tab::make('Resumen')->icon('heroicon-o-building-storefront')->schema([
                    Section::make('La cuenta')->columns(3)->schema([
                        TextEntry::make('ejecutivo.name')->label('Ejecutivo'),
                        TextEntry::make('tipo')->label('Tipo')->state(fn (Cuenta $record): string => self::tipo($record))
                            ->helperText('Pública si la ficha tiene el tipo «Entidad pública»: se cambia en su pestaña «Tipos».'),
                        TextEntry::make('cliente_desde')->label('Cliente desde')->date('d/m/Y')->placeholder('Prospecto'),
                        TextEntry::make('origenComercial.nombre')->label('Origen'),
                        TextEntry::make('notas')->label('Notas')->placeholder('—')->columnSpan(2),
                        TextEntry::make('motivo')->label('Inactiva por')->columnSpanFull()
                            ->visible(fn (Cuenta $record): bool => ! $record->es_activa)
                            ->state(fn (Cuenta $record): string => trim(($record->motivoInactiva->nombre ?? '').' '.($record->motivo_inactiva_detalle === null ? '' : "· {$record->motivo_inactiva_detalle}"))),
                    ]),
                    Section::make('La empresa')->columns(3)->schema([
                        TextEntry::make('party.razon_social')->label('Razón social')->columnSpan(2)
                            ->url(fn (Cuenta $record): ?string => self::usuarioPuede('parties.ver') ? VerParty::getUrl(['record' => $record->party_id]) : null),
                        TextEntry::make('party.numero_documento')->label('RUC')->fontFamily('mono')->copyable(),
                        TextEntry::make('party.nombre_comercial')->label('Nombre comercial')->placeholder('—'),
                        TextEntry::make('rubro')->label('Rubro (CIIU)')->placeholder('Sin rubro')
                            ->state(fn (Cuenta $record): ?string => $record->party->rubro === null ? null : "{$record->party->rubro->codigo} · {$record->party->rubro->nombre}"),
                        TextEntry::make('party.direccion_fiscal')->label('Dirección fiscal')->placeholder('—'),
                        TextEntry::make('sedes')->label('Sedes')->placeholder('Sin sedes')->listWithLineBreaks()->columnSpanFull()
                            ->state(fn (Cuenta $record): array => array_map(fn (ResumenSede $s): string => $s->etiqueta, app(PartiesService::class)->sedesActivas($record->party_id))),
                    ]),
                ]),
                // D11.3: el componente del núcleo, con su rotación, sus acciones y sus permisos.
                Tab::make('Contactos')->icon('heroicon-o-user-circle')
                    ->visible(fn (): bool => self::usuarioPuede('parties.ver'))
                    ->schema([
                        Livewire::make(ContactosDeParty::class, fn (Cuenta $record): array => ['partyId' => $record->party_id])->key('contactos-de-cuenta'),
                    ]),
                // D12.4: las que suman otros módulos (Oportunidades, Cotizaciones…), cada una con su permiso.
                ...array_map(fn (PestanaCuenta $pestana): Tab => Tab::make($pestana->titulo)->icon($pestana->icono)
                    ->visible(fn (): bool => app(RegistroPestanasCuenta::class)->puedeVer(self::usuario(), $pestana->permiso))
                    ->schema([
                        Livewire::make($pestana->nombreLivewire(), fn (Cuenta $record): array => ['cuentaId' => $record->id])->key('cuenta-'.$pestana->clave),
                    ]), app(RegistroPestanasCuenta::class)->pestanasPara(self::usuario())),
                PestanaHistorial::make(HistorialDeCuenta::class, fn (Cuenta $record): int => $record->id),
            ]),
        ]);
    }

    /** «Pública · Gobierno local» o «Privada» (D3 y D4), con el tipo vigente hoy; en la lista, ya viene en la consulta. */
    public static function tipo(Cuenta $cuenta): string
    {
        $calculado = $cuenta->getAttribute('es_publica_hoy');
        $publica = $calculado === null && ! array_key_exists('es_publica_hoy', $cuenta->getAttributes())
            ? app(PartiesService::class)->tieneRol($cuenta->party_id, CuentasService::ROL_ENTIDAD)
            : (bool) $calculado;

        return $publica ? 'Pública'.($cuenta->nivelGobierno === null ? '' : ' · '.$cuenta->nivelGobierno->nombre) : 'Privada';
    }

    public static function getPages(): array
    {
        return [
            'index' => ListarCuentas::route('/'),
            'nueva' => NuevaCuenta::route('/nueva'),
            'view' => VerCuenta::route('/{record}'),
        ];
    }
}
