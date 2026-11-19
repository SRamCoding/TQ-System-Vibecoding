<?php

declare(strict_types=1);

use App\Modules\Nucleo\Enums\Catalogo;
use App\Modules\Nucleo\Events\ControlCruceRegistrado;
use App\Modules\Nucleo\Events\CruceFuncionesCerrado;
use App\Modules\Nucleo\Events\CruceFuncionesRegistrado;
use App\Modules\Nucleo\Events\PermisosDeRolCambiados;
use App\Modules\Nucleo\Events\RolAsignado;
use App\Modules\Nucleo\Events\RolQuitado;
use App\Modules\Nucleo\Excepciones\CruceSinControl;
use App\Modules\Nucleo\Excepciones\PartyRechazada;
use App\Modules\Nucleo\Excepciones\SeguridadRechazada;
use App\Modules\Nucleo\Models\AuditoriaCambio;
use App\Modules\Nucleo\Models\ControlCruce;
use App\Modules\Nucleo\Models\CruceFunciones;
use App\Modules\Nucleo\Models\Party;
use App\Modules\Nucleo\Models\PermisoPorAmbito;
use App\Modules\Nucleo\Models\Puesto;
use App\Modules\Nucleo\Models\TipoDocumentoIdentidad;
use App\Modules\Nucleo\Models\UnidadOrganizativa;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Seguridad\DatosControl;
use App\Modules\Nucleo\Seguridad\DatosUsuario;
use App\Modules\Nucleo\Services\PartiesService;
use App\Modules\Nucleo\Services\SeguridadService;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Login;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/*
 * Reglas de seguridad (plan 1.4): permisos efectivos, roles por ámbito, separación de funciones y quién puede
 * cambiar qué. `pagos.registrar` × `pagos.aprobar` son permisos de prueba: los de verdad llegan con la etapa 3.
 * Personas y roles inventados: no dicen quién hace qué en Techquk. La grilla real de roles es un pendiente con
 * Gerencia (D3).
 */

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-03-10 10:00', 'America/Lima'));
});

function seguridad(): SeguridadService
{
    return app(SeguridadService::class);
}

/** Un rol nuevo con estos permisos (los crea si faltan). */
function rolCon(string $nombre, string ...$permisos): Role
{
    $rol = Role::findOrCreate($nombre);
    $rol->syncPermissions(array_map(fn (string $p): Permission => Permission::findOrCreate($p), $permisos));

    return $rol;
}

/** Quien administra en la prueba: por un rol que puede gestionar usuarios y roles. */
function administrador(): User
{
    return User::factory()->create()->assignRole(rolCon('gestor-de-prueba', 'usuarios.gestionar', 'roles.gestionar'));
}

function parPagos(): int
{
    return seguridad()->registrarIncompatibilidad(
        Permission::findOrCreate('pagos.registrar')->id,
        Permission::findOrCreate('pagos.aprobar')->id,
        'Quien registra un pago no lo aprueba.',
    );
}

function controlDe(User $responsable, ?string $hasta = null): DatosControl
{
    return new DatosControl('Gerencia revisa cada semana los pagos que Tesorería registró y aprobó.', $responsable->id, $hasta);
}

function unidadDePrueba(): UnidadOrganizativa
{
    return UnidadOrganizativa::factory()->create();
}

// --- Escenario de aceptación (parte 8, fila «Roles») ---

