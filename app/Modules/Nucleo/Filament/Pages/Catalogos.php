<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Filament\Pages;

use App\Modules\Nucleo\Acciones\ActualizarUbigeos;
use App\Modules\Nucleo\Enums\Catalogo;
use App\Modules\Nucleo\Enums\GrupoCatalogo;
use App\Modules\Nucleo\Excepciones\ErrorDeNegocio;
use App\Modules\Nucleo\Filament\AccionFilament;
use App\Modules\Nucleo\Filament\Clusters\Configuracion;
use App\Modules\Nucleo\Models\Concerns\BusquedaSinAcentos;
use App\Modules\Nucleo\Models\Concerns\ElementoCatalogo;
use App\Modules\Nucleo\Models\RedSocial;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Services\EdicionCatalogosService;
use App\Modules\Nucleo\Services\UbigeosService;
use App\Modules\Nucleo\Ubigeos\DiferenciaUbigeos;
use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Url;

/**
 * Los catálogos compartidos (M5). Plan 1.4j: al entrar, una caja por catálogo, agrupadas por tema y con un buscador;
 * al presionar una, su tabla se abre en un panel en el lugar de las cajas, con la lista de Configuración a la vista
 * (`?catalogo=…` lo abre directo; la X o Esc lo cierran). Agregar arriba de la tabla; editar, campos de
 * dinero, desactivar o activar y borrar en cada fila, todo por las acciones registradas `nucleo.<catalogo>.*`
 * (plan 1.2b). La versión se captura al abrir cada formulario. Lo que usa el sistema (moneda local, DNI, RUC,
 * roles como `cliente`) no ofrece desactivar ni borrar, y sus campos fijos se ven sin poder cambiarse (plan 1.3,
 * D3). En ubigeos, además, buscar y aplicar la actualización del INEI.
 */
