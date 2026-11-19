<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Filament\Resources\Parties\Pages;

use App\Modules\Nucleo\Acciones\Parties\RegistrarParty;
use App\Modules\Nucleo\Enums\Catalogo;
use App\Modules\Nucleo\Enums\TipoPersona;
use App\Modules\Nucleo\Excepciones\ErrorDeNegocio;
use App\Modules\Nucleo\Filament\AccionFilament;
use App\Modules\Nucleo\Filament\Resources\Parties\PartyResource;
use App\Modules\Nucleo\Filament\SelectorCatalogo;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Parties\FuentePadronContribuyentes;
use App\Modules\Nucleo\Parties\PartyRegistrada;
use App\Modules\Nucleo\RegistroAcciones\EjecutorAcciones;
use App\Modules\Nucleo\Services\CatalogosService;
use App\Modules\Nucleo\Services\PartiesService;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Alta de una persona o empresa en dos pasos, como «Crear cuenta» del v4 (plan 1.3, D12). Paso 1: el
 * documento; si ya existe, avisa (y ofrece activarla si está inactiva) y no sigue; si existe el otro
 * documento de la misma persona (DNI ↔ RUC 10), avisa y sigue (D9). Paso 2: los datos, con lo que dé el
 * padrón de SUNAT cuando se integre. Se registra por la acción `nucleo.parties.registrar`.
 *
 * @property-read Schema $form
 */
final class NuevaParty extends Page
{
    protected static string $resource = PartyResource::class;

    protected static ?string $title = 'Registrar persona o empresa';

    /** @var array<string, mixed> */
    public ?array $data = [];

    /**
     * La party que ya tiene el documento: id, etiqueta, si está activa y su versión.
     *
     * @var ?array{id: int, etiqueta: string, activa: bool, version: int}
     */
    public ?array $existente = null;

    /**
     * La party del otro documento de la misma persona (D9).
     *
     * @var ?array{id: int, etiqueta: string}
     */
    public ?array $pareja = null;

    /** El tipo de persona que dice el documento; nulo si lo elige quien registra. */
    public ?string $tipoDeducido = null;

    public static function canAccess(array $parameters = []): bool
    {
        return PartyResource::usuarioPuede('parties.crear');
    }

