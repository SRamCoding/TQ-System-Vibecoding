<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Filament\Resources\Parties;

use App\Modules\Nucleo\Enums\TipoPersona;
use App\Modules\Nucleo\Filament\Adjuntos\ArchivosDeRegistro;
use App\Modules\Nucleo\Filament\EtiquetasEnEspanol;
use App\Modules\Nucleo\Filament\PestanaHistorial;
use App\Modules\Nucleo\Filament\Resources\Parties\Pages\ListarParties;
use App\Modules\Nucleo\Filament\Resources\Parties\Pages\NuevaParty;
use App\Modules\Nucleo\Filament\Resources\Parties\Pages\VerParty;
use App\Modules\Nucleo\Historial\DescribibleEnBitacora;
use App\Modules\Nucleo\Models\Party;
use App\Modules\Nucleo\Models\PartyContacto;
use App\Modules\Nucleo\Models\PartyCuentaBancaria;
use App\Modules\Nucleo\Models\PartyRedSocial;
use App\Modules\Nucleo\Models\PartyRol;
use App\Modules\Nucleo\Models\PartySede;
use App\Modules\Nucleo\Models\RolParty;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Parties\PestanaFicha;
use App\Modules\Nucleo\Parties\RegistroPestanasFicha;
use App\Modules\Nucleo\Parties\ResumenContacto;
use App\Modules\Nucleo\Seguridad\GrupoSensible;
use App\Modules\Nucleo\Services\CalidadPartiesService;
use App\Modules\Nucleo\Services\HistorialCambiosService;
use App\Modules\Nucleo\Services\PartiesService;
use App\Modules\Nucleo\Services\RedesSocialesService;
use App\Modules\Nucleo\Tiempo\Calendario;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * M1 · «Personas y empresas» (plan 1.3, D12): lista, alta en dos pasos y ficha con Datos, Roles e Historial.
 * Solo presentación: todo cambio pasa por las acciones registradas `nucleo.parties.*` y PartiesService.
 */
final class PartyResource extends Resource
{
    use EtiquetasEnEspanol;

    protected static ?string $model = Party::class;

    protected static string|BackedEnum|null $navigationIcon = 'tq-apreton-manos';

    protected static ?string $navigationLabel = 'Personas y empresas';

    protected static ?string $modelLabel = 'persona o empresa';

    protected static ?string $pluralModelLabel = 'personas y empresas';

    protected static ?string $slug = 'personas';

    protected static ?string $recordTitleAttribute = 'razon_social';

    public static function canViewAny(): bool
    {
        return self::usuarioPuede('parties.ver');
    }

    public static function canView(Model $record): bool
    {
        return self::usuarioPuede('parties.ver');
    }

    /*
     * El alta tiene su propia página de dos pasos (NuevaParty) y los cambios van por acciones registradas:
     * los formularios de Filament para crear, editar y borrar quedan cerrados.
     */
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

    private static function usuario(): ?User
    {
        $usuario = Filament::auth()->user();

        return $usuario instanceof User ? $usuario : null;
    }

