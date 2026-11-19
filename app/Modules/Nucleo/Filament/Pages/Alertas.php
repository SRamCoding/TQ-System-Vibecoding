<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Filament\Pages;

use App\Modules\Nucleo\Acciones\Alertas\ConfigurarAlerta;
use App\Modules\Nucleo\Alertas\DefinicionAlerta;
use App\Modules\Nucleo\Alertas\RegistroAlertas;
use App\Modules\Nucleo\Excepciones\ErrorDeNegocio;
use App\Modules\Nucleo\Filament\Clusters\Configuracion;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Notificaciones\RegistroAvisos;
use App\Modules\Nucleo\RegistroAcciones\EjecutorAcciones;
use App\Modules\Nucleo\Services\AlertasService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Js;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Url;
use Spatie\Permission\Models\Role;

/**
 * Configuración → Alertas (plan 1.10, D7): el catálogo único de alertas. Al entrar, una caja por alerta (con su ícono),
 * agrupadas por su grupo de avisos y con un buscador; al presionar una, su configuración se abre en un panel que se
 * desliza desde la derecha encima de las cajas, sin recargar (`?alerta=…` lo abre directo; la X o Esc lo cierran).
 * Se eligen sus roles, si está activa, su anticipación y sus textos: los datos van entre llaves y se insertan con su
 * botón; el servicio no guarda un texto sin ellos (D5, opción B). Guarda con `nucleo.alertas.configurar`; la versión
 * se captura al abrir el panel.
 *
 * @property-read Schema $form
 */
final class Alertas extends Page
{
    protected static ?string $cluster = Configuracion::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-bell-alert';

    protected static ?string $navigationLabel = 'Alertas';

    protected static ?string $title = 'Alertas';

    protected static ?string $slug = 'alertas';

    protected static ?int $navigationSort = 14;

    /** La alerta abierta en el panel, o null: las cajas. */
    #[Url]
    public ?string $alerta = null;

    /** @var array<string, mixed> */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        $usuario = Filament::auth()->user();