test('parte 8: dar a quien registra pagos el rol de aprobarlos exige el control compensatorio', function () {
    Event::fake([CruceFuncionesRegistrado::class, ControlCruceRegistrado::class, RolAsignado::class]);
    $admin = administrador();
    $gerente = User::factory()->create();
    $persona = User::factory()->create()->assignRole(rolCon('tesoreria-registra', 'pagos.registrar'));
    $aprueba = rolCon('tesoreria-aprueba', 'pagos.aprobar');
    $par = parPagos();

    expect(fn () => seguridad()->asignarRol($persona->id, $aprueba->id, null, $admin))
        ->toThrow(CruceSinControl::class, 'pagos.aprobar × pagos.registrar')
        ->and($persona->fresh()?->hasRole('tesoreria-aprueba'))->toBeFalse()
        ->and(CruceFunciones::query()->count())->toBe(0);

    seguridad()->asignarRol($persona->id, $aprueba->id, controlDe($gerente), $admin);

    $cruce = CruceFunciones::query()->sole();

    expect($cruce->user_id)->toBe($persona->id)
        ->and($cruce->incompatibilidad_id)->toBe($par)
        ->and($cruce->desde->toDateString())->toBe('2026-03-10')
        ->and($cruce->hasta)->toBeNull()
        ->and($cruce->controlActual?->responsable_user_id)->toBe($gerente->id)
        ->and(seguridad()->estaControlado($cruce))->toBeTrue()
        ->and(seguridad()->crucesPendientes())->toHaveCount(0);

    Event::assertDispatched(CruceFuncionesRegistrado::class, fn (CruceFuncionesRegistrado $e): bool => $e->cruceId === $cruce->id && $e->conControl);
    Event::assertDispatched(ControlCruceRegistrado::class);
    Event::assertDispatched(RolAsignado::class, fn (RolAsignado $e): bool => $e->actorId === $admin->id && $e->usuarioId === $persona->id && $e->ambitoType === null);
});

// --- Cruces ---

test('agregar un permiso a un rol que crea un cruce para dos personas exige un control para cada una', function () {
    $admin = administrador();
    $gerente = User::factory()->create();
    $tesoreria = rolCon('tesoreria', 'pagos.registrar');
    $ana = User::factory()->create()->assignRole($tesoreria);
    $luis = User::factory()->create();
    PermisoPorAmbito::factory()->create(['user_id' => $luis->id, 'rol_id' => $tesoreria->id]);
    parPagos();
    $ambos = [Permission::findOrCreate('pagos.registrar')->id, Permission::findOrCreate('pagos.aprobar')->id];

    try {
        seguridad()->cambiarPermisosDeRol($tesoreria->id, $ambos, null, $admin);
        $this->fail('Debió pedir el control.');
    } catch (CruceSinControl $e) {
        expect(array_column($e->faltantes, 'usuario_id'))->toEqualCanonicalizing([$ana->id, $luis->id]);
    }

    expect($tesoreria->fresh()?->hasPermissionTo('pagos.aprobar'))->toBeFalse();

    seguridad()->cambiarPermisosDeRol($tesoreria->id, $ambos, controlDe($gerente), $admin);

    expect(CruceFunciones::query()->pluck('user_id')->all())->toEqualCanonicalizing([$ana->id, $luis->id])
        ->and(ControlCruce::query()->where('es_actual', true)->count())->toBe(2);
});

test('quitar el rol cierra el cruce en la misma transacción, sin borrarlo', function () {
    Event::fake([CruceFuncionesCerrado::class, RolQuitado::class]);
    $admin = administrador();
    $persona = User::factory()->create()->assignRole(rolCon('tesoreria-registra', 'pagos.registrar'));
    $aprueba = rolCon('tesoreria-aprueba', 'pagos.aprobar');
    parPagos();
    seguridad()->asignarRol($persona->id, $aprueba->id, controlDe(User::factory()->create()), $admin);

    seguridad()->quitarRol($persona->id, $aprueba->id, $admin);

    $cruce = CruceFunciones::query()->sole();

    expect($cruce->hasta?->toDateString())->toBe('2026-03-10');
    Event::assertDispatched(CruceFuncionesCerrado::class, fn (CruceFuncionesCerrado $e): bool => $e->cruceId === $cruce->id);
    Event::assertDispatched(RolQuitado::class, fn (RolQuitado $e): bool => $e->usuarioId === $persona->id);
});

