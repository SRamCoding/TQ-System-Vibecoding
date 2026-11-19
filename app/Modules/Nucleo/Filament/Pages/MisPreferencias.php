<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Filament\Pages;

use App\Modules\Nucleo\Excepciones\ErrorDeNegocio;
use App\Modules\Nucleo\Filament\AccionFilament;
use App\Modules\Nucleo\Filament\Apariencia;
use App\Modules\Nucleo\Filament\BarraModulos;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Preferencias\DefinicionPreferencia;
use App\Modules\Nucleo\Preferencias\FuenteDeLista;
use App\Modules\Nucleo\Preferencias\GrupoPreferencia;
use App\Modules\Nucleo\Preferencias\RegistroPreferencias;
use App\Modules\Nucleo\Preferencias\TipoPreferencia;
use App\Modules\Nucleo\RegistroAcciones\EjecutorAcciones;
use App\Modules\Nucleo\Services\NavegacionService;
use App\Modules\Nucleo\Services\PreferenciasUsuarioService;
use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;

/**
 * «Mis preferencias» (plan 1.4g, D6): se arma sola desde RegistroPreferencias, un grupo por sección en el orden de
 * GrupoPreferencia; un grupo sin preferencias no aparece. Cada cambio se guarda al instante con su acción registrada
 * (la general, o la propia de una lista) y se aplica sin recargar; los números, al salir del campo. Si algo se rechaza,
 * el control vuelve a su valor. «Restablecer» por grupo, con confirmación. Solo presentación: las reglas están en el
 * servicio.
 *
 * @property-read Schema $form
 */
final class MisPreferencias extends Page
{
    protected static ?string $slug = 'mis-preferencias';

    protected static ?string $title = 'Mis preferencias';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-adjustments-vertical';

    /** No va en la barra de módulos: se abre desde el menú del avatar. */
    protected static bool $shouldRegisterNavigation = false;

    /** @var array<string, mixed> */
    public ?array $data = [];

    /**
     * Las opciones de cada lista que se mostraron al abrir: lo desmarcado se calcula contra esto.
     *
     * @var array<string, list<string>>
     */
    public array $mostradas = [];

    /**
     * @param  array<string, mixed>  $parameters
     */
    public static function canAccess(array $parameters = []): bool
    {
        return Filament::auth()->user() instanceof User;
    }

    public function mount(): void
    {
        $this->llenar();
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([EmbeddedSchema::make('form')]);
    }

    public function form(Schema $schema): Schema
    {
        $secciones = [];

        foreach (GrupoPreferencia::cases() as $grupo) {
            $definiciones = self::visibles($grupo);

            if ($definiciones === []) {
                continue;
            }

            $secciones[] = Section::make($grupo->etiqueta())
                ->description($grupo->descripcion())
                ->key($grupo->value)
                ->headerActions([$this->restablecer($grupo)])
                ->schema(array_map(fn (DefinicionPreferencia $definicion): Field => $this->control($definicion), $definiciones));
        }

        return $schema->statePath('data')->components($secciones);
    }

    /** El nombre del campo de una preferencia: la clave sin el punto, que Filament leería como un anidamiento. */
    public static function campo(DefinicionPreferencia $definicion): string
    {
        return str_replace('.', '__', $definicion->clave());
    }

    /** @return list<DefinicionPreferencia> */
    private static function visibles(GrupoPreferencia $grupo): array
    {
        return array_values(array_filter(app(RegistroPreferencias::class)->delGrupo($grupo), fn (DefinicionPreferencia $definicion): bool => $definicion->seMuestra));
    }

    private function control(DefinicionPreferencia $definicion): Field
    {
        $usuario = $this->usuario();
        $nombre = self::campo($definicion);

        // Cada preferencia en su fila: el nombre y la ayuda a la izquierda, el control a la derecha. Las opciones, como
        // botones agrupados: la elegida se ve resaltada y no hay valores sueltos que adivinar.
        $control = match ($definicion->tipo) {
            TipoPreferencia::SiNo => Toggle::make($nombre)->inlineLabel(),
            TipoPreferencia::Numero => TextInput::make($nombre)->integer()->minValue($definicion->minimo)->maxValue($definicion->maximo)->inlineLabel(),
            // Pocas opciones fijas, en botones; las que dependen de la persona (sus módulos) crecen: en un desplegable.
            TipoPreferencia::Opcion => ! $definicion->opciones instanceof Closure && count($definicion->opcionesPara($usuario)) <= 4
                ? ToggleButtons::make($nombre)->options($definicion->opcionesPara($usuario))->grouped()->inlineLabel()
                : Select::make($nombre)->options($definicion->opcionesPara($usuario))->selectablePlaceholder(false)->inlineLabel(),
            TipoPreferencia::Lista => CheckboxList::make($nombre)->options($this->fuente($definicion)->opciones($usuario))->columns(2)->bulkToggleable(false),
        };

        return $control
            ->label($definicion->etiqueta)
            ->helperText($definicion->ayuda !== '' ? $definicion->ayuda : null)
            // En el celular no aplican, pero se ven: que no parezca que desaparecieron (D6).
            ->hint($definicion->soloEscritorio ? new HtmlString('<span class="tq-solo-celular">Solo en escritorio</span>') : null)
            // Los números, al salir del campo; lo demás, al cambiar (D6).
            ->live(onBlur: $definicion->tipo === TipoPreferencia::Numero)
            ->afterStateUpdated(fn (mixed $state) => $this->guardar($definicion, $state));
    }

