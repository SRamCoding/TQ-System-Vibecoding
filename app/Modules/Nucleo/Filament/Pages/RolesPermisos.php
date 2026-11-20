<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Filament\Pages;

use App\Modules\Nucleo\Excepciones\CruceSinControl;
use App\Modules\Nucleo\Excepciones\ErrorDeNegocio;
use App\Modules\Nucleo\Filament\AccionFilament;
use App\Modules\Nucleo\Filament\Clusters\Seguridad;
use App\Modules\Nucleo\Filament\SelectoresSeguridad;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\EjecutorAcciones;
use App\Modules\Nucleo\Seguridad\PermisosLegibles;
use App\Modules\Nucleo\Services\SeguridadService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Usuarios y organigrama → Roles y permisos (plan 1.4c, D2 y D3): una caja por rol, y debajo la grilla de solo lectura
 * de roles × permisos, con los datos sensibles primero. Al presionar un rol, sus permisos se abren en un panel que se
 * desliza desde la derecha (`?rol=…` lo abre directo). Un guardado es un solo cambio, por
 * `nucleo.seguridad.cambiar-permisos-de-rol`; si abre cruces de funciones, el panel pide ahí mismo su control.
 *
 * @property-read Schema $form
 */
final class RolesPermisos extends Page
{
    public const PERMISO = 'roles.gestionar';

    protected static ?string $cluster = Seguridad::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $navigationLabel = 'Roles y permisos';

    protected static ?string $title = 'Roles y permisos';

    protected static ?string $slug = 'roles';

    protected static ?int $navigationSort = 3;

    /** El rol abierto en el panel, o null: las cajas. Texto: un `?rol=abc` en la URL no debe tumbar la pantalla. */
    #[Url]
    public ?string $rol = null;

    /** @var array<string, mixed> */
    public ?array $data = [];

    /**
     * Los permisos que tenía el rol al abrir. El rol no lleva versión: sin esto, dos personas con el mismo panel abierto
     * se pisarían sin saberlo (plan 1.4c, D7).
     *
     * @var list<int>
     */
    #[Locked]
    public array $leidos = [];

    /**
     * Lo que frenó el último guardado: mientras no esté vacío, el panel pide el control.
     *
     * @var list<array{usuario: string, permisos: string}>
     */
    #[Locked]
    public array $cruces = [];

    /**
     * Los permisos marcados cuando el guardado se frenó. El control solo vale para esos cruces, los que la persona vio: si
     * después cambia lo marcado, se revisa de nuevo sin control, para que nadie firme un cruce que no se le mostró.
     *
     * @var list<int>
     */
    #[Locked]
    public array $frenadoCon = [];

    /**
     * Leídos una vez por pedido: privados, Livewire no los guarda.
     *
     * @var ?list<array{nombre: string, permisos: array<int, string>}>
     */
    private ?array $grupos = null;

    /** @var ?array<int, list<int>> */
    private ?array $titulares = null;

    public static function canAccess(): bool
    {
        $usuario = Filament::auth()->user();

        return $usuario instanceof User && $usuario->can(self::PERMISO);
    }

    public function mount(): void
    {
        if ($this->abierto() === null) {
            $this->rol = null;

            return;
        }

        $this->llenar();
    }

    /** Abre el panel de un rol sin recargar la página y lee sus permisos de ahora. */
    public function abrir(int $rol): void
    {
        $this->rol = (string) $rol;

        if ($this->abierto() === null) {
            $this->rol = null;

            return;
        }

        $this->llenar();
    }

    /** Cierra el panel (la vista ya lo deslizó afuera) y devuelve el foco a la caja del rol que estaba abierto. */
    public function cerrar(): void
    {
        $anterior = $this->rol;
        $this->rol = null;
        $this->data = [];
        $this->cruces = [];
        $this->frenadoCon = [];

        if ($anterior !== null) {
            $this->js('document.querySelector('.json_encode('[data-rol="'.$anterior.'"]').')?.focus()');
        }
    }