test('un par nuevo deja los cruces que ya existían pendientes de control y lo avisa', function () {
    Event::fake([CruceFuncionesRegistrado::class]);
    $persona = User::factory()->create()->assignRole(rolCon('tesoreria', 'pagos.registrar', 'pagos.aprobar'));

    parPagos();

    $cruce = CruceFunciones::query()->sole();

    expect($cruce->user_id)->toBe($persona->id)
        ->and(seguridad()->crucesPendientes()->pluck('id')->all())->toBe([$cruce->id]);
    Event::assertDispatched(CruceFuncionesRegistrado::class, fn (CruceFuncionesRegistrado $e): bool => ! $e->conControl);

    seguridad()->registrarControl($cruce->id, controlDe(User::factory()->create()), administrador());

    expect(seguridad()->crucesPendientes())->toHaveCount(0);
});

test('un control vencido deja el cruce pendiente; renovarlo deja uno solo actual', function () {
    User::factory()->create()->assignRole(rolCon('tesoreria', 'pagos.registrar', 'pagos.aprobar'));
    parPagos();
    $cruce = CruceFunciones::query()->sole();
    $gerente = User::factory()->create();
    seguridad()->registrarControl($cruce->id, controlDe($gerente, '2026-03-31'), $gerente);

    $this->travelTo(CarbonImmutable::parse('2026-03-31 23:59', 'America/Lima'));
    expect(seguridad()->crucesPendientes())->toHaveCount(0);

    $this->travelTo(CarbonImmutable::parse('2026-04-01 00:00', 'America/Lima'));
    expect(seguridad()->crucesPendientes()->pluck('id')->all())->toBe([$cruce->id]);

    seguridad()->registrarControl($cruce->id, controlDe($gerente), $gerente);

    expect(seguridad()->crucesPendientes())->toHaveCount(0)
        ->and(ControlCruce::query()->where('cruce_id', $cruce->id)->count())->toBe(2)
        ->and(ControlCruce::query()->where('cruce_id', $cruce->id)->where('es_actual', true)->count())->toBe(1);
});

test('el responsable del control no puede ser la persona del cruce', function () {
    $admin = administrador();
    $persona = User::factory()->create()->assignRole(rolCon('tesoreria-registra', 'pagos.registrar'));
    parPagos();

    expect(fn () => seguridad()->asignarRol($persona->id, rolCon('tesoreria-aprueba', 'pagos.aprobar')->id, controlDe($persona), $admin))
        ->toThrow(SeguridadRechazada::class, 'no puede ser responsable del control de su propio cruce')
        ->and(CruceFunciones::query()->count())->toBe(0);
});

test('un cruce armado con un permiso global y otro acotado se detecta igual', function () {
    $admin = administrador();
    $persona = User::factory()->create()->assignRole(rolCon('tesoreria-registra', 'pagos.registrar'));
    parPagos();
    $aprueba = rolCon('tesoreria-aprueba', 'pagos.aprobar');

    expect(fn () => seguridad()->asignarRolEnAmbito($persona->id, $aprueba->id, 'unidad_organizativa', unidadDePrueba()->id, null, null, null, $admin))
        ->toThrow(CruceSinControl::class);

    // También si el rol acotado empieza más adelante: el cruce no se esconde en una fecha futura.
    expect(fn () => seguridad()->asignarRolEnAmbito($persona->id, $aprueba->id, 'unidad_organizativa', unidadDePrueba()->id, CarbonImmutable::parse('2026-06-01'), null, null, $admin))
        ->toThrow(CruceSinControl::class);
});

