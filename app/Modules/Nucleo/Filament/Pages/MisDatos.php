<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Filament\Pages;

use App\Modules\Nucleo\Excepciones\ErrorDeNegocio;
use App\Modules\Nucleo\Filament\AccionFilament;
use App\Modules\Nucleo\Filament\MisDatos\MisRedesSociales;
use App\Modules\Nucleo\Http\SesionDeClave;
use App\Modules\Nucleo\Models\Party;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\EjecutorAcciones;
use App\Modules\Nucleo\Services\ClaveService;
use App\Modules\Nucleo\Services\FotoPerfilService;
use App\Modules\Nucleo\Tiempo\Calendario;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Html;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Js;
use Illuminate\Validation\ValidationException;

/**
 * «Mis datos» (plan 1.4h, D1 a D4): lo de la cuenta de cada persona. Se ven nombre, documento, correo, puesto y último
 * acceso (no se cambian aquí); se cambian el cumpleaños y el teléfono (con «Guardar», con las versiones leídas al
 * abrir) y la contraseña (con un código que llega al correo). Desde el plan 1.4o, también la foto y las redes. Todo por
 * acciones registradas.
 *
 * @property-read Schema $form
 */
final class MisDatos extends Page
{
    protected static ?string $slug = 'mis-datos';

    protected static ?string $title = 'Mis datos';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-identification';

    /** No va en la barra de módulos: se abre desde el menú de la foto. */
    protected static bool $shouldRegisterNavigation = false;

    /** @var array<string, mixed> */
    public ?array $data = [];

    private const array MESES = [1 => 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];

    public static function canAccess(): bool
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
        $usuario = $this->usuario();
        $ficha = $this->ficha();