    public function abierto(): ?Role
    {
        return ctype_digit((string) $this->rol) ? Role::query()->find((int) $this->rol) : null;
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            View::make('nucleo::filament.roles.pantalla')->viewData(fn (): array => [
                'roles' => $this->roles(),
                'grupos' => $this->grupos(),
                'marcados' => $this->marcados(),
                'abierto' => $this->abierto(),
                'bloqueo' => $this->abierto() === null ? null : $this->motivoBloqueo($this->abierto()),
            ]),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        $rol = $this->abierto();

        if ($rol === null) {
            return $schema->statePath('data')->components([]);
        }

        $editable = $this->motivoBloqueo($rol) === null;
        $listas = collect($this->grupos())->map(fn (array $grupo, int $i): CheckboxList => CheckboxList::make("grupo_{$i}")
            ->label($grupo['nombre'])
            ->options($grupo['permisos'])
            ->columns(2)
            ->bulkToggleable($editable)
            ->disabled(! $editable))->all();

        return $schema->statePath('data')->components([
            ...$listas,
            Section::make('Control compensatorio')
                ->visible(fn (): bool => $this->cruces !== [])
                ->schema([
                    View::make('nucleo::filament.roles.cruces-frenados')->viewData(fn (): array => ['cruces' => $this->cruces]),
                    Textarea::make('control')->label('Control compensatorio')->rows(3)->maxLength(2000)->markAsRequired(),
                    SelectoresSeguridad::usuarioActivo('responsable_user_id')->label('Responsable del control')->markAsRequired(),
                    DatePicker::make('control_vigente_hasta')->label('Control vigente hasta')->format('Y-m-d'),
                ]),
            Actions::make([Action::make('guardar')->label('Guardar')->action(fn () => $this->guardar())])->visible($editable),
        ]);
    }

    public function guardar(): void
    {
        $usuario = Filament::auth()->user();
        $rol = $this->abierto();

        if (! $usuario instanceof User || $rol === null) {
            throw new AuthorizationException;
        }

        $datos = $this->form->getState();

        if ($this->permisosDe($rol) !== $this->leidos) {
            Notification::make()->warning()->title('Alguien más cambió este rol mientras lo tenías abierto. Revisa sus permisos de ahora y vuelve a guardar.')->send();
            $this->llenar();

            return;
        }

        $permisos = collect($datos)->filter(fn (mixed $_, string $campo): bool => str_starts_with($campo, 'grupo_'))
            ->flatten()->map(fn (mixed $id): int => (int) $id)->unique()->sort()->values()->all();
        $conControl = $this->cruces !== [] && $permisos === $this->frenadoCon;

        try {
            app(EjecutorAcciones::class)->ejecutar('nucleo.seguridad.cambiar-permisos-de-rol', [
                'rol_id' => (int) $rol->getKey(),
                'permisos' => $permisos,
                'control' => $conControl ? ($datos['control'] ?? null) : null,
                'responsable_user_id' => $conControl ? ($datos['responsable_user_id'] ?? null) : null,
                'control_vigente_hasta' => $conControl ? ($datos['control_vigente_hasta'] ?? null) : null,
            ], $usuario);
        } catch (CruceSinControl $e) {
            $this->cruces = array_map(fn (array $cruce): array => [
                'usuario' => $cruce['usuario'],
                'permisos' => collect(explode(' × ', $cruce['permisos']))->map(PermisosLegibles::etiqueta(...))->implode(' × '),
            ], $e->faltantes);
            $this->frenadoCon = $permisos;
            Notification::make()->warning()->title('Falta el control compensatorio.')->send();

            return;
        } catch (ErrorDeNegocio|ValidationException $e) {
            $mensaje = $e instanceof ValidationException ? (string) collect($e->errors())->flatten()->first() : $e->getMessage();
            Notification::make()->danger()->title($mensaje)->send();

            return;
        }

        Notification::make()->success()->title('Guardado.')->send();
        $this->llenar();
    }

    /** El nombre de `administrador` y de `gerencia general` no cambia: el sistema los busca por él. */
    public function renombrarAction(): Action
    {
        return AccionFilament::desde(
            'nucleo.seguridad.renombrar-rol',
            fn (): array => ['rol_id' => (int) $this->abierto()?->getKey()],
            function (): void {
                Notification::make()->success()->title('Guardado.')->send();
            },
        )
            ->name('renombrar')->label('Renombrar')->icon('heroicon-o-pencil-square')->color('gray')->outlined()->size('sm')
            ->fillForm(fn (): array => ['nombre' => $this->abierto()?->name])
            ->hidden(fn (): bool => in_array($this->abierto()?->name, [SeguridadService::ROL_ADMINISTRADOR, SeguridadService::ROL_GERENCIA_GENERAL], true));
    }