test('desactivar un par cierra sus cruces; volver a registrarlo lo reactiva', function () {
    User::factory()->create()->assignRole(rolCon('tesoreria', 'pagos.registrar', 'pagos.aprobar'));
    $par = parPagos();

    seguridad()->desactivarIncompatibilidad($par, 1, administrador());

    expect(CruceFunciones::query()->sole()->hasta?->toDateString())->toBe('2026-03-10')
        ->and(seguridad()->crucesPendientes())->toHaveCount(0)
        ->and(parPagos())->toBe($par)
        ->and(CruceFunciones::query()->whereNull('hasta')->count())->toBe(1)
        ->and(fn () => parPagos())->toThrow(SeguridadRechazada::class, 'ya está registrado');
});

// --- Roles acotados a un ámbito (D7) ---

test('un rol acotado da el permiso en su unidad y no en otra; el día hasta es vigente en hora de Lima', function () {
    $ana = User::factory()->create();
    $compras = unidadDePrueba();
    $obras = unidadDePrueba();
    PermisoPorAmbito::factory()->create([
        'user_id' => $ana->id, 'rol_id' => rolCon('comprador', 'ordenes.aprobar')->id, 'ambito_id' => $compras->id,
        'desde' => '2026-03-01', 'hasta' => '2026-03-31',
    ]);

    expect(seguridad()->puede($ana, 'ordenes.aprobar', $compras))->toBeTrue()
        ->and(seguridad()->puede($ana, 'ordenes.aprobar', $obras))->toBeFalse()
        ->and(seguridad()->puede($ana, 'ordenes.aprobar'))->toBeFalse();

    $this->travelTo(CarbonImmutable::parse('2026-03-31 23:59', 'America/Lima'));
    expect(seguridad()->puede($ana, 'ordenes.aprobar', $compras))->toBeTrue();

    // En UTC todavía es 31 de marzo (05:00): manda el día de Lima.
    $this->travelTo(CarbonImmutable::parse('2026-04-01 00:00', 'America/Lima'));
    expect(seguridad()->puede($ana, 'ordenes.aprobar', $compras))->toBeFalse();

    $this->travelTo(CarbonImmutable::parse('2026-02-28 12:00', 'America/Lima'));
    expect(seguridad()->puede($ana, 'ordenes.aprobar', $compras))->toBeFalse();
});

test('ambitosDonde da solo las unidades vigentes, o todas si el permiso es global', function () {
    $ana = User::factory()->create();
    $vigente = unidadDePrueba();
    $vencida = unidadDePrueba();
    $comprador = rolCon('comprador', 'ordenes.aprobar');
    PermisoPorAmbito::factory()->create(['user_id' => $ana->id, 'rol_id' => $comprador->id, 'ambito_id' => $vigente->id]);
    PermisoPorAmbito::factory()->create(['user_id' => $ana->id, 'rol_id' => $comprador->id, 'ambito_id' => $vencida->id, 'hasta' => '2026-03-09']);

    $ambitos = seguridad()->ambitosDonde($ana, 'ordenes.aprobar', 'unidad_organizativa');

    expect($ambitos->todos)->toBeFalse()
        ->and($ambitos->ids)->toBe([$vigente->id])
        ->and($ambitos->filtrar(UnidadOrganizativa::query(), 'id')->pluck('id')->all())->toBe([$vigente->id]);

    $ana->assignRole($comprador);

    expect(seguridad()->ambitosDonde($ana->fresh() ?? $ana, 'ordenes.aprobar', 'unidad_organizativa')->todos)->toBeTrue();
});

