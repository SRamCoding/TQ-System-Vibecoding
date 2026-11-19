<?php

declare(strict_types=1);

namespace App\Modules\Crm\Cuentas\Filament\Resources\Cuentas\Pages;

use App\Modules\Crm\Cuentas\Enums\CasoRuc;
use App\Modules\Crm\Cuentas\Filament\Resources\Cuentas\CuentaResource;
use App\Modules\Crm\Cuentas\Filament\SelectoresCuenta;
use App\Modules\Crm\Cuentas\Services\CuentaCreada;
use App\Modules\Crm\Cuentas\Services\CuentasService;
use App\Modules\Nucleo\Excepciones\ErrorDeNegocio;
use App\Modules\Nucleo\Filament\AccionFilament;
use App\Modules\Nucleo\Filament\Resources\Parties\Pages\VerParty;
use App\Modules\Nucleo\Parties\CoincidenciaNombre;
use App\Modules\Nucleo\RegistroAcciones\EjecutorAcciones;
use App\Modules\Nucleo\Services\PartiesService;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
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
use Livewire\Attributes\Url;

/**
 * Nueva cuenta en dos pasos (plan 2.20, D2): primero el RUC, para que no se repita ninguna; después, lo comercial. Los
 * cuatro casos los dice `CuentasService::revisarRuc`; se crea por la acción `crm.cuentas.crear`, que vuelve a comprobar
 * todo al guardar. Los campos de catálogo son los de la acción (`AccionFilament::campo`).
 *
 * @property-read Schema $form
 */
final class NuevaCuenta extends Page
{
    protected static string $resource = CuentaResource::class;

    protected static ?string $title = 'Nueva cuenta';

    /** @var array<string, mixed> */
    public ?array $data = [];

    /** El RUC puesto desde la pestaña «Cuenta comercial» de una ficha (D13). */
    #[Url(as: 'ruc')]
    public ?string $rucInicial = null;

    /**
     * Lo que dijo el primer paso: el caso, el RUC normalizado y, si existe, la ficha y la cuenta.
     *
     * @var ?array{caso: string, ruc: string, natural: bool, ficha: ?array{id: int, etiqueta: string}, cuenta: ?array{id: int, etiqueta: string, ejecutivo: string}, publica: bool}
     */
    public ?array $revision = null;

    public static function canAccess(array $parameters = []): bool
    {
        return CuentaResource::usuarioPuede('crm.cuentas.crear');
    }