    public static function usuarioPuede(string $permiso): bool
    {
        $usuario = Filament::auth()->user();

        return $usuario instanceof User && $usuario->can($permiso);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['tipoDocumento', 'rolesVigentes.rol']))
            ->defaultSort('razon_social')
            ->columns([
                TextColumn::make('tipo')->label('Tipo')->badge()
                    ->formatStateUsing(fn (TipoPersona $state): string => $state === TipoPersona::Natural ? 'Natural' : 'Jurídica')
                    ->color(fn (TipoPersona $state): string => $state === TipoPersona::Natural ? 'info' : 'gray'),
                TextColumn::make('razon_social')->label('Razón social')->sortable()->wrap()
                    ->description(fn (Party $record): ?string => $record->nombre_comercial)
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->scopes(['buscarNombreODocumento' => [$search]])),
                TextColumn::make('numero_documento')->label('Documento')->fontFamily('mono')
                    ->formatStateUsing(fn (Party $record): string => "{$record->tipoDocumento->nombre} {$record->numero_documento}"),
                TextColumn::make('roles_vigentes')->label('Tipos')->badge()
                    ->state(fn (Party $record): array => self::rolesVigentes($record)),
                IconColumn::make('es_activo')->label('Activa')->boolean(),
            ])
            ->filters([
                SelectFilter::make('rol')->label('Tipo')
                    ->options(fn (): array => RolParty::query()->where('es_activo', true)->orderBy('nombre')->pluck('nombre', 'codigo')->all())
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                        ? $query->scopes(['conRolVigente' => [(string) $data['value'], Calendario::hoy()]])
                        : $query),
                SelectFilter::make('tipo')->label('Tipo de persona')
                    ->options([TipoPersona::Natural->value => 'Natural', TipoPersona::Juridica->value => 'Jurídica']),
                // Plan 1.4m: los avisos de la portada lo ponen con «Ver cuáles».
                SelectFilter::make('calidad')->label('Revisar')
                    ->options(['incompletas' => 'Fichas incompletas', 'duplicados' => 'Posibles duplicados'])
                    ->query(function (Builder $query, array $data): Builder {
                        $calidad = app(CalidadPartiesService::class);

                        return match ($data['value'] ?? null) {
                            'incompletas' => tap($query, fn (Builder $q) => $calidad->soloIncompletas($q, self::usuarioPuede(GrupoSensible::DatosBancarios->permiso()))),
                            'duplicados' => $query->whereKey($calidad->idsPosiblesDuplicados()),
                            default => $query,
                        };
                    }),
                TernaryFilter::make('es_activo')->label('Activas')->default(true)
                    ->trueLabel('Solo activas')->falseLabel('Solo inactivas')->placeholder('Todas'),
            ])
            ->recordUrl(fn (Party $record): string => VerParty::getUrl(['record' => $record]))
            ->emptyStateHeading('Sin personas ni empresas');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Tabs::make('ficha')->columnSpanFull()->tabs([
                Tab::make('Datos')->icon('heroicon-o-identification')->schema([
                    Section::make('Identidad')->columns(3)->schema([
                        TextEntry::make('tipo')->label('Tipo de persona')->formatStateUsing(fn (TipoPersona $state): string => $state->etiqueta()),
                        TextEntry::make('tipoDocumento.nombre')->label('Tipo de documento'),
                        TextEntry::make('numero_documento')->label('Número')->fontFamily('mono')->copyable(),
                        TextEntry::make('razon_social')->label('Razón social')->columnSpan(2),
                        TextEntry::make('nombre_comercial')->label('Nombre comercial')->placeholder('—'),
                        // Plan 2.20a, N4: la sección CIIU; se cambia con «Rubro», arriba.
                        TextEntry::make('rubro_id')->label('Rubro (CIIU)')->placeholder('—')
                            ->formatStateUsing(fn (Party $record): string => $record->rubro === null ? '—' : "{$record->rubro->codigo} · {$record->rubro->nombre}"),
                        TextEntry::make('nombres')->label('Nombres')->visible(fn (Party $record): bool => $record->tipo === TipoPersona::Natural),
                        TextEntry::make('apellidos')->label('Apellidos')->visible(fn (Party $record): bool => $record->tipo === TipoPersona::Natural),
                    ]),
                    Section::make('Contacto')->columns(3)->schema([
                        TextEntry::make('direccion_fiscal')->label('Dirección fiscal')->placeholder('—')->columnSpan(2),
                        TextEntry::make('ubigeo_id')->label('Ubigeo')->placeholder('—')
                            ->formatStateUsing(fn (Party $record): string => $record->ubigeo?->etiqueta() ?? '—'),
                        TextEntry::make('telefono')->label('Teléfono')->placeholder('—'),
                        TextEntry::make('email')->label('Correo')->placeholder('—'),
                        // Plan 1.3b, D2: si hay contactos vigentes y ninguno es el principal, se avisa.
                        TextEntry::make('contacto_principal')->label('Contacto principal')
                            ->state(fn (Party $record): string => self::contactoPrincipal($record))
                            ->color(fn (Party $record): ?string => app(PartiesService::class)->faltaContactoPrincipal($record->id) ? 'warning' : null),
                    ]),
                    Grid::make(2)->schema([
                        Section::make('Datos tributarios')->columns(3)->schema([
                            IconEntry::make('es_agente_retencion')->label('Agente de retención')->boolean(),
                            IconEntry::make('es_agente_percepcion')->label('Agente de percepción')->boolean(),
                            IconEntry::make('es_buen_contribuyente')->label('Buen contribuyente')->boolean(),
                        ]),
                        // Los llenará el padrón de SUNAT cuando se integre: no se editan en pantalla (D7).
                        Section::make('Según SUNAT')->columns(2)->schema([
                            TextEntry::make('estado_sunat')->label('Estado')->placeholder('Sin consultar'),
                            TextEntry::make('condicion_sunat')->label('Condición')->placeholder('Sin consultar'),
                        ]),
                    ]),
                ]),
                // «Tipos», como el catálogo «Tipos de persona y empresa» (plan 1.4j): no son los roles de seguridad.
                Tab::make('Tipos')->icon('heroicon-o-tag')->schema([
                    Livewire::make(RolesDeParty::class, fn (Party $record): array => ['partyId' => $record->id])->key('roles-de-party'),
                ]),
                Tab::make('Contactos')->icon('heroicon-o-user-circle')->schema([
                    Livewire::make(ContactosDeParty::class, fn (Party $record): array => ['partyId' => $record->id])->key('contactos-de-party'),
                ]),
                Tab::make('Sedes')->icon('heroicon-o-map-pin')->schema([
                    Livewire::make(SedesDeParty::class, fn (Party $record): array => ['partyId' => $record->id])->key('sedes-de-party'),
                ]),
                // Plan 1.4o, D2: sus redes y las de sus contactos.
                Tab::make('Redes')->icon('tq-redes-sociales')->schema([
                    Livewire::make(RedesDeParty::class, fn (Party $record): array => ['partyId' => $record->id])->key('redes-de-party'),
                ]),
                // Solo con su permiso (D5): el componente lo vuelve a revisar en el servidor.
                Tab::make('Cuentas bancarias')->icon('heroicon-o-building-library')
                    ->visible(fn (): bool => self::usuarioPuede(GrupoSensible::DatosBancarios->permiso()))
                    ->schema([
                        Livewire::make(CuentasBancariasDeParty::class, fn (Party $record): array => ['partyId' => $record->id])->key('cuentas-bancarias-de-party'),
                    ]),
                // Plan 1.8, D9: sus archivos (ficha RUC, contratos, certificados).
                Tab::make('Archivos')->icon('heroicon-o-folder-open')->schema([
                    Livewire::make(ArchivosDeRegistro::class, fn (Party $record): array => ['adjuntableType' => $record->getMorphClass(), 'adjuntableId' => $record->id])->key('archivos-de-party'),
                ]),
                // Plan 1.4m, D5: las que suman otros módulos (CRM, compras…), cada una con su permiso y, si lo declara,
                // solo en las fichas de sus tipos de documento (plan 2.20a, N6).
                ...array_map(fn (PestanaFicha $pestana): Tab => Tab::make($pestana->titulo)->icon($pestana->icono)
                    ->visible(fn (Party $record): bool => app(RegistroPestanasFicha::class)->puedeVer(self::usuario(), $pestana)
                        && $pestana->aplicaA($record->tipoDocumento->codigo))
                    ->schema([
                        Livewire::make($pestana->nombreLivewire(), fn (Party $record): array => ['partyId' => $record->id])->key('ficha-'.$pestana->clave),
                    ]), app(RegistroPestanasFicha::class)->todas()),
                PestanaHistorial::make(HistorialDeParty::class, fn (Party $record): int => $record->id),
            ]),
        ]);
    }

    /**
     * Nombres de los roles vigentes hoy de la party (`Party::rolesVigentes`).
     *
     * @return list<string>
     */
    public static function rolesVigentes(Party $party): array
    {
        return $party->rolesVigentes
            ->map(fn (PartyRol $p): string => $p->rol->nombre)
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    /**
     * Lo que muestra la pestaña Historial: la party y sus partes, también las ya borradas (su bitácora sigue en la
     * ficha). Las cuentas bancarias, solo para quien tiene `campos.ver-datos_bancarios`: si no, ni se leen (plan
     * 1.3b, D6), así sus números nunca llegan a la página.
     *
     * @return list<Model&DescribibleEnBitacora>
     */
    public static function modelosDelHistorial(Party $party): array
    {
        $historial = app(HistorialCambiosService::class);
        $partes = [
            PartyRol::class => $party->roles()->with('rol')->get()->all(),
            PartyContacto::class => $party->contactos()->get()->all(),
            PartySede::class => $party->sedes()->get()->all(),
        ];

        // Plan 1.4o, D3: las redes de una persona del equipo, solo para ella (alguna puede ser privada; la bitácora de
        // una borrada no dice si lo era).
        if (! app(RedesSocialesService::class)->esDelEquipo($party->id) || self::usuario()?->party_id === $party->id) {
            $partes[PartyRedSocial::class] = PartyRedSocial::query()->where('party_id', $party->id)->get()->all();
        }

        // Sin el permiso, las cuentas ni se leen (plan 1.3b, D6). Con él, CamposVisibles sigue filtrando número y CCI
        // campo por campo (plan 1.4, D6): una segunda barrera si algún día se quita esta.
        if (self::usuarioPuede(GrupoSensible::DatosBancarios->permiso())) {
            $partes[PartyCuentaBancaria::class] = $party->cuentasBancarias()->with(['banco', 'moneda'])->get()->all();
        }

        $modelos = [$party];

        foreach ($partes as $clase => $vivas) {
            array_push($modelos, ...$vivas, ...$historial->borradosDe($clase, 'party_id', $party->id));
        }

        return $modelos;
    }

    /** «Luis Fernández · Jefe de Logística», o qué falta. */
    private static function contactoPrincipal(Party $party): string
    {
        $principal = collect(app(PartiesService::class)->contactosVigentes($party->id))->first(fn (ResumenContacto $c): bool => $c->esPrincipal);

        return match (true) {
            $principal !== null => $principal->etiqueta,
            app(PartiesService::class)->faltaContactoPrincipal($party->id) => 'Sin contacto principal: márcalo en Contactos',
            default => '—',
        };
    }

    public static function getPages(): array
    {
        return [
            'index' => ListarParties::route('/'),
            'registrar' => NuevaParty::route('/registrar'),
            'view' => VerParty::route('/{record}'),
        ];
    }
}