test('asignar y quitar un rol en un ámbito', function () {
    Event::fake([RolAsignado::class, RolQuitado::class]);
    $admin = administrador();
    $ana = User::factory()->create();
    $unidad = unidadDePrueba();
    $rol = rolCon('comprador', 'ordenes.aprobar');

    $id = seguridad()->asignarRolEnAmbito($ana->id, $rol->id, 'unidad_organizativa', $unidad->id, CarbonImmutable::parse('2026-03-01'), null, null, $admin);

    expect(seguridad()->puede($ana, 'ordenes.aprobar', $unidad))->toBeTrue()
        ->and(fn () => seguridad()->asignarRolEnAmbito($ana->id, $rol->id, 'unidad_organizativa', $unidad->id, null, null, null, $admin))
        ->toThrow(SeguridadRechazada::class, 'en fechas que se cruzan')
        ->and(fn () => seguridad()->asignarRolEnAmbito($ana->id, $rol->id, 'party', 1, null, null, null, $admin))
        ->toThrow(SeguridadRechazada::class, 'no es un ámbito')
        ->and(fn () => seguridad()->asignarRolEnAmbito($ana->id, $rol->id, 'unidad_organizativa', $unidad->id, null, CarbonImmutable::parse('2026-03-09'), null, $admin))
        ->toThrow(SeguridadRechazada::class, 'no puede ser anterior');

    seguridad()->quitarRolEnAmbito($id, 1, $admin);

    expect(PermisoPorAmbito::query()->findOrFail($id)->hasta?->toDateString())->toBe('2026-03-09')
        ->and(seguridad()->puede($ana, 'ordenes.aprobar', $unidad))->toBeFalse();
    Event::assertDispatched(RolAsignado::class, fn (RolAsignado $e): bool => $e->ambitoType === 'unidad_organizativa' && $e->ambitoId === $unidad->id);
    Event::assertDispatched(RolQuitado::class, fn (RolQuitado $e): bool => $e->ambitoId === $unidad->id);

    // Uno que todavía no empezaba se borra: nunca valió.
    $futuro = seguridad()->asignarRolEnAmbito($ana->id, $rol->id, 'unidad_organizativa', $unidad->id, CarbonImmutable::parse('2026-05-01'), null, null, $admin);
    seguridad()->quitarRolEnAmbito($futuro, 1, $admin);

    expect(PermisoPorAmbito::query()->whereKey($futuro)->exists())->toBeFalse();
});

// --- Usuarios (D4) ---

test('registra la cuenta de una persona con su nombre; rechaza un RUC y una segunda cuenta de la misma persona', function () {
    $ruc = fn (string $numero): Party => Party::factory()->create([
        'tipo_documento_id' => TipoDocumentoIdentidad::query()->firstOrCreate(['codigo' => Catalogo::RUC], ['nombre' => 'RUC', 'longitud' => 11])->id,
        'numero_documento' => $numero, 'nombres' => 'Ana', 'apellidos' => 'Pérez Soto', 'razon_social' => 'Pérez Soto Ana',
    ]);
    $dni = Party::factory()->create(['numero_documento' => '01234567', 'nombres' => 'Ana', 'apellidos' => 'Pérez Soto', 'razon_social' => 'Pérez Soto Ana']);
    $ruc10 = $ruc('10012345670');
    $empresa = Party::factory()->juridica()->create();

    $id = seguridad()->registrarUsuario(new DatosUsuario($dni->id, ' Ana@Techquk.TEST '));
    $usuario = User::query()->findOrFail($id);

    expect($usuario->name)->toBe('Pérez Soto Ana')
        ->and($usuario->email)->toBe('ana@techquk.test')
        ->and($usuario->persona_numero)->toBe('01234567')
        ->and(fn () => seguridad()->registrarUsuario(new DatosUsuario($ruc10->id, 'otra@techquk.test')))
        ->toThrow(SeguridadRechazada::class, 'no la de una empresa ni un RUC')
        ->and(fn () => seguridad()->registrarUsuario(new DatosUsuario($empresa->id, 'empresa@techquk.test')))
        ->toThrow(SeguridadRechazada::class, 'no la de una empresa ni un RUC')
        ->and(fn () => seguridad()->registrarUsuario(new DatosUsuario($dni->id, 'otra@techquk.test')))
        ->toThrow(SeguridadRechazada::class, 'ya tiene una cuenta')
        ->and(fn () => seguridad()->registrarUsuario(new DatosUsuario(Party::factory()->create()->id, 'ANA@techquk.test')))
        ->toThrow(SeguridadRechazada::class, 'Ya hay una cuenta con el correo')
        ->and(fn () => seguridad()->registrarUsuario(new DatosUsuario(Party::factory()->create()->id, 'no-es-correo')))
        ->toThrow(SeguridadRechazada::class, 'no es válido');
});