final class Catalogos extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $cluster = Configuracion::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-queue-list';

    protected static ?string $navigationLabel = 'Catálogos';

    protected static ?string $title = 'Catálogos';

    protected static ?string $slug = 'catalogos';

    protected static ?int $navigationSort = 3;

    /** El catálogo abierto en el panel, o null: las cajas. */
    #[Url]
    public ?string $catalogo = null;

    public static function canAccess(): bool
    {
        $usuario = Filament::auth()->user();

        return $usuario instanceof User && $usuario->can('catalogos.ver');
    }

    /**
     * Abre el panel de un catálogo sin recargar la página (se desliza; pedido del desarrollador, 2026-10-23): rehace la
     * tabla para ese catálogo. Uno que no existe no hace nada.
     */
    public function abrir(string $catalogo): void
    {
        if (Catalogo::tryFrom($catalogo) === null) {
            return;
        }

        $this->catalogo = $catalogo;
        $this->tableSearch = '';
        $this->resetTable();
    }

    /** Cierra el panel (la vista ya lo deslizó afuera) y devuelve el foco a la caja del catálogo que estaba abierto. */
    public function cerrar(): void
    {
        $anterior = $this->abierto();
        $this->catalogo = null;
        $this->tableSearch = '';
        $this->resetTable();

        if ($anterior !== null) {
            $this->js('document.querySelector('.json_encode('[data-catalogo="'.$anterior->value.'"]').')?.focus()');
        }
    }

    /** El abierto, o null si no hay ninguno (o el de la URL no existe). */
    public function abierto(): ?Catalogo
    {
        return Catalogo::tryFrom((string) $this->catalogo);
    }

    /** El de la tabla: el abierto; sin ninguno, la tabla no se dibuja. */
    public function actual(): Catalogo
    {
        return $this->abierto() ?? Catalogo::Monedas;
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            View::make('nucleo::filament.catalogos.pantalla')->viewData(fn (): array => [
                'grupos' => GrupoCatalogo::cases(),
                'abierto' => $this->abierto(),
            ]),
        ]);
    }

    /**
     * Configuración > Catálogos > Listado, como las demás pantallas; con un panel abierto, el nombre del catálogo en vez
     * de «Listado» (D1; pedido del desarrollador, 2026-10-23).
     */
    public function getBreadcrumbs(): array
    {
        $migas = [...Configuracion::unshiftClusterBreadcrumbs([]), self::getUrl() => 'Catálogos'];

        return [...$migas, $this->abierto()?->titulo() ?? 'Listado'];
    }

    public function table(Table $table): Table
    {
        $catalogo = $this->actual();
        $modelo = $catalogo->modelo();

        return $table
            ->query(fn (): Builder => $modelo::query())
            ->defaultSort('codigo')
            ->columns([
                TextColumn::make('codigo')->label('Código')->fontFamily('mono')->sortable()
                    ->searchable(query: function (Builder $query, string $search) use ($catalogo): Builder {
                        BusquedaSinAcentos::aplicar($query, $search, ['codigo', ...$catalogo->columnasBusqueda()]);

                        return $query;
                    }),
                TextColumn::make('etiqueta')->label('Nombre')->wrap()
                    ->state(fn (Model&ElementoCatalogo $record): string => $record->etiqueta())
                    ->icon(fn (Model $record): ?string => $record instanceof RedSocial ? $record->icono->componente() : null),
                IconColumn::make($modelo::columnaActivo())->label('Activo')->boolean(),
            ])
            ->headerActions([
                // Plan 2.20a, N2: un catálogo cerrado no agrega elementos.
                ...($catalogo->esCerrado() ? [] : [$this->accionCatalogo('crear')->label('Agregar')->icon('heroicon-o-plus')]),
                // En ubigeos, sus dos botones de actualización del INEI, dentro del panel.
                ...($catalogo === Catalogo::Ubigeos ? [$this->accionBuscarActualizacion(), $this->accionAplicarActualizacion()] : []),
            ])
            ->recordActions([
                $this->accionDeFila('editar', $catalogo->camposNoSensibles(), soloLectura: fn (Model $record): array => $this->esDelSistema($record) ? $catalogo->camposFijosDelSistema() : [])
                    ->label('Editar')->icon('heroicon-o-pencil-square'),
                ...($catalogo->tieneSensibles() ? [
                    $this->accionDeFila('editar-sensibles', $catalogo->camposSensibles())->label('Campos de dinero')->icon('heroicon-o-banknotes')->color('warning'),
                ] : []),
                // Plan 1.4z, D9: el ícono de una aplicación, subido desde aquí (PNG o WEBP).
                ...($catalogo === Catalogo::Aplicaciones ? [
                    AccionFilament::desde('nucleo.aplicaciones.subir-icono', fn (Model $record): array => ['aplicacion_id' => $record->getKey()],
                        function (): void {
                            Notification::make()->success()->title('Ícono subido.')->send();
                        })
                        ->label('Subir ícono')->icon('heroicon-o-photo'),
                ] : []),
                $this->accionDeFila('desactivar')->label('Desactivar')->icon('heroicon-o-eye-slash')->color('gray')->requiresConfirmation()
                    ->hidden(fn (Model&ElementoCatalogo $record): bool => ! $record->estaActivo() || $this->esDelSistema($record)),
                $this->accionDeFila('activar')->label('Activar')->icon('heroicon-o-eye')->color('success')->requiresConfirmation()
                    ->hidden(fn (Model&ElementoCatalogo $record): bool => $record->estaActivo()),
                $this->accionDeFila('borrar')->label('Borrar')->icon('heroicon-o-trash')->color('danger')->requiresConfirmation()
                    ->hidden(fn (Model $record): bool => $this->esDelSistema($record))
                    ->modalDescription('Solo se borra lo que nunca se usó. Si ya está en algún registro, desactívalo.'),
            ])
            ->emptyStateHeading($catalogo === Catalogo::Cubso ? 'CUBSO todavía no se carga' : 'Sin elementos')
            ->emptyStateDescription($catalogo === Catalogo::Cubso ? 'Se cargará cuando Licitaciones confirme que lo necesita.' : null);
    }

    private function esDelSistema(Model $record): bool
    {
        return app(EdicionCatalogosService::class)->esDelSistema($this->actual(), (string) $record->getAttribute('codigo'));
    }

    /** La acción registrada del catálogo abierto para la operación (`nucleo.formas-pago.crear`…). */
    private function accionCatalogo(string $operacion, ?Closure $datosFijos = null, ?Closure $soloLectura = null): Action
    {
        return AccionFilament::desde(
            'nucleo.'.str_replace('_', '-', $this->actual()->value).'.'.$operacion,
            $datosFijos,
            function (mixed $resultado): void {
                Notification::make()->success()->title('Guardado.')->send();
            },
            $soloLectura,
        );
    }

    /**
     * Acción sobre la fila: fija su código y llena, al abrir, la versión y los campos que edita.
     *
     * @param  list<string>  $campos
     */
    private function accionDeFila(string $operacion, array $campos = [], ?Closure $soloLectura = null): Action
    {
        return $this->accionCatalogo($operacion, fn (Model $record): array => ['codigo' => $record->getAttribute('codigo')], $soloLectura)
            ->fillForm(fn (Model $record): array => [
                ...array_combine($campos, array_map(fn (string $campo): mixed => $record->getAttribute($campo), $campos)),
                'version' => $record->getAttribute('version'),
            ]);
    }

    /** Solo mira: descarga y muestra qué cambiaría. No es una acción registrada porque no cambia nada. */
    private function accionBuscarActualizacion(): Action
    {
        return Action::make('buscarActualizacionUbigeos')
            ->label('Buscar actualización')
            ->icon('heroicon-o-magnifying-glass')
            ->visible(fn (): bool => Filament::auth()->user()?->can('ubigeos.actualizar') ?? false)
            ->action(function (UbigeosService $ubigeos): void {
                try {
                    $diferencia = $ubigeos->diferencias($ubigeos->descargar());
                } catch (ErrorDeNegocio $e) {
                    Notification::make()->danger()->title($e->getMessage())->send();

                    return;
                }

                $diferencia->hayCambios()
                    ? Notification::make()->warning()->title('Hay cambios en la lista del INEI')->body(self::resumen($diferencia).' Usa «Aplicar actualización» para traerlos.')->persistent()->send()
                    : Notification::make()->success()->title('Los ubigeos están al día.')->send();
            });
    }

    private function accionAplicarActualizacion(): Action
    {
        return AccionFilament::desde(
            app(ActualizarUbigeos::class),
            alTerminar: function (DiferenciaUbigeos $diferencia): void {
                Notification::make()->success()
                    ->title($diferencia->hayCambios() ? 'Ubigeos actualizados' : 'Los ubigeos ya estaban al día.')
                    ->body($diferencia->hayCambios() ? self::resumen($diferencia) : null)
                    ->send();
                $this->resetTable();
            },
        )
            ->label('Aplicar actualización')
            ->icon('heroicon-o-arrow-down-tray')
            ->requiresConfirmation()
            ->modalDescription('Se descarga la lista vigente del INEI: se agregan los distritos nuevos, se corrigen los nombres y se desactivan los del INEI que ya no están (no se borra ninguno). Los agregados a mano y los desactivados a mano se respetan.');
    }

    private static function resumen(DiferenciaUbigeos $d): string
    {
        $nuevos = array_slice(array_map(fn ($x): string => $x->descripcion(), $d->nuevos), 0, 5);

        return sprintf('Nuevos: %d · renombrados: %d · ya no están: %d · agregados a mano que ahora publica el INEI: %d.', count($d->nuevos), count($d->renombrados), count($d->retirados), count($d->adoptados))
            .($nuevos === [] ? '' : ' '.implode('; ', $nuevos).(count($d->nuevos) > 5 ? '…' : ''));
    }
}
