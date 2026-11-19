<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Filament\Resources\Aprobaciones;

use App\Modules\Nucleo\Enums\CondicionRegla;
use App\Modules\Nucleo\Enums\FirmanteAprobacion;
use App\Modules\Nucleo\Estados\DefinicionDocumento;
use App\Modules\Nucleo\Estados\RegistroDocumentos;
use App\Modules\Nucleo\Filament\AccionFilament;
use App\Modules\Nucleo\Filament\Clusters\Configuracion;
use App\Modules\Nucleo\Filament\EtiquetasEnEspanol;
use App\Modules\Nucleo\Filament\Resources\Aprobaciones\Pages\ListarReglasAprobacion;
use App\Modules\Nucleo\Models\PasoReglaAprobacion;
use App\Modules\Nucleo\Models\ReglaAprobacion;
use App\Modules\Nucleo\Models\UnidadOrganizativa;
use App\Modules\Nucleo\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Configuración → Aprobaciones (plan 1.7, D8): las reglas de la matriz de aprobaciones del v4 como datos. Para cada
 * paso que requiere aprobación, en un rango de montos en soles, qué jefaturas firman y en qué orden. Agregar, editar,
 * desactivar y activar pasan por las acciones registradas `nucleo.aprobaciones.*`; la versión se captura al abrir.
 */
final class ReglaAprobacionResource extends Resource
{
    use EtiquetasEnEspanol;

    protected static ?string $model = ReglaAprobacion::class;

    protected static ?string $cluster = Configuracion::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-check';

    protected static ?string $modelLabel = 'regla de aprobación';

    protected static ?string $pluralModelLabel = 'reglas de aprobación';

    protected static ?string $navigationLabel = 'Aprobaciones';

    protected static ?string $slug = 'aprobaciones';

    protected static ?int $navigationSort = 13;

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

    /** La página elige la tabla según la pestaña (`ListarReglasAprobacion::table`): aquí no se arma ninguna. */
    public static function table(Table $table): Table
    {
        return $table;
    }