    public function mount(): void
    {
        $usuario = CuentaResource::usuario();
        $this->form->fill([
            'ruc' => $this->rucInicial,
            // D7.3: por defecto, quien crea, si puede atender cuentas.
            'ejecutivo_user_id' => $usuario?->can(CuentasService::PERMISO_ATENDER) === true ? $usuario->id : null,
        ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])->id('form')->livewireSubmitHandler('crear'),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
            Wizard::make([
                Step::make('RUC')
                    ->description('Se empieza por el RUC: así no se repite ninguna cuenta.')
                    ->schema([
                        TextInput::make('ruc')->label('RUC')->required()->maxLength(20)->live(onBlur: true)
                            ->afterStateUpdated(fn () => $this->revision = null),
                        Callout::make('Esta cuenta ya existe')
                            ->description(fn (): string => ($this->revision['cuenta']['etiqueta'] ?? '').', que atiende '.($this->revision['cuenta']['ejecutivo'] ?? '').'.')
                            ->danger()->key('existe')
                            ->visible(fn (): bool => ($this->revision['caso'] ?? null) === CasoRuc::CuentaExiste->value)
                            ->actions([
                                Action::make('abrirExistente')->label('Abrir la existente')
                                    ->url(fn (): ?string => isset($this->revision['cuenta']) ? VerCuenta::getUrl(['record' => $this->revision['cuenta']['id']]) : null),
                            ]),
                        Callout::make('Esta ficha está inactiva')
                            ->description('Revisa sus datos antes de reactivarla en Personas y empresas: pudo desactivarse por un RUC equivocado.')
                            ->warning()->key('inactiva')
                            ->visible(fn (): bool => ($this->revision['caso'] ?? null) === CasoRuc::FichaInactiva->value)
                            ->actions([
                                Action::make('abrirFicha')->label('Abrir la ficha')
                                    ->visible(fn (): bool => CuentaResource::usuarioPuede('parties.ver'))
                                    ->url(fn (): ?string => isset($this->revision['ficha']) ? VerParty::getUrl(['record' => $this->revision['ficha']['id']]) : null),
                            ]),
                    ])
                    ->afterValidation(fn () => $this->revisarRuc()),
                Step::make('Datos comerciales')
                    ->schema([
                        // Caso 2: la ficha ya existe; sus datos se corrigen en Personas y empresas.
                        Text::make(fn (): string => 'Empresa: '.($this->revision['ficha']['etiqueta'] ?? ''))
                            ->visible(fn (): bool => $this->caso() === CasoRuc::FichaSinCuenta),
                        // Caso 4: sin padrón, el RUC en grande y su confirmación atrapan el tipeo que el dígito deja pasar.
                        Text::make(fn (): HtmlString => new HtmlString('<span style="font-size:1.75rem;font-family:monospace;letter-spacing:0.08em">'.e($this->revision['ruc'] ?? '').'</span>'))
                            ->visible(fn (): bool => $this->caso() === CasoRuc::FichaNueva),
                        Checkbox::make('ruc_confirmado')->label('Revisé el RUC: es el de esta empresa')->accepted()
                            ->visible(fn (): bool => $this->caso() === CasoRuc::FichaNueva)->dehydrated(false),
                        TextInput::make('razon_social')->label('Razón social')->maxLength(200)->live(onBlur: true)
                            ->required(fn (): bool => $this->caso() === CasoRuc::FichaNueva && ! $this->esNatural())
                            ->visible(fn (): bool => $this->caso() === CasoRuc::FichaNueva && ! $this->esNatural()),
                        TextInput::make('nombres')->label('Nombres')->maxLength(100)
                            ->required(fn (): bool => $this->caso() === CasoRuc::FichaNueva && $this->esNatural())
                            ->visible(fn (): bool => $this->caso() === CasoRuc::FichaNueva && $this->esNatural()),
                        TextInput::make('apellidos')->label('Apellidos')->maxLength(100)->live(onBlur: true)
                            ->required(fn (): bool => $this->caso() === CasoRuc::FichaNueva && $this->esNatural())
                            ->visible(fn (): bool => $this->caso() === CasoRuc::FichaNueva && $this->esNatural()),
                        TextInput::make('nombre_comercial')->label('Nombre comercial')->maxLength(200)->live(onBlur: true)
                            ->visible(fn (): bool => $this->caso() === CasoRuc::FichaNueva),
                        $this->avisoParecidos(),
                        AccionFilament::campo('crm.cuentas.crear', 'rubro_id')
                            ->visible(fn (): bool => $this->caso() === CasoRuc::FichaNueva),
                        Toggle::make('es_publica')->label('Pública (entidad del Estado)')->live()
                            ->helperText(fn (): ?string => ($this->revision['publica'] ?? false) ? 'La ficha ya es «Entidad pública».' : null)
                            ->disabled(fn (): bool => $this->revision['publica'] ?? false)->dehydrated(),
                        AccionFilament::campo('crm.cuentas.crear', 'nivel_gobierno_id')
                            ->required(fn (Get $get): bool => (bool) $get('es_publica'))
                            ->visible(fn (Get $get): bool => (bool) $get('es_publica')),
                        SelectoresCuenta::ejecutivo()->label('Ejecutivo')->required(),
                        AccionFilament::campo('crm.cuentas.crear', 'origen_comercial_id'),
                        AccionFilament::campo('crm.cuentas.crear', 'origen_detalle'),
                        Textarea::make('notas')->label('Notas')->maxLength(2000),
                    ]),
            ])->submitAction(new HtmlString(Blade::render('<x-filament::button type="submit">Crear</x-filament::button>'))),
        ]);
    }

    /**
     * Paso 1 → 2: valida el RUC y dice su caso. Si ya es cuenta o la ficha está inactiva, no se sigue.
     *
     * @throws Halt|ValidationException
     */
    public function revisarRuc(): void
    {
        try {
            $revision = app(CuentasService::class)->revisarRuc((string) $this->data['ruc']);
        } catch (ErrorDeNegocio $e) {
            throw ValidationException::withMessages(['data.ruc' => $e->getMessage()]);
        }

        $this->revision = [
            'caso' => $revision->caso->value,
            'ruc' => $revision->ruc,
            'natural' => $revision->esPersonaNatural,
            'ficha' => $revision->ficha === null ? null : ['id' => $revision->ficha->id, 'etiqueta' => $revision->ficha->etiqueta],
            'cuenta' => $revision->cuenta === null ? null : ['id' => $revision->cuenta->id, 'etiqueta' => $revision->cuenta->etiqueta, 'ejecutivo' => $revision->cuenta->ejecutivoNombre],
            'publica' => $revision->ficha !== null && app(PartiesService::class)->tieneRol($revision->ficha->id, CuentasService::ROL_ENTIDAD),
        ];
        $this->data['ruc'] = $revision->ruc;
        $this->data['es_publica'] = $this->revision['publica'];

        if (in_array($revision->caso, [CasoRuc::CuentaExiste, CasoRuc::FichaInactiva], true)) {
            throw new Halt;
        }
    }

    public function crear(): void
    {
        $usuario = CuentaResource::usuario() ?? throw new AuthorizationException;
        $datos = $this->form->getState();

        try {
            $creada = app(EjecutorAcciones::class)->ejecutar('crm.cuentas.crear', [
                'ruc' => $this->revision['ruc'] ?? ($datos['ruc'] ?? null),
                'es_publica' => (bool) ($datos['es_publica'] ?? false),
                'nivel_gobierno_id' => ($datos['es_publica'] ?? false) ? ($datos['nivel_gobierno_id'] ?? null) : null,
                'ejecutivo_user_id' => $datos['ejecutivo_user_id'] ?? null,
                'origen_comercial_id' => $datos['origen_comercial_id'] ?? null,
                'origen_detalle' => $datos['origen_detalle'] ?? null,
                'notas' => $datos['notas'] ?? null,
                'razon_social' => $datos['razon_social'] ?? null,
                'nombres' => $datos['nombres'] ?? null,
                'apellidos' => $datos['apellidos'] ?? null,
                'nombre_comercial' => $datos['nombre_comercial'] ?? null,
                'rubro_id' => $datos['rubro_id'] ?? null,
            ], $usuario);
        } catch (ErrorDeNegocio $e) {
            Notification::make()->danger()->title($e->getMessage())->send();

            return;
        } catch (ValidationException $e) {
            Notification::make()->danger()->title('No se creó nada.')->body($e->validator->errors()->first())->send();
            $porCampo = [];

            foreach ($e->errors() as $clave => $mensajes) {
                $porCampo['data.'.Str::before($clave, '.')] = $mensajes;
            }

            throw ValidationException::withMessages($porCampo);
        }

        if (! $creada instanceof CuentaCreada) {
            return;
        }

        Notification::make()->success()->title('Cuenta creada.')
            ->body($creada->posibleMismaPersona === null ? null : "Ojo: puede ser la misma persona que {$creada->posibleMismaPersona->etiqueta}.")
            ->send();
        $this->redirect(VerCuenta::getUrl(['record' => $creada->cuenta->id]));
    }

    /** D2: nombre igual o parecido al de otra ficha. Solo avisa, nunca bloquea. */
    private function avisoParecidos(): Callout
    {
        return Callout::make('Se parece a otras empresas registradas')
            ->description(fn (Get $get): HtmlString => new HtmlString(implode('<br>', array_map(
                fn (CoincidenciaNombre $c): string => e($c->party->etiqueta).' — '.e($c->aviso()).($c->party->activa ? '' : ' (inactiva)'),
                $this->parecidos($get),
            )).'<br>Si es otra empresa, sigue: esto solo avisa.'))
            ->warning()->key('parecidos')
            ->visible(fn (Get $get): bool => $this->caso() === CasoRuc::FichaNueva && $this->parecidos($get) !== []);
    }

    /**
     * @return list<CoincidenciaNombre>
     */
    private function parecidos(Get $get): array
    {
        $nombre = $this->esNatural() ? trim($get('apellidos').' '.$get('nombres')) : (string) $get('razon_social');
        $servicio = app(CuentasService::class);
        $porId = [];

        foreach ([...$servicio->parecidosPorNombre($nombre), ...$servicio->parecidosPorNombre((string) $get('nombre_comercial'))] as $coincidencia) {
            $porId[$coincidencia->party->id] ??= $coincidencia;
        }

        return array_values($porId);
    }

    private function caso(): ?CasoRuc
    {
        return CasoRuc::tryFrom((string) ($this->revision['caso'] ?? ''));
    }

    private function esNatural(): bool
    {
        return (bool) ($this->revision['natural'] ?? false);
    }
}
