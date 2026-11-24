<?php

declare(strict_types=1);

namespace App\Modules\Crm\Campanias\Filament\Resources\Leads;

use App\Modules\Crm\Campanias\Filament\Resources\Leads\Pages\ListarLeads;
use App\Modules\Crm\Campanias\Models\Campania;
use App\Modules\Crm\Campanias\Models\Lead;
use App\Modules\Crm\Campanias\Services\LeadsService;
use App\Modules\Crm\Cuentas\Services\CuentasService;
use App\Modules\Crm\Oportunidades\Filament\Resources\Oportunidades\Pages\VerOportunidad;
use App\Modules\Nucleo\Filament\EtiquetasEnEspanol;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Tiempo\Calendario;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/** MVP rápido (ficha t1-campanias): los leads, con contactar, convertir (revisando coincidencias) y descartar. */
final class LeadResource extends Resource
{
    use EtiquetasEnEspanol;

    protected static ?string $model = Lead::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-inbox-arrow-down';

    protected static ?string $navigationLabel = 'Leads';

    protected static ?string $modelLabel = 'lead';

    protected static ?string $pluralModelLabel = 'leads';

    protected static ?string $slug = 'leads';

    public static function canViewAny(): bool
    {
        return self::puede(LeadsService::PERMISO_VER);
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

    public static function sinAtender(): int
    {
        return Lead::query()->where('estado', 'nuevo')->where('created_at', '<', Calendario::ahora()->subDays(LeadsService::DIAS_SIN_ATENDER))->count();
    }

    public static function table(Table $table): Table
    {
        $abierto = fn (Lead $record): bool => in_array($record->estado, ['nuevo', 'contactado'], true) && self::puede(LeadsService::PERMISO_GESTIONAR);
        $sinAtender = self::sinAtender();

        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['campania', 'asignado']))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('nombre_contacto')->label('Nombre')->searchable()->weight('bold')->description(fn (Lead $record): ?string => $record->cargo),
                TextColumn::make('organizacion')->label('Organización')->searchable()->wrap()->description(fn (Lead $record): ?string => $record->ruc === null ? null : "RUC {$record->ruc}"),
                TextColumn::make('origen')->label('Origen')
                    ->state(fn (Lead $record): string => (string) DB::table('origenes_comerciales')->where('id', $record->origen_comercial_id)->value('nombre'))
                    ->description(fn (Lead $record): ?string => $record->campania?->nombre),
                TextColumn::make('dias')->label('Hace')
                    ->state(fn (Lead $record): string => (int) $record->created_at->diffInDays(Calendario::ahora()).' días')
                    ->color(fn (Lead $record): ?string => $record->estado === 'nuevo' && $record->created_at->lt(Calendario::ahora()->subDays(LeadsService::DIAS_SIN_ATENDER)) ? 'danger' : null),
                TextColumn::make('estado')->label('Estado')->badge()->formatStateUsing(fn (string $state): string => Lead::ESTADOS[$state])->color(fn (string $state): string => Lead::COLORES[$state]),
                TextColumn::make('oportunidad')->label('Oportunidad')->placeholder('—')
                    ->state(fn (Lead $record): ?string => $record->oportunidad_id === null ? null : (string) DB::table('oportunidades')->where('id', $record->oportunidad_id)->value('numero'))
                    ->url(fn (Lead $record): ?string => $record->oportunidad_id === null ? null : VerOportunidad::getUrl(['record' => $record->oportunidad_id])),
            ])
            ->filters([
                SelectFilter::make('vista')->label('Ver')->default('abiertos')
                    ->options(['abiertos' => 'Por atender y contactados', 'sin_atender' => "Sin atender +3 días · {$sinAtender}", 'convertido' => 'Convertidos', 'descartado' => 'Descartados', 'todos' => 'Todos'])
                    ->query(fn (Builder $query, array $data): Builder => match ($data['value'] ?? 'abiertos') {
                        'sin_atender' => $query->where('estado', 'nuevo')->where('created_at', '<', Calendario::ahora()->subDays(LeadsService::DIAS_SIN_ATENDER)),
                        'convertido', 'descartado' => $query->where('estado', $data['value']),
                        'todos' => $query,
                        default => $query->whereIn('estado', ['nuevo', 'contactado']),
                    }),
                SelectFilter::make('campania_id')->label('Campaña')->options(fn (): array => Campania::query()->orderByDesc('desde')->pluck('nombre', 'id')->all()),
            ])
            ->recordActions([
                Action::make('contactar')->label('Contactado')->icon('heroicon-o-phone')->color('gray')
                    ->visible(fn (Lead $record): bool => $record->estado === 'nuevo' && self::puede(LeadsService::PERMISO_GESTIONAR))
                    ->action(function (Lead $record): void {
                        /** @var User $u */
                        $u = auth()->user();
                        app(LeadsService::class)->contactar($record->id, $u);
                    }),
                self::accionConvertir()->visible($abierto),
                self::accionDescartar()->visible($abierto),
            ]);
    }

    /**
     * «Convertir» (en Oportunidades, «Calificar»; observación 7.1): revisa coincidencias y crea la oportunidad en
     * Prospecto. El lead llega como registro de la acción (`->record()` desde otra pantalla).
     */
    public static function accionConvertir(): Action
    {
        return Action::make('convertir')->label('Convertir')->icon('heroicon-o-arrow-right-circle')->color('success')->button()->slideOver()
            ->modalHeading(fn (Lead $record): string => "Convertir lead · {$record->nombre_contacto}")
            ->fillForm(function (Lead $record): array {
                $c = app(LeadsService::class)->coincidencias($record);

                return ['destino' => $c === [] ? 'nueva' : "c:{$c[0]['cuenta_id']}", 'ruc' => $record->ruc, 'nombre' => $record->organizacion === null ? 'Oportunidad de '.$record->nombre_contacto : mb_substr("{$record->organizacion} · ".($record->campania?->nombre ?? 'lead'), 0, 200),
                    'monto' => '50000', 'cierre' => CarbonImmutable::parse(Calendario::hoy())->addDays(60)->toDateString()];
            })
            ->schema([
                Text::make(fn (Lead $record): string => ($n = count(app(LeadsService::class)->coincidencias($record))) === 0 ? 'No se encontraron coincidencias.' : "Se encontraron {$n} coincidencias antes de crear nada."),
                Radio::make('destino')->label('¿Con qué cuenta?')->required()->live()
                    ->options(fn (Lead $record): array => [
                        ...collect(app(LeadsService::class)->coincidencias($record))->mapWithKeys(fn (array $c): array => ["c:{$c['cuenta_id']}" => "{$c['etiqueta']} · {$c['por']}"])->all(),
                        'otra' => 'Otra cuenta existente…',
                        'nueva' => 'Crear una cuenta nueva de todas formas',
                    ]),
                Select::make('cuenta_id')->label('Cuenta')->searchable()->visible(fn (Get $get): bool => $get('destino') === 'otra')->required(fn (Get $get): bool => $get('destino') === 'otra')
                    ->getSearchResultsUsing(fn (string $search): array => collect(app(CuentasService::class)->buscar($search))->mapWithKeys(fn ($c): array => [$c->id => $c->etiqueta])->all()),
                TextInput::make('ruc')->label('RUC de la cuenta nueva')->maxLength(11)->visible(fn (Get $get): bool => $get('destino') === 'nueva')->required(fn (Get $get): bool => $get('destino') === 'nueva'),
                TextInput::make('nombre')->label('Oportunidad')->required()->maxLength(200),
                TextInput::make('monto')->label('Monto estimado (S/)')->numeric()->required()->minValue(1),
                Select::make('linea_solucion_id')->label('Línea de solución')->required()->options(fn (): array => DB::table('lineas_solucion')->orderBy('nombre')->pluck('nombre', 'id')->all()),
                DatePicker::make('cierre')->label('Cierre estimado')->required()->format('Y-m-d'),
            ])
            ->action(function (Lead $record, array $data, Action $action): void {
                $destino = (string) $data['destino'];
                $cuentaId = match (true) {
                    str_starts_with($destino, 'c:') => (int) substr($destino, 2),
                    $destino === 'otra' => (int) $data['cuenta_id'],
                    default => null,
                };

                try {
                    /** @var User $u */
                    $u = auth()->user();
                    $lead = app(LeadsService::class)->convertir($record->id, $cuentaId, $data['ruc'] ?? null, $data['nombre'], (string) $data['monto'], (int) $data['linea_solucion_id'], CarbonImmutable::parse($data['cierre']), $u);
                } catch (\Throwable $e) {
                    Notification::make()->danger()->title($e->getMessage())->send();
                    $action->halt();

                    return;
                }

                Notification::make()->success()->title('Convertido: nació su oportunidad.')
                    ->actions([Action::make('ver')->label('Abrir la oportunidad')->url(VerOportunidad::getUrl(['record' => $lead->oportunidad_id]))])->send();
            });
    }

    /** «Descartar» con su motivo. */
    public static function accionDescartar(): Action
    {
        return Action::make('descartar')->label('Descartar')->icon('heroicon-o-x-mark')->color('gray')
            ->schema([Select::make('motivo')->label('Motivo')->required()->options(array_combine($m = ['No es mercado para Techquk', 'Sin presupuesto', 'Solo pedía información', 'Datos falsos o duplicado', 'No respondió'], $m))])
            ->action(fn (Lead $record, array $data) => app(LeadsService::class)->descartar($record->id, $data['motivo']));
    }

    /** «Nuevo lead»: entra a Oportunidades como tarjeta «Por calificar» (observación 7.1). */
    public static function accionNuevo(): Action
    {
        return Action::make('nuevo')->label('Nuevo lead')->icon('heroicon-o-plus')->slideOver()
            ->visible(fn (): bool => LeadResource::puede(LeadsService::PERMISO_GESTIONAR))
            ->schema([
                TextInput::make('nombre_contacto')->label('Nombre')->required()->maxLength(150),
                TextInput::make('cargo')->label('Cargo')->maxLength(120),
                TextInput::make('organizacion')->label('Organización')->maxLength(200),
                TextInput::make('ruc')->label('RUC')->maxLength(11),
                TextInput::make('email')->label('Correo')->email()->maxLength(150),
                TextInput::make('telefono')->label('Teléfono')->maxLength(30),
                // Observación 9.3: el canal por el que llegó; el origen comercial sale de él (se puede cambiar).
                Select::make('canal_lead_id')->label('Canal')->required()->live()
                    ->options(fn (): array => array_map(fn (array $c): string => $c['nombre'], app(LeadsService::class)->canales(soloActivos: true)))
                    ->afterStateUpdated(fn (mixed $state, callable $set) => $set('origen_comercial_id', app(LeadsService::class)->origenDelCanal(is_numeric($state) ? (int) $state : null))),
                Select::make('origen_comercial_id')->label('Origen comercial')->required()->options(fn (): array => DB::table('origenes_comerciales')->orderBy('nombre')->pluck('nombre', 'id')->all()),
                Select::make('campania_id')->label('Campaña')->options(fn (): array => Campania::query()->where('es_activa', true)->pluck('nombre', 'id')->all()),
                Textarea::make('mensaje')->label('Qué pidió')->rows(3),
            ])
            ->action(function (array $data): void {
                // Observación 9.9: si ya lo conocemos (teléfono, correo o RUC), no se duplica: se une al que existe.
                $r = app(LeadsService::class)->llega([...$data, 'estado' => 'nuevo']);
                Notification::make()->success()->title($r['unido'] ? 'Ya lo conocíamos ('.(LeadsService::REGISTRADO_COMO[$r['como']] ?? 'ya registrado').'): quedó anotado, sin duplicar.' : ($r['como'] ? 'Lead registrado (ya registrado: '.LeadsService::REGISTRADO_COMO[$r['como']].').' : 'Lead registrado.'))->send();
            });
    }

    public static function getPages(): array
    {
        return ['index' => ListarLeads::route('/')];
    }
}
