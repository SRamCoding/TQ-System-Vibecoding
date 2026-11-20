<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Filament;

use App\Modules\Nucleo\Excepciones\ErrorDeNegocio;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\Accion;
use App\Modules\Nucleo\RegistroAcciones\Campo;
use App\Modules\Nucleo\RegistroAcciones\EjecutorAcciones;
use App\Modules\Nucleo\RegistroAcciones\RegistroAcciones;
use App\Modules\Nucleo\RegistroAcciones\TipoCampo;
use App\Modules\Nucleo\Services\CatalogosService;
use App\Modules\Nucleo\Services\MotivosService;
use App\Modules\Nucleo\Services\ParametrosService;
use App\Modules\Nucleo\Services\SolicitudesPermisoService;
use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Dibuja una acción registrada en Filament: formulario desde sus campos y ejecución por
 * EjecutorAcciones. Solo presentación; las reglas están en la acción y su servicio.
 */
final class AccionFilament
{
    /**
     * @param  Accion|string  $accion  la acción o su clave registrada
     * @param  ?Closure  $datosFijos  valores que no pregunta el formulario; Filament le inyecta lo que
     *                                pida por nombre (`$record` en la acción de una fila)
     * @param  ?Closure(mixed): void  $alTerminar  recibe lo que devolvió la acción (para avisar el resultado)
     * @param  ?Closure  $soloLectura  nombres de los campos que se muestran sin poder cambiarse (con inyección,
     *                                 como $datosFijos). Se envían igual, con el valor que se llenó al abrir: el
     *                                 servicio sigue siendo quien rechaza un cambio
     * @param  ?Closure  $ocultos  nombres de los campos que no se muestran (con inyección): llegan vacíos a la
     *                             acción, por ejemplo los de una persona jurídica al editar una natural
     * @param  array<string, Closure(): Field>  $controles  otro control para un campo, solo de presentación: un
     *                                                      selector con buscador para un id de otra tabla (el banco,
     *                                                      un contacto). Etiqueta, obligatoriedad y reglas siguen
     *                                                      saliendo del campo, y el ejecutor valida igual (plan 1.3b)
     * @param  list<Closure(): Field>  $deApoyo  campos solo de presentación que no son de la acción: nunca llegan a ella
     *                                           (`dehydrated(false)`), pero un control los lee con `$get`. Por ejemplo,
     *                                           lo que se mostró al abrir la ventana (plan 1.4f, «Elegir módulos»)
     */
    /** Si la persona tiene el permiso de la acción (o la acción no pide ninguno). */
    public static function tiene(Accion $accion): bool
    {
        return $accion->permiso() === null || (Filament::auth()->user()?->can($accion->permiso()) ?? false);
    }