    private function guardar(DefinicionPreferencia $definicion, mixed $state): void
    {
        $usuario = $this->usuario();

        try {
            if ($definicion->tipo === TipoPreferencia::Lista) {
                $fuente = $this->fuente($definicion);
                $elegidos = is_array($state) ? array_values(array_map(strval(...), $state)) : [];
                app(EjecutorAcciones::class)->ejecutar($fuente->accion(), $fuente->datosParaGuardar($elegidos, $this->mostradas[$definicion->clave()] ?? []), $usuario);
            } else {
                // La acción recibe texto; lo convierte el registro, como al guardar.
                $valor = is_bool($state) || is_int($state) || is_string($state) ? $definicion->tipo->aTexto($state) : '';
                app(EjecutorAcciones::class)->ejecutar('nucleo.cambiar-preferencia', ['clave' => $definicion->clave(), 'valor' => $valor], $usuario);
            }
        } catch (ErrorDeNegocio $e) {
            $this->rechazado($definicion, $e->getMessage());

            return;
        } catch (ValidationException $e) {
            $this->rechazado($definicion, (string) collect($e->errors())->flatten()->first());

            return;
        }

        $this->aplicar($definicion->grupo);
    }

    /** El control vuelve a lo guardado y se avisa por qué. */
    private function rechazado(DefinicionPreferencia $definicion, string $motivo): void
    {
        $this->data[self::campo($definicion)] = $this->valorDe($definicion);
        Notification::make()->danger()->title($motivo)->send();
    }

    private function restablecer(GrupoPreferencia $grupo): Action
    {
        return AccionFilament::desde('nucleo.restablecer-preferencias', fn (): array => ['grupo' => $grupo->value])
            ->name('restablecer'.ucfirst($grupo->value))
            ->label('Restablecer')
            ->link()
            ->requiresConfirmation()
            ->modalHeading("¿Volver {$grupo->etiqueta()} a como viene?")
            ->modalDescription('Se pierden los cambios que hiciste en este grupo.')
            ->modalSubmitActionLabel('Restablecer')
            ->after(function () use ($grupo): void {
                $this->llenar();
                $this->aplicar($grupo);
            });
    }

    /** Lo guardado, en los controles; y lo que se muestra de cada lista. */
    private function llenar(): void
    {
        $datos = [];

        foreach (GrupoPreferencia::cases() as $grupo) {
            foreach (self::visibles($grupo) as $definicion) {
                $datos[self::campo($definicion)] = $this->valorDe($definicion);

                if ($definicion->tipo === TipoPreferencia::Lista) {
                    $this->mostradas[$definicion->clave()] = array_map(strval(...), array_keys($this->fuente($definicion)->opciones($this->usuario())));
                }
            }
        }

        $this->form->fill($datos);
    }

    /** Lo que cambia a la vista en esta misma página, sin recargar. */
    private function aplicar(GrupoPreferencia $grupo): void
    {
        $usuario = $this->usuario();

        if ($grupo === GrupoPreferencia::Apariencia) {
            $this->dispatch(Apariencia::EVENTO, clases: Apariencia::clases($usuario), tema: Apariencia::tema($usuario));
        }

        if ($grupo === GrupoPreferencia::Navegacion) {
            $navegacion = app(NavegacionService::class);
            $this->dispatch(BarraModulos::EVENTO, visible: $navegacion->barraVisible($usuario), ocultos: $navegacion->ocultos($usuario));
            // El ícono de «Mostrar barra de módulos» en el menú del avatar.
            $this->dispatch('refresh-topbar');
        }
    }

    /** @return bool|int|string|list<string> */
    private function valorDe(DefinicionPreferencia $definicion): bool|int|string|array
    {
        $valor = app(PreferenciasUsuarioService::class)->valor($this->usuario(), $definicion->clave());

        // Las claves de una lista van como texto, igual que sus opciones en las casillas.
        return is_array($valor) ? array_map(strval(...), $valor) : $valor;
    }

    private function fuente(DefinicionPreferencia $definicion): FuenteDeLista
    {
        return app(PreferenciasUsuarioService::class)->fuente($definicion);
    }

    private function usuario(): User
    {
        $usuario = Filament::auth()->user();

        return $usuario instanceof User ? $usuario : throw new AuthorizationException;
    }
}