        return $schema->statePath('data')->components([
            // Plan 1.4o, D4 a D7: a la derecha de los datos, la foto del círculo del menú; tocarla abre la ventana para subirla.
            Section::make('Mi cuenta')
                ->schema([
                    Grid::make(['default' => 1, 'md' => 3])->schema([
                        Group::make([
                            TextEntry::make('nombre')->label('Nombre')->state($usuario->name)->inlineLabel(),
                            TextEntry::make('documento')->label('Documento')->inlineLabel()
                                ->state($ficha === null ? 'Sin ficha de persona' : "{$ficha->tipoDocumento->nombre} {$ficha->numero_documento}"),
                            TextEntry::make('correo')->label('Correo de acceso')->state($usuario->email)->inlineLabel(),
                            TextEntry::make('puesto')->label('Puesto')->inlineLabel()
                                ->state($usuario->puesto === null ? 'Sin puesto asignado' : $usuario->puesto->nombre.' · '.($usuario->puesto->unidad->nombre ?? '')),
                            TextEntry::make('ultimo_acceso')->label('Último acceso')->inlineLabel()
                                ->state($usuario->ultimo_acceso_at?->setTimezone(Calendario::ZONA)->translatedFormat('j \d\e F \d\e Y, H:i') ?? 'Todavía no hay registro'),
                        ])->columnSpan(['default' => 1, 'md' => 2]),
                        Group::make([
                            Html::make(fn (): HtmlString => $this->circuloFoto()),
                            Actions::make([$this->cambiarFoto(), $this->quitarFoto()])->alignCenter(),
                        ])->columnSpan(1),
                    ]),
                ]),
            Section::make('Mis datos de contacto')
                ->schema([
                    TextInput::make('cumpleanos_dia')->label('Día de tu cumpleaños')->integer()->minValue(1)->maxValue(31)->inlineLabel(),
                    Select::make('cumpleanos_mes')->label('Mes de tu cumpleaños')->options(self::MESES)->placeholder('Sin registrar')->inlineLabel(),
                    TextInput::make('telefono')->label('Teléfono')->maxLength(30)->tel()->inlineLabel()->visible($ficha !== null),
                    Hidden::make('version_cuenta'),
                    Hidden::make('version_ficha'),
                    Actions::make([Action::make('guardar')->label('Guardar')->action(fn () => $this->guardar())]),
                ]),
            // Plan 1.4o, D2 y D3: mis redes, y red por red quién las ve.
            Section::make('Mis redes')
                ->visible($ficha !== null)
                ->schema([Livewire::make(MisRedesSociales::class)->key('mis-redes-sociales')]),
            Section::make('Seguridad')->schema([
                Text::make('Para cambiar tu contraseña te enviaremos un código a tu correo de acceso.'),
                Actions::make([$this->cambiarClave()]),
            ]),
        ]);
    }

    public function guardar(): void
    {
        $usuario = $this->usuario();
        $datos = $this->form->getState();
        $ejecutor = app(EjecutorAcciones::class);

        try {
            $ejecutor->ejecutar('nucleo.registrar-mi-cumpleanos', [
                'cumpleanos_dia' => self::entero($datos['cumpleanos_dia'] ?? null),
                'cumpleanos_mes' => self::entero($datos['cumpleanos_mes'] ?? null),
                'version' => self::entero($datos['version_cuenta'] ?? null),
            ], $usuario);

            if ($this->ficha() !== null) {
                $telefono = trim((string) ($datos['telefono'] ?? ''));
                $ejecutor->ejecutar('nucleo.registrar-mi-telefono', ['telefono' => $telefono === '' ? null : $telefono, 'version' => (int) ($datos['version_ficha'] ?? 0)], $usuario);
            }
        } catch (ErrorDeNegocio|ValidationException $e) {
            Notification::make()->danger()->title(self::mensaje($e))->send();
            // Lo que alcanzó a guardarse queda; las versiones se releen para el siguiente intento.
            $this->llenar(conservar: $datos);

            return;
        }

        Notification::make()->success()->title('Guardado.')->send();
        $this->llenar();
    }

    /**
     * Subir o cambiar la foto (plan 1.4o, D5 y D6): el navegador abre el editor para encuadrarla en un círculo y la achica
     * a 400 × 400 antes de subirla, así que una foto de celular de cualquier tamaño entra sin errores.
     */
    private function cambiarFoto(): Action
    {
        return AccionFilament::desde('nucleo.cambiar-mi-foto', alTerminar: fn () => $this->fotoCambiada('Listo: tu foto nueva ya se ve en el menú.'), controles: [
            'foto' => fn (): Field => FileUpload::make('foto')->avatar()
                ->imageEditor()->circleCropper()->automaticallyOpenImageEditorForAspectRatio()
                ->automaticallyResizeImagesToWidth('400')->automaticallyResizeImagesToHeight('400')
                ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                ->maxSize(FotoPerfilService::TOPE_MB * 1024)
                ->extraAttributes(['class' => 'tq-subir-foto'])
                ->storeFiles(false),
        ])
            ->label(fn (): string => $this->usuario()->foto_ruta === null ? 'Subir mi foto' : 'Cambiar mi foto')
            ->icon('heroicon-o-camera')
            ->modalHeading('Mi foto')
            ->modalSubmitActionLabel('Guardar foto');
    }

    private function quitarFoto(): Action
    {
        return AccionFilament::desde('nucleo.quitar-mi-foto', alTerminar: fn () => $this->fotoCambiada('Listo: quitaste tu foto.'))
            ->label('Quitar mi foto')->icon('heroicon-o-trash')->color('gray')
            ->requiresConfirmation()->modalDescription('El menú volverá a mostrar tus iniciales.')
            ->hidden(fn (): bool => $this->usuario()->foto_ruta === null);
    }

    /**
     * El círculo con la foto (o las iniciales). Es un botón: abre la misma ventana que «Subir mi foto», con la misma
     * llamada que hace ese botón (la acción está dentro del formulario).
     */
    private function circuloFoto(): HtmlString
    {
        $etiqueta = $this->usuario()->foto_ruta === null ? 'Subir mi foto' : 'Cambiar mi foto';

        return new HtmlString('<button type="button" class="tq-mi-foto-boton" title="'.e($etiqueta).'" aria-label="'.e($etiqueta).'"'
            .' wire:click="mountAction(\'nucleoCambiarMiFoto\', {}, { schemaComponent: \'form\' })">'
            .'<img src="'.e(filament()->getUserAvatarUrl($this->usuario())).'" alt="Mi foto" class="tq-mi-foto">'
            .'</button>'
            .'<style>'
            .'.tq-mi-foto-boton { display: block; width: 100%; max-width: 13rem; margin: 0 auto; border-radius: 9999px; cursor: pointer; }'
            .'.tq-mi-foto { display: block; width: 100%; aspect-ratio: 1 / 1; border-radius: 9999px; object-fit: cover; }'
            // En la ventana para subirla, el círculo va al centro y del mismo tamaño que aquí. Clase interna de Filament:
            // excepción anotada en interfaz.md («Vistas propias»), vigilada por MiFotoYRedesTest.
            .'.tq-subir-foto .fi-fo-file-upload-input-ctn { width: 13rem; margin-inline: auto; }'
            .'</style>');
    }

    /**
     * La foto del menú cambia en el sitio, sin recargar la página. `fi-user-avatar` es de Filament: excepción anotada en
     * interfaz.md («Vistas propias»), vigilada por MiFotoYRedesTest.
     */
    private function fotoCambiada(string $aviso): void
    {
        $usuario = $this->usuario()->refresh();
        Notification::make()->success()->title($aviso)->send();
        $this->js('document.querySelectorAll(\'img.fi-user-avatar\').forEach((img) => { img.src = '.Js::from(filament()->getUserAvatarUrl($usuario)).'; })');
    }

    /**
     * «Cambiar mi contraseña» (D3): se pide el código («Enviar código»), llega al correo y se escribe con la nueva dos
     * veces. Esta sesión sigue; las de otros equipos se cierran.
     */
    private function cambiarClave(): Action
    {
        return Action::make('cambiarClave')
            ->label('Cambiar mi contraseña')
            ->icon('heroicon-o-key')
            ->modalHeading('Cambiar mi contraseña')
            ->modalDescription(fn (): string => 'Pide el código: llegará a '.ClaveService::correoOculto($this->usuario()->email).'. Vale 10 minutos.')
            ->schema([
                TextInput::make('codigo')->label('Código del correo')->length(6)->required()->autocomplete('one-time-code'),
                TextInput::make('clave')->label('Contraseña nueva')->password()->revealable()->required()->autocomplete('new-password')
                    ->helperText('Al menos 8 caracteres, con una mayúscula, una minúscula, un número y un carácter especial.'),
                TextInput::make('repetida')->label('Contraseña nueva, otra vez')->password()->revealable()->required()->autocomplete('new-password'),
            ])
            ->extraModalFooterActions([
                Action::make('enviarCodigo')->label('Enviar código')->color('gray')->action(function (): void {
                    try {
                        $resultado = app(EjecutorAcciones::class)->ejecutar('nucleo.pedir-codigo-clave', [], $this->usuario());
                        Notification::make()->success()->title('Te enviamos el código a '.(is_array($resultado) ? $resultado['correo'] : 'tu correo').'.')->send();
                    } catch (ErrorDeNegocio $e) {
                        Notification::make()->warning()->title($e->getMessage())->send();
                    }
                }),
            ])
            ->modalSubmitActionLabel('Cambiar contraseña')
            ->action(function (array $data, Action $action): void {
                try {
                    app(EjecutorAcciones::class)->ejecutar('nucleo.cambiar-mi-clave', [
                        'codigo' => (string) $data['codigo'], 'clave' => (string) $data['clave'], 'repetida' => (string) $data['repetida'],
                    ], $this->usuario());
                } catch (ErrorDeNegocio|ValidationException $e) {
                    Notification::make()->danger()->title(self::mensaje($e))->send();

                    throw (new Halt)->rollBackDatabaseTransaction(false);
                }

                SesionDeClave::seguirEnEsteEquipo($this->usuario());
                Notification::make()->success()->title('Listo: tu contraseña nueva está guardada. Se cerraron tus sesiones en otros equipos.')->send();
            });
    }

    /**
     * Lo guardado en los campos, con las versiones de ahora.
     *
     * @param  array<string, mixed>  $conservar  lo escrito que no se pudo guardar: se deja como estaba
     */
    private function llenar(array $conservar = []): void
    {
        $usuario = $this->usuario()->refresh();
        $ficha = $this->ficha();

        $this->form->fill([
            'cumpleanos_dia' => $usuario->cumpleanos_dia,
            'cumpleanos_mes' => $usuario->cumpleanos_mes,
            'telefono' => $ficha?->telefono,
            ...array_intersect_key($conservar, array_flip(['cumpleanos_dia', 'cumpleanos_mes', 'telefono'])),
            'version_cuenta' => $usuario->version,
            'version_ficha' => $ficha?->version,
        ]);
    }

    private function ficha(): ?Party
    {
        $partyId = $this->usuario()->party_id;

        return $partyId === null ? null : Party::query()->with('tipoDocumento')->find($partyId);
    }

    private static function entero(mixed $valor): ?int
    {
        return is_numeric($valor) ? (int) $valor : null;
    }

    private static function mensaje(ErrorDeNegocio|ValidationException $e): string
    {
        return $e instanceof ValidationException ? (string) collect($e->errors())->flatten()->first() : $e->getMessage();
    }

    private function usuario(): User
    {
        $usuario = Filament::auth()->user();

        return $usuario instanceof User ? $usuario : throw new AuthorizationException;
    }
}