    public static function desde(Accion|string $accion, ?Closure $datosFijos = null, ?Closure $alTerminar = null, ?Closure $soloLectura = null, ?Closure $ocultos = null, array $controles = [], array $deApoyo = []): Action
    {
        $accion = is_string($accion) ? app(RegistroAcciones::class)->obtener($accion) : $accion;

        /** @return array<string, mixed> */
        $fijos = fn (Action $action): array => $datosFijos === null ? [] : (array) $action->evaluate($datosFijos);

        return Action::make(Str::camel(str_replace('.', '-', $accion->clave())))
            ->label($accion->nombre())
            // MVP rápido (op-solicitudes-permiso, plan 2.0b): sin el permiso, el botón se ve bloqueado (gris, candado) y dice
            // qué falta; al tocarlo se pide con un clic. Quien decide sigue siendo EjecutorAcciones.
            ->color(fn (): ?string => self::tiene($accion) ? null : 'gray')
            ->tooltip(fn (): ?string => self::tiene($accion) ? null : 'Te falta el permiso «'.app(SolicitudesPermisoService::class)->nombre((string) $accion->permiso()).'». Tócalo para pedirlo.')
            ->extraAttributes(fn (): array => self::tiene($accion) ? [] : ['class' => 'opacity-60'])
            ->schema(function (Action $action) use ($accion, $fijos, $soloLectura, $ocultos, $controles, $deApoyo): array {
                if (! self::tiene($accion)) {
                    return [
                        Text::make('Para «'.$accion->nombre().'» te falta el permiso «'.app(SolicitudesPermisoService::class)->nombre((string) $accion->permiso()).'». Quien administra Roles y permisos recibe tu pedido y te lo da por rol.'),
                        Textarea::make('tq_motivo_solicitud')->label('¿Para qué lo necesitas? (opcional)')->rows(2)->maxLength(300),
                    ];
                }

                $bloqueados = $soloLectura === null ? [] : (array) $action->evaluate($soloLectura);
                $sinMostrar = $ocultos === null ? [] : (array) $action->evaluate($ocultos);
                $apoyo = array_map(fn (Closure $campo): Field => $campo()->dehydrated(false), $deApoyo);

                return [...$apoyo, ...array_map(
                    fn (Campo $campo): Field => in_array($campo->nombre, $bloqueados, true)
                        ? self::componente($campo, $controles[$campo->nombre] ?? null, $accion)->disabled()->dehydrated()
                        : self::componente($campo, $controles[$campo->nombre] ?? null, $accion),
                    array_values(array_filter(
                        $accion->campos(),
                        fn (Campo $campo): bool => ! array_key_exists($campo->nombre, $fijos($action)) && ! in_array($campo->nombre, $sinMostrar, true),
                    )),
                )];
            })
            ->action(function (Action $action, array $data) use ($accion, $fijos, $alTerminar): void {
                $usuario = Filament::auth()->user();

                if (! $usuario instanceof User) {
                    throw new AuthorizationException;
                }

                if (! self::tiene($accion)) {
                    try {
                        app(SolicitudesPermisoService::class)->solicitar($usuario, (string) $accion->permiso(), $accion->nombre(), $data['tq_motivo_solicitud'] ?? null);
                        Notification::make()->success()->title('Permiso pedido: te avisarán cuando lo resuelvan.')->send();
                    } catch (\RuntimeException $e) {
                        Notification::make()->warning()->title($e->getMessage())->send();
                    }

                    return;
                }

                // Desde la pantalla, un campo de archivo solo vale si es el archivo que subió la persona: si el navegador lo
                // cambia por una ruta del servidor, se rechaza (plan 1.8; la ruta es solo para quien llama sin interfaz).
                foreach ($accion->campos() as $campo) {
                    // Un FileUpload de un solo archivo puede llegar como lista: vacía es «sin archivo» (un campo
                    // opcional que no se llenó, plan 2.21d); con uno, ese archivo.
                    if ($campo->tipo === TipoCampo::Archivo && is_array($data[$campo->nombre] ?? null) && count($data[$campo->nombre]) <= 1) {
                        $data[$campo->nombre] = array_values($data[$campo->nombre])[0] ?? null;
                    }

                    if ($campo->tipo === TipoCampo::Archivo && array_key_exists($campo->nombre, $data) && $data[$campo->nombre] !== null && ! $data[$campo->nombre] instanceof UploadedFile) {
                        Notification::make()->danger()->title('Vuelve a elegir el archivo.')->send();
                        $action->halt();

                        return;
                    }
                }

                try {
                    $resultado = app(EjecutorAcciones::class)->ejecutar($accion, [...$data, ...$fijos($action)], $usuario);
                } catch (ErrorDeNegocio $e) {
                    Notification::make()->danger()->title($e->getMessage())->send();
                    $action->halt();

                    return;
                }

                if ($alTerminar !== null) {
                    $alTerminar($resultado);
                }
            });
    }

    /**
     * El control de un campo de una acción, el mismo que dibuja su formulario (plan 2.20): para una página que llama a
     * la acción sin ser su ventana (un alta en pasos). Así un selector de catálogo es siempre el común
     * (`Campo::elementoDe`) y recibe las mejoras de los selectores de catálogo (tarea 2.0) sin tocar la página.
     */
    public static function campo(Accion|string $accion, string $nombre): Field
    {
        $accion = is_string($accion) ? app(RegistroAcciones::class)->obtener($accion) : $accion;

        foreach ($accion->campos() as $campo) {
            if ($campo->nombre === $nombre) {
                return self::componente($campo, accion: $accion);
            }
        }

        throw new LogicException("La acción «{$accion->clave()}» no tiene el campo «{$nombre}».");
    }