    /** La pestaña «Reglas». */
    public static function tablaDeReglas(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('pasos'))
            ->defaultSort(fn ($query) => $query->orderBy('documento_type')->orderBy('transicion_codigo')->orderByRaw('COALESCE(monto_mas_de, 0)'))
            ->columns([
                TextColumn::make('nombre')->label('Regla')->searchable()->wrap(),
                TextColumn::make('documento_type')->label('Documento')
                    ->formatStateUsing(fn (string $state, ReglaAprobacion $record): string => self::documentoYPaso($record)),
                TextColumn::make('rango')->label('Monto (S/)')
                    ->state(fn (ReglaAprobacion $record): string => self::rango($record)),
                TextColumn::make('firmas')->label('Firman, en orden')->wrap()
                    ->state(fn (ReglaAprobacion $record): string => implode(' → ', $record->pasos->map(fn (PasoReglaAprobacion $paso): string => self::etiquetaPaso(self::clavePaso($paso)))->all())),
                TextColumn::make('tope_autoaprobacion')->label('Autoaprobación hasta')
                    ->formatStateUsing(fn (?string $state): string => 'S/ '.number_format((float) $state, 2))
                    ->placeholder('No se permite'),
                IconColumn::make('es_activa')->label('Activa')->boolean(),
            ])
            ->headerActions([self::accionRegla('nucleo.aprobaciones.crear-regla')->label('Agregar')->icon('heroicon-o-plus')])
            ->recordActions([
                self::accionRegla('nucleo.aprobaciones.editar-regla', fn (ReglaAprobacion $record): array => ['regla_id' => $record->id])
                    ->label('Editar')->icon('heroicon-o-pencil-square')
                    ->fillForm(fn (ReglaAprobacion $record): array => [
                        'nombre' => $record->nombre,
                        'documento_type' => $record->documento_type,
                        'transicion' => $record->transicion_codigo,
                        'monto_mas_de' => $record->monto_mas_de,
                        'monto_hasta' => $record->monto_hasta,
                        'tope_autoaprobacion' => $record->tope_autoaprobacion,
                        'condicion' => $record->condicion->value,
                        'pasos' => $record->pasos->map(fn (PasoReglaAprobacion $paso): string => self::clavePaso($paso))->all(),
                        'version' => $record->version,
                    ]),
                AccionFilament::desde('nucleo.aprobaciones.desactivar-regla', fn (ReglaAprobacion $record): array => ['regla_id' => $record->id], self::avisar(...))
                    ->label('Desactivar')->icon('heroicon-o-eye-slash')->color('gray')->requiresConfirmation()
                    ->fillForm(fn (ReglaAprobacion $record): array => ['version' => $record->version])
                    ->hidden(fn (ReglaAprobacion $record): bool => ! $record->es_activa),
                AccionFilament::desde('nucleo.aprobaciones.activar-regla', fn (ReglaAprobacion $record): array => ['regla_id' => $record->id], self::avisar(...))
                    ->label('Activar')->icon('heroicon-o-eye')->color('success')->requiresConfirmation()
                    ->fillForm(fn (ReglaAprobacion $record): array => ['version' => $record->version])
                    ->hidden(fn (ReglaAprobacion $record): bool => $record->es_activa),
            ])
            ->emptyStateHeading('Todavía no hay reglas de aprobación')
            ->emptyStateDescription('Sin reglas, un documento que pide aprobación espera: agrega una para cada paso que se aprueba.');
    }

    /** Crear y editar comparten el formulario: selectores para el documento, el paso y las firmas en orden. */
    private static function accionRegla(string $clave, ?\Closure $datosFijos = null): Action
    {
        return AccionFilament::desde($clave, $datosFijos, self::avisar(...), controles: [
            'documento_type' => fn () => Select::make('documento_type')->options(self::documentos())->live(),
            'transicion' => fn () => Select::make('transicion')->options(fn (Get $get): array => self::pasosQueSeAprueban((string) $get('documento_type'))),
            // El orden de las firmas es el orden en que se eligen.
            'pasos' => fn () => Select::make('pasos')->multiple()->searchable()->options(self::firmantes(...)),
            // Planes 2.2 y 2.6: con una condición no hay autoaprobación; el tope no aparece (la base lo exige).
            'condicion' => fn () => Select::make('condicion')->options(collect(CondicionRegla::cases())->mapWithKeys(fn (CondicionRegla $c): array => [$c->value => $c->etiqueta()])->all())->live(),
            'tope_autoaprobacion' => fn () => TextInput::make('tope_autoaprobacion')->numeric()
                ->hidden(fn (Get $get): bool => ! (CondicionRegla::tryFrom((string) $get('condicion')) ?? CondicionRegla::Ninguna)->admiteAutoaprobacion()),
        ]);
    }

    private static function avisar(): void
    {
        Notification::make()->success()->title('Regla guardada.')->send();
    }

    /**
     * Los documentos que tienen algún paso con aprobación.
     *
     * @return array<string, string>
     */
    private static function documentos(): array
    {
        return collect(app(RegistroDocumentos::class)->definiciones())
            ->filter(fn (DefinicionDocumento $definicion): bool => self::pasosQueSeAprueban($definicion->tipo()) !== [])
            ->mapWithKeys(fn (DefinicionDocumento $definicion): array => [$definicion->tipo() => ucfirst($definicion->nombre())])
            ->all();
    }

    /**
     * @return array<string, string>
     */
    private static function pasosQueSeAprueban(string $tipo): array
    {
        $definicion = app(RegistroDocumentos::class)->definicionDe($tipo);

        return $definicion === null ? [] : collect($definicion->transiciones())
            ->filter(fn ($transicion): bool => $transicion->requiereAprobacion)
            ->mapWithKeys(fn ($transicion): array => [$transicion->codigo => $transicion->nombre])
            ->all();
    }

    /**
     * «La jefatura del documento» y la de cada unidad activa del organigrama.
     *
     * @return array<string, string>
     */
    private static function firmantes(mixed $state = null): array
    {
        $opciones = ['documento' => FirmanteAprobacion::UnidadDelDocumento->etiqueta()];

        foreach (UnidadOrganizativa::query()->where('es_activa', true)->orderBy('nombre')->get() as $unidad) {
            $opciones["unidad:{$unidad->id}"] = "Jefatura de {$unidad->nombre}";
        }

        // La que la regla ya tiene, aunque la unidad se haya desactivado (AGENTS.md).
        foreach (is_array($state) ? $state : [] as $clave) {
            $opciones[$clave] ??= self::etiquetaPaso((string) $clave);
        }

        return $opciones;
    }

    private static function clavePaso(PasoReglaAprobacion $paso): string
    {
        return $paso->firmante === FirmanteAprobacion::UnidadDelDocumento ? 'documento' : "unidad:{$paso->unidad_organizativa_id}";
    }

    private static function etiquetaPaso(string $clave): string
    {
        if ($clave === 'documento') {
            return FirmanteAprobacion::UnidadDelDocumento->etiqueta();
        }

        $nombre = UnidadOrganizativa::query()->whereKey((int) substr($clave, strlen('unidad:')))->value('nombre');

        return 'Jefatura de '.($nombre ?? '(unidad borrada)');
    }

    private static function documentoYPaso(ReglaAprobacion $regla): string
    {
        $definicion = app(RegistroDocumentos::class)->definicionDe($regla->documento_type);

        if ($definicion === null) {
            return $regla->documento_type;
        }

        return ucfirst($definicion->nombre()).' · '.($definicion->transicion($regla->transicion_codigo)->nombre ?? $regla->transicion_codigo);
    }

    private static function rango(ReglaAprobacion $regla): string
    {
        $soles = fn (?string $monto): string => number_format((float) $monto, 2);

        // Plan 2.2, D5: la condición se lee junto al monto.
        $condicion = $regla->condicion === CondicionRegla::Ninguna ? '' : ' · '.mb_strtolower($regla->condicion->etiqueta());

        return match (true) {
            $regla->monto_mas_de === null && $regla->monto_hasta === null => 'Cualquier monto',
            $regla->monto_mas_de === null => "Hasta {$soles($regla->monto_hasta)}",
            $regla->monto_hasta === null => "Más de {$soles($regla->monto_mas_de)}",
            default => "Más de {$soles($regla->monto_mas_de)} hasta {$soles($regla->monto_hasta)}",
        }.$condicion;
    }

    public static function getPages(): array
    {
        return ['index' => ListarReglasAprobacion::route('/')];
    }
}