test('una segunda cuenta de la misma persona por su ficha RUC 10 no pasa, aunque se salte la regla del documento', function () {
    $dni = Party::factory()->create(['numero_documento' => '01234567', 'nombres' => 'Ana', 'apellidos' => 'Pérez Soto', 'razon_social' => 'Pérez Soto Ana']);
    seguridad()->registrarUsuario(new DatosUsuario($dni->id, 'ana@techquk.test'));
    $ruc10 = Party::factory()->create([
        'tipo_documento_id' => TipoDocumentoIdentidad::query()->firstOrCreate(['codigo' => Catalogo::RUC], ['nombre' => 'RUC', 'longitud' => 11])->id,
        'numero_documento' => '10012345670', 'nombres' => 'Ana', 'apellidos' => 'Pérez Soto', 'razon_social' => 'Pérez Soto Ana',
    ]);

    expect(fn () => User::factory()->create(['party_id' => $ruc10->id]))->toThrow(QueryException::class, 'users_persona_unique');
});

test('editar el nombre de la persona cambia el de su cuenta en la misma transacción', function () {
    $ana = User::factory()->create();
    $party = $ana->party ?? throw new LogicException('Sin persona.');

    app(PartiesService::class)->editar($party->id, ['nombres' => 'Ana María', 'apellidos' => 'Pérez Soto'], $party->version);

    expect($ana->fresh()?->name)->toBe('Pérez Soto Ana María');

    User::saving(fn () => throw new RuntimeException('falla al guardar la cuenta'));

    expect(fn () => app(PartiesService::class)->editar($party->id, ['nombres' => 'Otra', 'apellidos' => 'Pérez Soto'], $party->version + 1))
        ->toThrow(RuntimeException::class)
        ->and($party->fresh()?->nombres)->toBe('Ana María')
        ->and($ana->fresh()?->name)->toBe('Pérez Soto Ana María');
});

test('al iniciar sesión se anota el último acceso', function () {
    $ana = User::factory()->create();

    event(new Login('web', $ana, false));

    expect($ana->fresh()?->ultimo_acceso_at?->toDateString())->toBe(now()->toDateString());
});

// --- Quién cambia qué (D8) ---

test('nadie se asigna ni se quita un rol, ni cambia los permisos de un rol que tiene', function () {
    $admin = administrador();
    $otro = rolCon('otro', 'pagos.registrar');
    $propio = Role::findByName('gestor-de-prueba');

    expect(fn () => seguridad()->asignarRol($admin->id, $otro->id, null, $admin))->toThrow(SeguridadRechazada::class, 'Nadie cambia sus propios roles')
        ->and(fn () => seguridad()->quitarRol($admin->id, $propio->id, $admin))->toThrow(SeguridadRechazada::class, 'Nadie cambia sus propios roles')
        ->and(fn () => seguridad()->asignarRolEnAmbito($admin->id, $otro->id, 'unidad_organizativa', unidadDePrueba()->id, null, null, null, $admin))
        ->toThrow(SeguridadRechazada::class, 'Nadie cambia sus propios roles')
        ->and(fn () => seguridad()->cambiarPermisosDeRol($propio->id, [], null, $admin))->toThrow(SeguridadRechazada::class, 'Tienes el rol «gestor-de-prueba»')
        ->and(fn () => seguridad()->desactivarUsuario($admin->id, 1, $admin))->toThrow(SeguridadRechazada::class, 'Nadie cambia');
});