    /**
     * @param  ?Closure(): Field  $control
     */
    private static function componente(Campo $campo, ?Closure $control = null, ?Accion $accion = null): Field
    {
        if ($campo->control) {
            return Hidden::make($campo->nombre)->required($campo->obligatorio)->rules($campo->reglas);
        }

        $componente = $control !== null ? $control() : match ($campo->tipo) {
            TipoCampo::Texto => TextInput::make($campo->nombre),
            TipoCampo::Entero => TextInput::make($campo->nombre)->integer(),
            TipoCampo::Decimal => TextInput::make($campo->nombre)->numeric(),
            TipoCampo::Fecha => DatePicker::make($campo->nombre)->format('Y-m-d'),
            TipoCampo::Booleano => Toggle::make($campo->nombre),
            TipoCampo::Opcion => Select::make($campo->nombre)->options(self::opciones($campo)),
            // Plan 2.0 (D1 y D4): el selector común, que busca en la base y trae el valor actual aunque esté desactivado.
            TipoCampo::ElementoCatalogo => SelectorCatalogo::de($campo->catalogo ?? throw new LogicException("El campo {$campo->nombre} no dice de qué catálogo elige."), $campo->nombre)
                // Con detalle de «Otro» (plan 2.0, D5), vuelve a dibujar el formulario al cambiar.
                ->live($campo->campoDetalle !== null),
            TipoCampo::ListaCatalogo => SelectorCatalogo::de($campo->catalogo ?? throw new LogicException("El campo {$campo->nombre} no dice de qué catálogo elige."), $campo->nombre, varios: true),
            TipoCampo::ListaIds => Select::make($campo->nombre)->multiple()->searchable()
                ->options(fn (): array => DB::table($campo->tabla ?? throw new LogicException("El campo {$campo->nombre} no dice de qué tabla elige."))
                    ->orderBy((string) $campo->columnaEtiqueta)
                    ->pluck((string) $campo->columnaEtiqueta, 'id')
                    ->all()),
            // El motivo vuelve a dibujar el formulario al cambiar: su detalle aparece solo con «Otro».
            TipoCampo::Motivo => Select::make($campo->nombre)->live()
                ->options(fn (mixed $state): array => app(MotivosService::class)->opciones(
                    $campo->familiaMotivos ?? throw new LogicException("El campo {$campo->nombre} no dice de qué lista de motivos elige."),
                    is_numeric($state) ? (int) $state : null,
                )),
            TipoCampo::ListaTextos => TagsInput::make($campo->nombre),
            // El archivo queda en el temporal de Livewire y llega a la acción tal cual: lo guarda el servicio.
            TipoCampo::Archivo => FileUpload::make($campo->nombre)->storeFiles(false),
        };

        $componente = $componente->label($campo->etiqueta)->rules($campo->reglas);

        // Un control propio de la pantalla para un campo opcional conserva su `required()` (por ejemplo, obligatorio solo
        // si se marca otro: plan 2.21d, D8); un campo obligatorio de la acción siempre lo es.
        if ($control === null || $campo->obligatorio) {
            $componente->required($campo->obligatorio && $campo->tipo !== TipoCampo::Booleano);
        }

        $motivo = $accion === null ? null : self::motivoDelDetalle($accion, $campo->nombre);

        if ($motivo === null) {
            return $componente;
        }

        // El detalle de un motivo o de un catálogo con «Otro» (plan 2.0, D5): solo con «Otro», y entonces obligatorio
        // con su mínimo. Oculto no se envía, y el servicio rechaza un detalle sin «Otro».
        $conOtro = $motivo->tipo === TipoCampo::ElementoCatalogo && $motivo->catalogo !== null
            ? fn (Get $get): bool => app(CatalogosService::class)->esOtro($motivo->catalogo, $get($motivo->nombre))
            : fn (Get $get): bool => app(MotivosService::class)->esOtro($get($motivo->nombre));

        $componente->visible($conOtro)->required($conOtro);

        return $componente instanceof TextInput
            ? $componente->minLength(fn (): int => app(ParametrosService::class)->entero('motivos.minimo_detalle_otro'))
            : $componente;
    }

    /**
     * El campo (un motivo, o un catálogo con «Otro») de la acción que pide este campo como su detalle; nulo si no es el
     * detalle de ninguno.
     */
    private static function motivoDelDetalle(Accion $accion, string $nombre): ?Campo
    {
        foreach ($accion->campos() as $campo) {
            if (in_array($campo->tipo, [TipoCampo::Motivo, TipoCampo::ElementoCatalogo], true) && $campo->campoDetalle === $nombre) {
                return $campo;
            }
        }

        return null;
    }

    /**
     * @return array<string, string>
     */
    private static function opciones(Campo $campo): array
    {
        /** @var class-string<BackedEnum> $enum */
        $enum = $campo->enum;
        $opciones = [];

        foreach ($enum::cases() as $caso) {
            $opciones[(string) $caso->value] = method_exists($caso, 'etiqueta') ? $caso->etiqueta() : $caso->name;
        }

        return $opciones;
    }
}
