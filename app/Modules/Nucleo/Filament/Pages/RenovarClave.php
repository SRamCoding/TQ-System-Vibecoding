<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Filament\Pages;

use App\Modules\Nucleo\Excepciones\ErrorDeNegocio;
use App\Modules\Nucleo\Http\SesionDeClave;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\EjecutorAcciones;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

/**
 * «Cambia tu contraseña» (plan 1.4h, D5): para quien entró con una contraseña que no cumple la regla. No puede usar
 * nada más hasta renovarla (ExigirClaveRenovada). Sin código por correo: acaba de entrar con la suya. Guarda con la
 * acción `nucleo.renovar-mi-clave`.
 *
 * @property-read Schema $form
 */
final class RenovarClave extends Page
{
    protected static ?string $slug = 'renovar-clave';

    protected static ?string $title = 'Cambia tu contraseña';

    protected static bool $shouldRegisterNavigation = false;

    /** @var array<string, mixed> */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        $usuario = Filament::auth()->user();

        return $usuario instanceof User && $usuario->debe_cambiar_clave;
    }

    public function mount(): void
    {
        $this->form->fill();
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([EmbeddedSchema::make('form')]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
            Section::make('Tu contraseña ya no cumple la regla')
                ->description('Para seguir, elige una nueva: al menos 8 caracteres, con una mayúscula, una minúscula, un número y un carácter especial (por ejemplo, ! # $ % & * ? @).')
                ->schema([
                    TextInput::make('clave')->label('Contraseña nueva')->password()->revealable()->required()->autocomplete('new-password'),
                    TextInput::make('repetida')->label('Contraseña nueva, otra vez')->password()->revealable()->required()->autocomplete('new-password'),
                    Text::make('Al guardarla, se cerrarán tus sesiones en otros equipos.'),
                    Actions::make([Action::make('guardar')->label('Guardar mi contraseña nueva')->action(fn () => $this->guardar())]),
                ]),
        ]);
    }

    public function guardar(): void
    {
        $usuario = Filament::auth()->user();

        if (! $usuario instanceof User) {
            throw new AuthorizationException;
        }

        $datos = $this->form->getState();

        try {
            app(EjecutorAcciones::class)->ejecutar('nucleo.renovar-mi-clave', ['clave' => (string) $datos['clave'], 'repetida' => (string) $datos['repetida']], $usuario);
        } catch (ErrorDeNegocio|ValidationException $e) {
            $mensaje = $e instanceof ValidationException ? (string) collect($e->errors())->flatten()->first() : $e->getMessage();
            Notification::make()->danger()->title($mensaje)->send();

            return;
        }

        SesionDeClave::seguirEnEsteEquipo($usuario);
        Notification::make()->success()->title('Listo: tu contraseña nueva está guardada.')->send();
        $this->redirect(Filament::getUrl());
    }
}
