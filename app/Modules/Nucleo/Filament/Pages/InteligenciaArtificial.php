<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Filament\Pages;

use App\Modules\Nucleo\Enums\AccionSugerenciaIa;
use App\Modules\Nucleo\Enums\EstadoSugerenciaIa;
use App\Modules\Nucleo\Excepciones\ErrorDeNegocio;
use App\Modules\Nucleo\Filament\AccionFilament;
use App\Modules\Nucleo\Filament\Clusters\Configuracion;
use App\Modules\Nucleo\Ia\ClientesModeloLenguaje;
use App\Modules\Nucleo\Ia\CostoLlamadaIa;
use App\Modules\Nucleo\Ia\DefinicionProcesoIa;
use App\Modules\Nucleo\Ia\RegistroProcesosIa;
use App\Modules\Nucleo\Models\IaLlamada;
use App\Modules\Nucleo\Models\IaModelo;
use App\Modules\Nucleo\Models\IaProceso;
use App\Modules\Nucleo\Models\IaProveedor;
use App\Modules\Nucleo\Models\IaSugerencia;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\EjecutorAcciones;
use App\Modules\Nucleo\Services\IaConfiguracionService;
use App\Modules\Nucleo\Services\IaService;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Url;

/**
 * Configuración → IA (plan 1.11, D11). Arriba, una caja por proceso de IA (hoy ninguno: lo dice); abajo, una por
 * proveedor y la de agregar uno. Al presionar una caja, su panel se desliza desde la derecha encima de las cajas, sin
 * recargar (`?abierto=proceso:<codigo>` o `proveedor:<id>`), como en Alertas y Catálogos.
 *
 * - Proceso: qué envía, la autorización de Gerencia, el gasto del mes contra su tope, las llamadas y el acierto
 *   (aceptadas y corregidas sobre las resueltas). El administrador elige modelos y lo enciende (`ia.configurar`);
 *   Gerencia autoriza lo que sale y fija el tope (`ia.autorizar`).
 * - Proveedor: sus datos y sus modelos, con su precio vigente; agregar un modelo, editarlo, ponerle precio y marcarlo
 *   por defecto.
 *
 * Todo pasa por las acciones registradas; la versión se captura al abrir el panel. Sin bandeja de sugerencias: cada
 * una se muestra en el formulario de la acción de su proceso.
 */
final class InteligenciaArtificial extends Page
{
    protected static ?string $cluster = Configuracion::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-sparkles';

    protected static ?string $navigationLabel = 'IA';

    protected static ?string $title = 'Inteligencia artificial';

    protected static ?string $slug = 'ia';

    protected static ?int $navigationSort = 15;

    public const string NUEVO_PROVEEDOR = 'proveedor:nuevo';

    /** El panel abierto: `proceso:<codigo>`, `proveedor:<id>` o `proveedor:nuevo`; null: las cajas. */
    #[Url]
    public ?string $abierto = null;

    /** @var array<string, mixed> */
    public ?array $proceso = [];

    /** @var array<string, mixed> */
    public ?array $autorizacion = [];

    /** @var array<string, mixed> */
    public ?array $proveedor = [];

    public static function canAccess(): bool
    {
        $usuario = Filament::auth()->user();

        return $usuario instanceof User && ($usuario->can('ia.configurar') || $usuario->can('ia.autorizar'));
    }

    public function mount(): void
    {
        if ($this->procesoAbierto() === null && $this->proveedorAbierto() === null && $this->abierto !== self::NUEVO_PROVEEDOR) {
            $this->abierto = null;

            return;
        }

        $this->llenar();
    }

    public function abrir(string $clave): void
    {
        $this->abierto = $clave;

        if ($this->procesoAbierto() === null && $this->proveedorAbierto() === null && $clave !== self::NUEVO_PROVEEDOR) {
            $this->abierto = null;

            return;
        }

        $this->llenar();
    }

    public function cerrar(): void
    {
        $anterior = $this->abierto;
        $this->abierto = null;
        $this->proceso = $this->autorizacion = $this->proveedor = [];

        if ($anterior !== null) {
            $this->js('document.querySelector('.json_encode('[data-ia="'.$anterior.'"]').')?.focus()');
        }
    }