        return $usuario instanceof User && $usuario->can(ConfigurarAlerta::PERMISO);
    }

    public function mount(): void
    {
        if ($this->abierta() === null) {
            $this->alerta = null;

            return;
        }

        $this->llenar();
    }

    /** Abre el panel de una alerta sin recargar la página y lee su configuración de ahora (con su versión). */
    public function abrir(string $clave): void
    {
        if (! array_key_exists($clave, app(RegistroAlertas::class)->alertas())) {
            return;
        }

        $this->alerta = $clave;
        $this->llenar();
    }

    /** Cierra el panel (la vista ya lo deslizó afuera) y devuelve el foco a la caja de la alerta que estaba abierta. */
    public function cerrar(): void
    {
        $anterior = $this->alerta;
        $this->alerta = null;
        $this->data = [];

        if ($anterior !== null) {
            $this->js('document.querySelector('.json_encode('[data-alerta="'.$anterior.'"]').')?.focus()');
        }
    }

    public function abierta(): ?DefinicionAlerta
    {
        return app(RegistroAlertas::class)->alertas()[(string) $this->alerta] ?? null;
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            View::make('nucleo::filament.alertas.pantalla')->viewData(fn (): array => [
                'grupos' => $this->grupos(),
                'abierta' => $this->abierta(),
                'sinNadie' => $this->sinNadie(),
            ]),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        $alerta = $this->abierta();

        if ($alerta === null) {
            return $schema->statePath('data')->components([]);
        }

        $marcadores = collect($alerta->datos)->map(fn (string $etiqueta, string $dato): string => "{{$dato}} = {$etiqueta}")->implode(' · ');

        return $schema->statePath('data')->components([
            Select::make('roles')->label('¿Quiénes la reciben?')->multiple()->searchable()->required(! $alerta->conDestinatarioPropio())
                ->options(fn (): array => Role::query()->orderBy('name')->pluck('name', 'id')->map(fn (string $nombre): string => Str::ucfirst($nombre))->all())
                // Plan 2.2, D6: la que llega siempre a una persona puntual lo dice, para que nadie crea que quedó sin destinatarios.
                ->helperText($alerta->conDestinatarioPropio()
                    ? "Siempre llega {$alerta->destinatarioPropio}. Con estos roles, le llega también a toda persona activa que los tenga."
                    : 'Le llega a toda persona activa con alguno de estos roles. Al menos uno.'),
            Toggle::make('es_activa')->label('Activa')
                ->disabled($alerta->obligatoria)
                ->helperText($alerta->obligatoria ? 'Obligatoria: no se puede apagar, y llega por correo aunque la persona lo haya apagado.' : null),
            TextInput::make('anticipacion_dias')->label('Días de anticipación')->placeholder('60,30,15')
                ->visible($alerta->anticipacion !== null)
                ->helperText('Cuántos días antes avisa, separados por comas.'),
            TextInput::make('asunto')->label('Asunto')->required()->maxLength(200)
                ->extraInputAttributes(['data-tq-texto-alerta' => true]),
            Textarea::make('texto')->label('Texto')->required()->rows(3)->maxLength(2000)
                ->extraInputAttributes(['data-tq-texto-alerta' => true])
                ->helperText("Los datos van entre llaves y el sistema los llena con el valor real: {$marcadores}. Sin uno de ellos no se guarda."),
            Actions::make(collect($alerta->datos)->map(fn (string $etiqueta, string $dato): Action => Action::make('insertar_'.$dato)
                ->label("+ {$etiqueta}")
                ->link()
                ->alpineClickHandler('window.tqInsertarDatoAlerta('.Js::from('{'.$dato.'}').')'))->values()->all()),
            Hidden::make('version'),
            Actions::make([
                Action::make('guardar')->label('Guardar')->action(fn () => $this->guardar()),
                Action::make('porDefecto')->label('Volver al texto por defecto')->color('gray')->action(fn () => $this->textoPorDefecto()),
            ]),
        ]);
    }

    public function guardar(): void
    {
        $usuario = Filament::auth()->user();
        $alerta = $this->abierta();

        if (! $usuario instanceof User || $alerta === null) {
            throw new AuthorizationException;
        }

        $datos = $this->form->getState();

        try {
            app(EjecutorAcciones::class)->ejecutar('nucleo.alertas.configurar', [
                'alerta' => $alerta->clave,
                'roles' => array_values(array_map(intval(...), (array) ($datos['roles'] ?? []))),
                'es_activa' => $alerta->obligatoria || (bool) ($datos['es_activa'] ?? false),
                'anticipacion_dias' => $datos['anticipacion_dias'] ?? null,
                'asunto' => (string) ($datos['asunto'] ?? ''),
                'texto' => (string) ($datos['texto'] ?? ''),
                'version' => (int) ($this->data['version'] ?? 0),
            ], $usuario);
        } catch (ErrorDeNegocio|ValidationException $e) {
            $mensaje = $e instanceof ValidationException ? (string) collect($e->errors())->flatten()->first() : $e->getMessage();
            Notification::make()->danger()->title($mensaje)->send();

            return;
        }

        Notification::make()->success()->title('Guardado.')->send();
        $this->llenar();
    }

    /** Pone el asunto y el texto de fábrica; se aplican al guardar. */
    public function textoPorDefecto(): void
    {
        $alerta = $this->abierta();

        if ($alerta === null) {
            return;
        }

        $this->data['asunto'] = $alerta->asunto;
        $this->data['texto'] = $alerta->texto;
        Notification::make()->info()->title('Texto por defecto puesto: guarda para aplicarlo.')->send();
    }

    /**
     * Configuración > Alertas > Listado; con una alerta abierta, su nombre en vez de «Listado».
     */
    public function getBreadcrumbs(): array
    {
        $migas = [...Configuracion::unshiftClusterBreadcrumbs([]), self::getUrl() => 'Alertas'];

        return [...$migas, $this->abierta()->nombre ?? 'Listado'];
    }

    /** Lee la configuración de ahora de la alerta abierta, con su versión (bloqueo optimista: al abrir, no al guardar). */
    private function llenar(): void
    {
        $alerta = $this->abierta();

        if ($alerta === null) {
            return;
        }

        $tipo = app(AlertasService::class)->tipo($alerta);

        $this->form->fill([
            'roles' => $tipo->roles()->pluck('rol_id')->map(fn (mixed $id): int => (int) $id)->all(),
            'es_activa' => $alerta->obligatoria || $tipo->es_activa,
            'anticipacion_dias' => $tipo->anticipacion_dias,
            'asunto' => $tipo->asunto ?? $alerta->asunto,
            'texto' => $tipo->texto ?? $alerta->texto,
            'version' => $tipo->version,
        ]);
    }

    /**
     * Las alertas por su grupo de avisos, en el orden en que se declararon.
     *
     * @return list<array{nombre: string, alertas: list<DefinicionAlerta>}>
     */
    private function grupos(): array
    {
        $avisos = app(RegistroAvisos::class);

        return collect(app(RegistroAlertas::class)->alertas())
            ->groupBy(fn (DefinicionAlerta $alerta): string => $alerta->grupo)
            ->map(fn ($alertas, string $grupo): array => ['nombre' => $avisos->grupo($grupo)->nombre, 'alertas' => $alertas->values()->all()])
            ->values()
            ->all();
    }

    /**
     * Las alertas cuyos roles no tienen a ninguna persona activa: hoy le llegan al administrador (D2).
     *
     * @return list<string>
     */
    private function sinNadie(): array
    {
        return app(AlertasService::class)->sinDestinatariosPropiosDe(app(RegistroAlertas::class)->alertas());
    }
}