test('nunca queda nadie activo que pueda gestionar usuarios', function () {
    $ana = administrador();
    $luis = administrador();
    $gestor = Role::findByName('gestor-de-prueba');
    seguridad()->desactivarUsuario($luis->id, 1, $ana);

    expect(fn () => seguridad()->cambiarPermisosDeRol($gestor->id, [], null, User::factory()->create()->givePermissionTo('roles.gestionar')))
        ->toThrow(SeguridadRechazada::class, 'quedaría nadie activo')
        ->and(fn () => seguridad()->quitarRol($ana->id, $gestor->id, $luis))
        ->toThrow(SeguridadRechazada::class, 'quedaría nadie activo')
        ->and($ana->fresh()?->hasRole('gestor-de-prueba'))->toBeTrue();

    seguridad()->activarUsuario($luis->id, 2, $ana);
    seguridad()->desactivarUsuario($ana->id, 1, $luis);

    expect(fn () => seguridad()->desactivarUsuario($luis->id, 3, User::factory()->create()))
        ->toThrow(SeguridadRechazada::class, 'quedaría nadie activo');
});

test('el rol administrador no se renombra ni cambia sus permisos', function () {
    $rol = Role::findOrCreate(SeguridadService::ROL_ADMINISTRADOR);
    $actor = User::factory()->create();

    expect(fn () => seguridad()->renombrarRol($rol->id, 'jefe'))->toThrow(SeguridadRechazada::class, 'vienen fijos')
        ->and(fn () => seguridad()->cambiarPermisosDeRol($rol->id, [], null, $actor))->toThrow(SeguridadRechazada::class, 'vienen fijos');
});

test('crear, renombrar y cambiar los permisos de un rol, con su evento', function () {
    Event::fake([PermisosDeRolCambiados::class]);
    $id = seguridad()->crearRol('  Almacenero  ');
    seguridad()->renombrarRol($id, 'Almacén');
    $permiso = Permission::findOrCreate('almacen.despachar');

    seguridad()->cambiarPermisosDeRol($id, [$permiso->id], null, User::factory()->create());

    expect(Role::findById($id)->name)->toBe('Almacén')
        ->and(fn () => seguridad()->crearRol('Almacén'))->toThrow(SeguridadRechazada::class, 'Ya hay un rol llamado');
    Event::assertDispatched(PermisosDeRolCambiados::class, fn (PermisosDeRolCambiados $e): bool => $e->antes === [] && $e->despues === ['almacen.despachar']);
});

test('una cuenta desactivada o de sistema no puede nada', function () {
    $ana = User::factory()->create()->assignRole(rolCon('comprador', 'ordenes.aprobar'));

    expect(seguridad()->puede($ana, 'ordenes.aprobar'))->toBeTrue()
        ->and(seguridad()->puede($ana, 'no.existe'))->toBeFalse();

    seguridad()->desactivarUsuario($ana->id, 1, administrador());

    expect(seguridad()->puede($ana->fresh() ?? $ana, 'ordenes.aprobar'))->toBeFalse()
        ->and(seguridad()->puede(User::query()->where('es_sistema', true)->sole()->assignRole('comprador'), 'ordenes.aprobar'))->toBeFalse();
});

// --- Revisión de la 1.4 ---

test('nadie se cambia su puesto, ni desactiva el par de un cruce suyo, ni registra el control de su propio cruce', function () {
    $persona = User::factory()->create()->assignRole(rolCon('tesoreria', 'pagos.registrar', 'pagos.aprobar', 'roles.gestionar'));
    $par = parPagos();
    $cruce = CruceFunciones::query()->sole();

    expect(fn () => seguridad()->cambiarPuesto($persona->id, Puesto::factory()->create()->id, 1, $persona))
        ->toThrow(SeguridadRechazada::class, 'Nadie cambia')
        ->and(fn () => seguridad()->desactivarIncompatibilidad($par, 1, $persona))
        ->toThrow(SeguridadRechazada::class, 'Tienes un cruce abierto de ese par')
        ->and(fn () => seguridad()->registrarControl($cruce->id, controlDe(User::factory()->create()), $persona))
        ->toThrow(SeguridadRechazada::class, 'Es tu propio cruce')
        ->and($cruce->refresh()->hasta)->toBeNull();
});