    public function mount(): void
    {
        $this->form->fill();
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])->id('form')->livewireSubmitHandler('registrar'),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
            Wizard::make([
                Step::make('Documento')
                    ->description('Se empieza por el documento: así no se repite a nadie.')
                    ->schema([
                        // Plan 2.0, D4: los selectores de catálogo son el común (búsqueda en la base y «+»).
                        SelectorCatalogo::de(Catalogo::TiposDocumentoIdentidad, 'tipo_documento_id')->label('Tipo de documento')->required()->live()
                            ->afterStateUpdated(fn () => $this->olvidarRevision()),
                        TextInput::make('numero_documento')->label('Número')->required()->maxLength(30)->live(onBlur: true)
                            ->afterStateUpdated(fn () => $this->olvidarRevision()),
                        Callout::make(fn (): string => ($this->existente['activa'] ?? true) ? 'Ya está registrada' : 'Ya está registrada, pero está inactiva')
                            ->description(fn (): string => $this->existente['etiqueta'] ?? '')
                            ->danger()
                            ->key('existente')
                            ->visible(fn (): bool => $this->existente !== null)
                            ->actions([
                                Action::make('abrirExistente')->label('Abrirla')
                                    ->url(fn (): ?string => $this->existente === null ? null : VerParty::getUrl(['record' => $this->existente['id']])),
                                $this->accionActivarExistente(),
                            ]),
                    ])
                    ->afterValidation(fn () => $this->revisarDocumento()),
                Step::make('Datos')
                    ->schema([
                        $this->avisoPareja(),
                        Text::make(fn (): string => 'Tipo de persona: '.(TipoPersona::tryFrom((string) $this->tipoDeducido)?->etiqueta() ?? ''))
                            ->visible(fn (): bool => $this->tipoDeducido !== null),
                        Select::make('tipo')->label('Tipo de persona')->live()
                            ->options([TipoPersona::Natural->value => TipoPersona::Natural->etiqueta(), TipoPersona::Juridica->value => TipoPersona::Juridica->etiqueta()])
                            ->required(fn (): bool => $this->tipoDeducido === null)
                            ->visible(fn (): bool => $this->tipoDeducido === null),
                        TextInput::make('nombres')->label('Nombres')->maxLength(100)
                            ->required(fn (Get $get): bool => $this->tipoElegido($get) === TipoPersona::Natural)
                            ->visible(fn (Get $get): bool => $this->tipoElegido($get) === TipoPersona::Natural),
                        TextInput::make('apellidos')->label('Apellidos')->maxLength(100)
                            ->required(fn (Get $get): bool => $this->tipoElegido($get) === TipoPersona::Natural)
                            ->visible(fn (Get $get): bool => $this->tipoElegido($get) === TipoPersona::Natural),
                        TextInput::make('razon_social')->label('Razón social')->maxLength(200)
                            ->required(fn (Get $get): bool => $this->tipoElegido($get) === TipoPersona::Juridica)
                            ->visible(fn (Get $get): bool => $this->tipoElegido($get) === TipoPersona::Juridica),
                        TextInput::make('nombre_comercial')->label('Nombre comercial')->maxLength(200),
                        TextInput::make('direccion_fiscal')->label('Dirección fiscal')->maxLength(255),
                        SelectorCatalogo::de(Catalogo::Ubigeos, 'ubigeo_id')->label('Ubigeo'),
                        TextInput::make('telefono')->label('Teléfono')->maxLength(30),
                        TextInput::make('email')->label('Correo')->email()->maxLength(150),
                        SelectorCatalogo::de(Catalogo::RolesParty, 'roles', varios: true)->label('Roles (desde hoy)')
                            ->visible(fn (): bool => PartyResource::usuarioPuede(RegistrarParty::PERMISO_ROLES)),
                    ]),
            ])->submitAction(new HtmlString(Blade::render('<x-filament::button type="submit">Registrar</x-filament::button>'))),
        ]);
    }

    /**
     * Paso 1 → 2: valida el documento con las reglas del servicio, busca si ya existe y su pareja, y precarga
     * lo que dé el padrón. Si ya existe, no se sigue.
     *
     * @throws Halt|ValidationException
     */
    public function revisarDocumento(): void
    {
        $parties = app(PartiesService::class);
        $codigo = app(CatalogosService::class)->codigoDe(Catalogo::TiposDocumentoIdentidad, (int) $this->data['tipo_documento_id']);

        try {
            $documento = $parties->documento($codigo, (string) $this->data['numero_documento']);
        } catch (ErrorDeNegocio $e) {
            throw ValidationException::withMessages(['data.numero_documento' => $e->getMessage()]);
        }

        $existente = $parties->buscarPorDocumento($codigo, $documento->numero);

        if ($existente !== null) {
            $this->existente = [
                'id' => $existente->id,
                'etiqueta' => $existente->etiqueta,
                'activa' => $existente->activa,
                'version' => (int) PartyResource::getModel()::query()->whereKey($existente->id)->value('version'),
            ];

            throw new Halt;
        }

        $pareja = $parties->posibleMismaPersona($codigo, $documento->numero);
        $this->pareja = $pareja === null ? null : ['id' => $pareja->id, 'etiqueta' => $pareja->etiqueta];
        $this->tipoDeducido = $documento->tipoPersona()?->value;
        $this->data['numero_documento'] = $documento->numero;
        $this->data['tipo'] = $this->tipoDeducido;

        // Fuera de toda transacción, como el tipo de cambio. Hoy SinPadron: no hay nada que precargar.
        $padron = app(FuentePadronContribuyentes::class)->consultar($codigo, $documento->numero);

        if ($padron !== null) {
            $this->data['razon_social'] = $padron->razonSocial;
            $this->data['direccion_fiscal'] = $padron->direccionFiscal;
            $this->data['ubigeo_id'] = $padron->ubigeoId;
        }
    }

    public function registrar(): void
    {
        $usuario = Filament::auth()->user();

        if (! $usuario instanceof User) {
            throw new AuthorizationException;
        }

        $datos = $this->form->getState();

        try {
            $registro = app(EjecutorAcciones::class)->ejecutar('nucleo.parties.registrar', [
                'tipo_documento_id' => $datos['tipo_documento_id'] ?? null,
                'numero_documento' => $datos['numero_documento'] ?? null,
                'tipo' => $this->tipoDeducido === null ? ($datos['tipo'] ?? null) : null,
                'razon_social' => $datos['razon_social'] ?? null,
                'nombres' => $datos['nombres'] ?? null,
                'apellidos' => $datos['apellidos'] ?? null,
                'nombre_comercial' => $datos['nombre_comercial'] ?? null,
                'direccion_fiscal' => $datos['direccion_fiscal'] ?? null,
                'ubigeo_id' => $datos['ubigeo_id'] ?? null,
                'telefono' => $datos['telefono'] ?? null,
                'email' => $datos['email'] ?? null,
                'roles' => $datos['roles'] ?? [],
            ], $usuario);
        } catch (ErrorDeNegocio $e) {
            Notification::make()->danger()->title($e->getMessage())->send();

            return;
        } catch (ValidationException $e) {
            // Las claves del ejecutor no traen el prefijo del formulario (`email`, `roles.0`), y una regla que el
            // formulario no repite (un rol borrado entre pasos) no se vería: aviso y error bajo su campo.
            Notification::make()->danger()->title('No se registró nada.')->body($e->validator->errors()->first())->send();

            $porCampo = [];

            foreach ($e->errors() as $clave => $mensajes) {
                $porCampo['data.'.Str::before($clave, '.')] = $mensajes;
            }

            throw ValidationException::withMessages($porCampo);
        }

        if (! $registro instanceof PartyRegistrada) {
            return;
        }

        Notification::make()->success()->title('Registrada.')
            ->body($registro->pareja === null ? null : "Ojo: puede ser la misma persona que {$registro->pareja->etiqueta}.")
            ->send();

        $this->redirect(VerParty::getUrl(['record' => $registro->id]));
    }

    private function avisoPareja(): Callout
    {
        return Callout::make('Puede ser la misma persona')
            ->description(fn (): string => 'Ya está registrado el otro documento de esta persona: '.($this->pareja['etiqueta'] ?? '').'. Puedes registrarla igual.')
            ->warning()
            ->visible(fn (): bool => $this->pareja !== null)
            ->actions([
                Action::make('abrirPareja')->label('Ver la registrada')->openUrlInNewTab()
                    ->url(fn (): ?string => $this->pareja === null ? null : VerParty::getUrl(['record' => $this->pareja['id']])),
            ]);
    }

    private function accionActivarExistente(): Action
    {
        return AccionFilament::desde(
            'nucleo.parties.activar',
            fn (): array => ['party_id' => $this->existente['id'] ?? 0, 'version' => $this->existente['version'] ?? 0],
            function (): void {
                Notification::make()->success()->title('Activada.')->send();

                if ($this->existente !== null) {
                    $this->redirect(VerParty::getUrl(['record' => $this->existente['id']]));
                }
            },
        )
            ->label('Activarla')
            ->requiresConfirmation()
            ->hidden(fn (): bool => $this->existente === null || $this->existente['activa']);
    }

    private function tipoElegido(Get $get): ?TipoPersona
    {
        return TipoPersona::tryFrom((string) ($this->tipoDeducido ?? $get('tipo')));
    }

    private function olvidarRevision(): void
    {
        $this->existente = null;
        $this->pareja = null;
        $this->tipoDeducido = null;
    }
}