    public function procesoAbierto(): ?IaProceso
    {
        if (! str_starts_with((string) $this->abierto, 'proceso:')) {
            return null;
        }

        $codigo = substr((string) $this->abierto, strlen('proceso:'));

        return array_key_exists($codigo, app(RegistroProcesosIa::class)->procesos()) ? IaProceso::query()->where('codigo', $codigo)->first() : null;
    }

    public function proveedorAbierto(): ?IaProveedor
    {
        $id = substr((string) $this->abierto, strlen('proveedor:'));

        return str_starts_with((string) $this->abierto, 'proveedor:') && ctype_digit($id) ? IaProveedor::query()->find((int) $id) : null;
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            View::make('nucleo::filament.ia.pantalla')->viewData(fn (): array => [
                'procesos' => $this->procesos(),
                'proveedores' => IaProveedor::query()->withCount('modelos')->orderBy('nombre')->get(),
                'procesoAbierto' => $this->procesoAbierto(),
                'detalle' => $this->detalleDelProceso(),
                'proveedorAbierto' => $this->proveedorAbierto(),
                'nuevoProveedor' => $this->abierto === self::NUEVO_PROVEEDOR,
                'modelos' => $this->modelosDelProveedor(),
                'puedeConfigurar' => $this->puede('ia.configurar'),
                'puedeAutorizar' => $this->puede('ia.autorizar'),
            ]),
        ]);
    }

    /** Lo que elige el administrador: modelos y encendido (`nucleo.ia-cambiar-proceso`). */
    public function procesoForm(Schema $schema): Schema
    {
        return $schema->statePath('proceso')->disabled(! $this->puede('ia.configurar'))->components([
            Select::make('modelo_id')->label('Modelo')->placeholder('El modelo por defecto')
                ->options(fn (mixed $state): array => $this->opcionesDeModelo($state))
                ->helperText('Solo modelos activos y con precio vigente.'),
            Select::make('modelo_respaldo_id')->label('Modelo de respaldo')->placeholder('Sin respaldo')
                ->options(fn (mixed $state): array => $this->opcionesDeModelo($state))
                ->helperText('Si el principal falla, se intenta con este (de otro proveedor, si el problema es el crédito o la clave).'),
            Toggle::make('esta_activo')->label('Encendido')
                ->helperText('Si envía datos fuera de la empresa, necesita la autorización vigente de Gerencia.'),
            Hidden::make('version'),
            Actions::make([Action::make('guardarProceso')->label('Guardar')->action(fn () => $this->guardarProceso())])
                ->visible($this->puede('ia.configurar')),
        ]);
    }

    /** Lo que decide Gerencia: autorizar lo que sale hoy y el tope del mes (`nucleo.ia-autorizar-proceso`). */
    public function autorizacionForm(Schema $schema): Schema
    {
        return $schema->statePath('autorizacion')->components([
            TextInput::make('tope_mensual_usd')->label('Tope del mes (USD)')->numeric()->minValue(0)->required()
                ->helperText('0 deja el proceso sin llamar. Al llegar al tope, sigue en manual hasta el mes siguiente.'),
            Hidden::make('version'),
            Actions::make([Action::make('autorizarProceso')->label('Autorizar lo que envía hoy')->action(fn () => $this->autorizarProceso())]),
        ]);
    }

    /** Los datos de un proveedor, o uno nuevo (`nucleo.ia-crear-proveedor`, `nucleo.ia-editar-proveedor`). */
    public function proveedorForm(Schema $schema): Schema
    {
        $nuevo = $this->abierto === self::NUEVO_PROVEEDOR;
        $implementaciones = app(ClientesModeloLenguaje::class)->codigos();

        return $schema->statePath('proveedor')->disabled(! $this->puede('ia.configurar'))->components([
            TextInput::make('codigo')->label('Código')->required()->maxLength(40)->visible($nuevo)
                ->helperText('En minúsculas y guion bajo: claude_api. No cambia después.'),
            TextInput::make('nombre')->label('Nombre')->required()->maxLength(120),
            Select::make('implementacion')->label('Implementación')->required()
                ->options(fn (mixed $state): array => array_combine([...$implementaciones, ...(is_string($state) && $state !== '' ? [$state] : [])], [...$implementaciones, ...(is_string($state) && $state !== '' ? [$state] : [])])),
            Toggle::make('es_externo')->label('Saca los datos de la empresa')
                ->helperText('Un servicio en internet (Claude API). Apagado: un modelo en un servidor propio.'),
            TextInput::make('variable_clave')->label('Variable de la clave')->maxLength(80)->placeholder('IA_CLAVE_CLAUDE')
                ->helperText('El nombre de la variable de entorno que TI llena con la clave, nunca la clave. Vacío: no necesita.'),
            TextInput::make('llamadas_en_paralelo')->label('Llamadas en paralelo')->integer()->minValue(1)->maxValue(20)->required(),
            Toggle::make('es_activo')->label('Activo')->visible(! $nuevo),
            Hidden::make('version'),
            Actions::make([Action::make('guardarProveedor')->label($nuevo ? 'Agregar proveedor' : 'Guardar')->action(fn () => $this->guardarProveedor())])
                ->visible($this->puede('ia.configurar')),
        ]);
    }

    public function guardarProceso(): void
    {
        $proceso = $this->procesoAbierto();
        $datos = $this->getSchema('procesoForm')?->getState() ?? [];

        if ($proceso === null) {
            return;
        }

        $this->ejecutar('nucleo.ia-cambiar-proceso', [
            'proceso_id' => $proceso->id,
            'esta_activo' => (bool) ($datos['esta_activo'] ?? false),
            'modelo_id' => $this->entero($datos['modelo_id'] ?? null),
            'modelo_respaldo_id' => $this->entero($datos['modelo_respaldo_id'] ?? null),
            'version' => (int) ($this->proceso['version'] ?? 0),
        ]);
    }

    public function autorizarProceso(): void
    {
        $proceso = $this->procesoAbierto();
        $datos = $this->getSchema('autorizacionForm')?->getState() ?? [];

        if ($proceso === null) {
            return;
        }

        $this->ejecutar('nucleo.ia-autorizar-proceso', [
            'proceso_id' => $proceso->id,
            'tope_mensual_usd' => (string) ($datos['tope_mensual_usd'] ?? ''),
            'version' => (int) ($this->autorizacion['version'] ?? 0),
        ]);
    }

    public function guardarProveedor(): void
    {
        $datos = $this->getSchema('proveedorForm')?->getState() ?? [];
        $variable = trim((string) ($datos['variable_clave'] ?? ''));
        $comunes = [
            'nombre' => (string) ($datos['nombre'] ?? ''),
            'implementacion' => (string) ($datos['implementacion'] ?? ''),
            'es_externo' => (bool) ($datos['es_externo'] ?? false),
            'variable_clave' => $variable === '' ? null : $variable,
            'llamadas_en_paralelo' => (int) ($datos['llamadas_en_paralelo'] ?? 0),
        ];

        if ($this->abierto === self::NUEVO_PROVEEDOR) {
            $id = $this->ejecutar('nucleo.ia-crear-proveedor', ['codigo' => (string) ($datos['codigo'] ?? ''), ...$comunes]);

            if (is_int($id)) {
                $this->abrir('proveedor:'.$id);
            }

            return;
        }

        $proveedor = $this->proveedorAbierto();

        if ($proveedor !== null) {
            $this->ejecutar('nucleo.ia-editar-proveedor', [
                'proveedor_id' => $proveedor->id,
                ...$comunes,
                'es_activo' => (bool) ($datos['es_activo'] ?? false),
                'version' => (int) ($this->proveedor['version'] ?? 0),
            ]);
        }
    }

    // --- Los modelos del proveedor abierto: acciones con su ventana ---

    public function nucleoIaCrearModeloAction(): Action
    {
        return AccionFilament::desde('nucleo.ia-crear-modelo', fn (): array => ['proveedor_id' => $this->proveedorAbierto()?->id], fn () => $this->llenar())
            ->label('Agregar modelo')
            ->icon('heroicon-o-plus');
    }

    public function nucleoIaEditarModeloAction(): Action
    {
        return AccionFilament::desde('nucleo.ia-editar-modelo', fn (array $arguments): array => ['modelo_id' => (int) ($arguments['modelo'] ?? 0)], fn () => $this->llenar())
            ->label('Editar')
            ->link()
            ->fillForm(fn (array $arguments): array => IaModelo::query()->find((int) ($arguments['modelo'] ?? 0))?->only(['nombre', 'max_tokens_salida', 'es_activo', 'version']) ?? []);
    }

    public function nucleoIaAgregarPrecioAction(): Action
    {
        return AccionFilament::desde('nucleo.ia-agregar-precio', fn (array $arguments): array => ['modelo_id' => (int) ($arguments['modelo'] ?? 0)], fn () => $this->llenar())
            ->label('Nuevo precio')
            ->link()
            ->fillForm(fn (): array => ['vigente_desde' => CarbonImmutable::now('America/Lima')->toDateString()]);
    }

    public function nucleoIaMarcarModeloPorDefectoAction(): Action
    {
        return AccionFilament::desde('nucleo.ia-marcar-modelo-por-defecto', fn (array $arguments): array => ['modelo_id' => (int) ($arguments['modelo'] ?? 0)], fn () => $this->llenar())
            ->label('Marcar por defecto')
            ->link()
            ->modalDescription('Los procesos sin modelo propio pasan a usarlo. Si es de otro proveedor externo, cae la autorización de los que envían datos fuera.')
            ->fillForm(fn (array $arguments): array => ['version' => IaModelo::query()->find((int) ($arguments['modelo'] ?? 0))?->version]);
    }

    /**
     * Configuración > IA > Listado; con un panel abierto, el nombre de lo abierto.
     */
    public function getBreadcrumbs(): array
    {
        $migas = [...Configuracion::unshiftClusterBreadcrumbs([]), self::getUrl() => 'IA'];
        $proceso = $this->procesoAbierto();
        $proveedor = $this->proveedorAbierto();
        $nombre = match (true) {
            $proceso !== null => $this->definicion($proceso)?->nombre,
            $proveedor !== null => $proveedor->nombre,
            $this->abierto === self::NUEVO_PROVEEDOR => 'Nuevo proveedor',
            default => null,
        };

        return [...$migas, $nombre ?? 'Listado'];
    }

    /** Lee lo de ahora del panel abierto, con su versión (bloqueo optimista: al abrir, no al guardar). */
    private function llenar(): void
    {
        $proceso = $this->procesoAbierto();

        if ($proceso !== null) {
            $this->getSchema('procesoForm')?->fill($proceso->only(['modelo_id', 'modelo_respaldo_id', 'esta_activo', 'version']));
            $this->getSchema('autorizacionForm')?->fill(['tope_mensual_usd' => (string) $proceso->tope_mensual_usd, 'version' => $proceso->version]);
        }

        $proveedor = $this->proveedorAbierto();

        if ($proveedor !== null) {
            $this->getSchema('proveedorForm')?->fill($proveedor->only(['nombre', 'implementacion', 'es_externo', 'variable_clave', 'llamadas_en_paralelo', 'es_activo', 'version']));
        } elseif ($this->abierto === self::NUEVO_PROVEEDOR) {
            $this->getSchema('proveedorForm')?->fill(['implementacion' => app(ClientesModeloLenguaje::class)->codigos()[0] ?? null, 'es_externo' => true, 'llamadas_en_paralelo' => 2]);
        }
    }

    /** @param array<string, mixed> $datos */
    private function ejecutar(string $clave, array $datos): mixed
    {
        $usuario = Filament::auth()->user();

        if (! $usuario instanceof User) {
            throw new AuthorizationException;
        }

        try {
            $resultado = app(EjecutorAcciones::class)->ejecutar($clave, $datos, $usuario);
        } catch (ErrorDeNegocio|ValidationException $e) {
            $mensaje = $e instanceof ValidationException ? (string) collect($e->errors())->flatten()->first() : $e->getMessage();
            Notification::make()->danger()->title($mensaje)->send();

            return null;
        }

        Notification::make()->success()->title('Guardado.')->send();
        $this->llenar();

        return $resultado;
    }

    private function puede(string $permiso): bool
    {
        return Filament::auth()->user()?->can($permiso) ?? false;
    }

    private function entero(mixed $valor): ?int
    {
        return is_numeric($valor) ? (int) $valor : null;
    }

    private function definicion(?IaProceso $proceso): ?DefinicionProcesoIa
    {
        return $proceso === null ? null : (app(RegistroProcesosIa::class)->procesos()[$proceso->codigo] ?? null);
    }

    /**
     * Los modelos que se pueden asignar (activos y con precio vigente), y el que ya tiene el proceso aunque ya no lo
     * sea: Filament rechaza un valor que no está entre las opciones.
     *
     * @return array<int, string>
     */
    private function opcionesDeModelo(mixed $actual): array
    {
        $hoy = CarbonImmutable::now();

        return IaModelo::query()->with('proveedor')->orderBy('nombre')->get()
            ->filter(fn (IaModelo $modelo): bool => ($modelo->es_activo && CostoLlamadaIa::precioVigente($modelo->id, $hoy) !== null) || (int) $actual === $modelo->id)
            ->mapWithKeys(fn (IaModelo $modelo): array => [$modelo->id => "{$modelo->proveedor->nombre} · {$modelo->nombre}"])
            ->all();
    }

    /**
     * Una caja por proceso registrado, con su estado.
     *
     * @return list<array{codigo: string, nombre: string, descripcion: string, encendido: bool, autorizado: bool, gasto: string, tope: string}>
     */
    private function procesos(): array
    {
        $filas = IaProceso::query()->get()->keyBy('codigo');
        $configuracion = app(IaConfiguracionService::class);
        $ia = app(IaService::class);
        $procesos = [];

        foreach (app(RegistroProcesosIa::class)->procesos() as $codigo => $definicion) {
            $fila = $filas[$codigo] ?? null;

            if (! $fila instanceof IaProceso) {
                continue;
            }

            $procesos[] = [
                'codigo' => $codigo,
                'nombre' => $definicion->nombre,
                'descripcion' => $definicion->descripcion,
                'encendido' => $fila->esta_activo,
                'autorizado' => $configuracion->autorizacionVigente($fila),
                'gasto' => $ia->gastoDelMes($fila),
                'tope' => (string) $fila->tope_mensual_usd,
            ];
        }

        return $procesos;
    }

    /**
     * Lo que muestra el panel de un proceso: qué envía, la autorización, el gasto del mes y el acierto.
     *
     * @return array<string, mixed>|null
     */
    private function detalleDelProceso(): ?array
    {
        $proceso = $this->procesoAbierto();
        $definicion = $this->definicion($proceso);

        if ($proceso === null || $definicion === null) {
            return null;
        }

        $inicio = CarbonImmutable::now('America/Lima')->startOfMonth();
        $cuentas = IaSugerencia::query()->where('proceso_id', $proceso->id)->where('estado', EstadoSugerenciaIa::Resuelta)
            ->selectRaw('count(*) as resueltas, count(*) filter (where accion <> ?) as usadas', [AccionSugerenciaIa::Rechazada->value])
            ->first();
        $resueltas = (int) ($cuentas->resueltas ?? 0);
        $usadas = (int) ($cuentas->usadas ?? 0);
        $modelo = $proceso->modelo_id === null ? IaModelo::query()->where('es_por_defecto', true)->first() : IaModelo::query()->find($proceso->modelo_id);

        return [
            'definicion' => $definicion,
            'autorizadoPor' => $proceso->autorizado_por === null ? null : User::query()->whereKey($proceso->autorizado_por)->value('name'),
            'autorizadoAt' => $proceso->autorizado_at,
            'vigente' => app(IaConfiguracionService::class)->autorizacionVigente($proceso),
            'enviaFuera' => $proceso->envia_datos_externos,
            'gasto' => app(IaService::class)->gastoDelMes($proceso),
            'tope' => (string) $proceso->tope_mensual_usd,
            'llamadas' => IaLlamada::query()->where('proceso_id', $proceso->id)->where('created_at', '>=', $inicio)->count(),
            'resueltas' => $resueltas,
            'acierto' => $resueltas === 0 ? null : (int) round(100 * $usadas / $resueltas),
            'modelo' => $modelo === null ? null : "{$modelo->proveedor->nombre} · {$modelo->nombre}".($proceso->modelo_id === null ? ' (por defecto)' : ''),
        ];
    }

    /**
     * Los modelos del proveedor abierto, con su precio vigente.
     *
     * @return list<array{modelo: IaModelo, precio: ?string}>
     */
    private function modelosDelProveedor(): array
    {
        $proveedor = $this->proveedorAbierto();

        if ($proveedor === null) {
            return [];
        }

        $hoy = CarbonImmutable::now();

        return $proveedor->modelos()->orderBy('nombre')->get()
            ->map(function (IaModelo $modelo) use ($hoy): array {
                $precio = CostoLlamadaIa::precioVigente($modelo->id, $hoy);

                return ['modelo' => $modelo, 'precio' => $precio === null ? null : rtrim(rtrim($precio->usd_millon_entrada, '0'), '.').' / '.rtrim(rtrim($precio->usd_millon_salida, '0'), '.').' USD por millón'];
            })
            ->values()
            ->all();
    }
}