    /** Un rol nuevo nace sin permisos y se abre su panel para marcárselos. */
    protected function getHeaderActions(): array
    {
        return [
            AccionFilament::desde('nucleo.seguridad.crear-rol', alTerminar: fn (mixed $rolId) => $this->abrir((int) $rolId))
                ->label('Crear rol')->icon('heroicon-o-plus')
                ->modalDescription('Nace sin permisos. Al crearlo se abre para que le marques los suyos.'),
        ];
    }

    /**
     * Usuarios y organigrama > Roles y permisos > Listado; con un rol abierto, su nombre en vez de «Listado».
     */
    public function getBreadcrumbs(): array
    {
        $migas = [...Seguridad::unshiftClusterBreadcrumbs([]), self::getUrl() => 'Roles y permisos'];

        return [...$migas, $this->abierto() === null ? 'Listado' : Str::ucfirst($this->abierto()->name)];
    }

    /** Por qué este rol no se puede editar, o null. Lo mismo que rechazaría el servicio (plan 1.4c, D3). */
    private function motivoBloqueo(Role $rol): ?string
    {
        if ($rol->name === SeguridadService::ROL_ADMINISTRADOR) {
            return 'Los permisos de administrador vienen con el sistema y no se cambian.';
        }

        if (in_array(Filament::auth()->id(), $this->titulares()[(int) $rol->getKey()] ?? [], true)) {
            return 'Tienes este rol: nadie cambia los permisos de un rol suyo. Se lo puedes pedir a otro administrador.';
        }

        return null;
    }

    /**
     * @return list<array{id: int, nombre: string, permisos: int, personas: int}>
     */
    private function roles(): array
    {
        $titulares = $this->titulares();

        return Role::query()->withCount('permissions')->orderBy('name')->get()
            ->map(fn (Role $rol): array => [
                'id' => (int) $rol->getKey(),
                'nombre' => Str::ucfirst($rol->name),
                'permisos' => (int) $rol->getAttribute('permissions_count'),
                'personas' => count($titulares[(int) $rol->getKey()] ?? []),
            ])
            ->all();
    }

    /**
     * @return list<array{nombre: string, permisos: array<int, string>}>
     */
    private function grupos(): array
    {
        if ($this->grupos === null) {
            /** @var array<int, string> $permisos */
            $permisos = Permission::query()->pluck('name', 'id')->all();
            $this->grupos = PermisosLegibles::agrupar($permisos);
        }

        return $this->grupos;
    }

    /**
     * @return array<int, list<int>> rol => sus cuentas
     */
    private function titulares(): array
    {
        return $this->titulares ??= app(SeguridadService::class)->titularesPorRol();
    }

    /**
     * Las casillas de la grilla: `"<rol>:<permiso>"`, de una sola consulta.
     *
     * @return array<string, bool>
     */
    private function marcados(): array
    {
        return DB::table('role_has_permissions')->get(['role_id', 'permission_id'])
            ->mapWithKeys(fn (object $fila): array => ["{$fila->role_id}:{$fila->permission_id}" => true])
            ->all();
    }

    /** Lee los permisos de ahora del rol abierto. */
    private function llenar(): void
    {
        $rol = $this->abierto();

        if ($rol === null) {
            return;
        }

        $this->leidos = $this->permisosDe($rol);
        $this->cruces = [];
        $this->frenadoCon = [];
        $llenado = [];

        foreach ($this->grupos() as $i => $grupo) {
            $llenado["grupo_{$i}"] = array_values(array_intersect(array_keys($grupo['permisos']), $this->leidos));
        }

        $this->form->fill($llenado);
    }

    /**
     * @return list<int>
     */
    private function permisosDe(Role $rol): array
    {
        return DB::table('role_has_permissions')->where('role_id', $rol->getKey())->orderBy('permission_id')
            ->pluck('permission_id')->map(fn (mixed $id): int => (int) $id)->all();
    }
}