test('una persona con cuenta activa no se desactiva; una cuenta de persona desactivada no se activa', function () {
    $admin = administrador();
    $ana = User::factory()->create();
    $persona = $ana->party ?? throw new LogicException('Sin persona.');

    expect(fn () => app(PartiesService::class)->desactivar($persona->id, $persona->version))
        ->toThrow(PartyRechazada::class, 'tiene una cuenta de usuario activa');

    seguridad()->desactivarUsuario($ana->id, 1, $admin);
    app(PartiesService::class)->desactivar($persona->id, $persona->version);

    expect(fn () => seguridad()->activarUsuario($ana->id, 2, $admin))
        ->toThrow(SeguridadRechazada::class, 'La persona está desactivada');
});

test('los roles globales, los permisos de un rol y su nombre quedan en la bitácora', function () {
    $admin = administrador();
    $ana = User::factory()->create();
    $rol = Role::query()->findOrFail(seguridad()->crearRol('Comprador'));
    $permiso = Permission::findOrCreate('ordenes.aprobar');

    seguridad()->cambiarPermisosDeRol($rol->id, [$permiso->id], null, $admin);
    seguridad()->renombrarRol($rol->id, 'Compras');
    seguridad()->asignarRol($ana->id, $rol->id, null, $admin);
    seguridad()->quitarRol($ana->id, $rol->id, $admin);

    $deLaCuenta = AuditoriaCambio::query()->where('auditable_type', 'user')->where('auditable_id', $ana->id)->where('evento', 'actualizado')->orderBy('id')->get();
    $delRol = AuditoriaCambio::query()->where('auditable_type', 'role')->where('auditable_id', $rol->id)->orderBy('id')->get();

    expect($deLaCuenta->map(fn (AuditoriaCambio $c): array => [$c->valores_anteriores, $c->valores_nuevos])->all())->toBe([
        [['roles' => []], ['roles' => ['Compras']]],
        [['roles' => ['Compras']], ['roles' => []]],
    ])
        ->and($delRol->map(fn (AuditoriaCambio $c): ?array => $c->valores_nuevos)->all())->toBe([
            ['nombre' => 'Comprador'], ['permisos' => ['ordenes.aprobar']], ['nombre' => 'Compras'],
        ]);
});

test('el puesto y el estado de la cuenta quedan en su bitácora, sin la clave', function () {
    $admin = administrador();
    $ana = User::factory()->create();
    $puesto = Puesto::factory()->create();

    seguridad()->cambiarPuesto($ana->id, $puesto->id, 1, $admin);
    seguridad()->establecerClave($ana->id, 'Otra-clave-muy-larga-1');

    $cambios = AuditoriaCambio::query()->where('auditable_type', 'user')->where('auditable_id', $ana->id)->get();

    expect($cambios->contains(fn (AuditoriaCambio $c): bool => ($c->valores_nuevos['puesto_id'] ?? null) === $puesto->id))->toBeTrue()
        ->and($cambios->contains(fn (AuditoriaCambio $c): bool => array_key_exists('password', $c->valores_nuevos ?? [])))->toBeFalse();
});

test('quitar un permiso a un rol se nota enseguida en can(): la caché de permisos no guarda el valor viejo', function () {
    $admin = administrador();
    $rol = rolCon('comprador', 'ordenes.aprobar');
    $ana = User::factory()->create()->assignRole($rol);

    expect($ana->can('ordenes.aprobar'))->toBeTrue();

    seguridad()->cambiarPermisosDeRol($rol->id, [], null, $admin);

    expect(User::query()->findOrFail($ana->id)->can('ordenes.aprobar'))->toBeFalse();
});
