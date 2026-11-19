<?php

declare(strict_types=1);

use App\Modules\Nucleo\Alertas\DefinicionAlerta;
use App\Modules\Nucleo\Alertas\FuenteAlertas;
use App\Modules\Nucleo\Alertas\RegistroAlertas;
use App\Modules\Nucleo\Alertas\TextoAlerta;
use App\Modules\Nucleo\Console\RevisarAlertas;
use App\Modules\Nucleo\Jobs\EnviarCorreoDeAviso;
use App\Modules\Nucleo\Models\AlertaEmitida;
use App\Modules\Nucleo\Models\AlertaTipo;
use App\Modules\Nucleo\Models\AlertaTipoRol;
use App\Modules\Nucleo\Models\CruceFunciones;
use App\Modules\Nucleo\Models\Notificacion;
use App\Modules\Nucleo\Models\UnidadOrganizativa;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Seguridad\CrucesSinControl;
use App\Modules\Nucleo\Services\AlertasService;
use App\Modules\Nucleo\Services\SeguridadService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Event as TareaProgramada;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/*
 * El catálogo único de alertas (plan 1.10, D1 a D6): una alerta se emite una sola vez por hecho, a las personas activas
 * con sus roles, con sus textos (editados o de fábrica) y sus datos reales; la revisión diaria corre las fuentes y cierra
 * los cruces de roles acotados vencidos. Personas, roles y alertas de prueba inventados.
 */

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-03-10 07:00', 'America/Lima'));
    Queue::fake();
});

function alertas(): AlertasService
{
    return app(AlertasService::class);
}

/** Una alerta de prueba del grupo «control» del núcleo, para el rol `tesoreria-prueba`. */
function alertaDePrueba(bool $obligatoria = false, bool $soloCampana = false, ?string $anticipacion = null): DefinicionAlerta
{
    Role::findOrCreate('tesoreria-prueba', 'web');
    $alerta = new DefinicionAlerta(
        'prueba.fianza-por-vencer',
        'Carta fianza por vencer',
        'Antes de que venza una carta fianza.',
        'control',
        'heroicon-o-bell',
        'La fianza {numero} vence en {dias} días',
        'Renuévala antes de que venza.',
        ['numero' => 'Número de la fianza', 'dias' => 'Días que faltan'],
        ['tesoreria-prueba'],
        obligatoria: $obligatoria,
        soloCampana: $soloCampana,
        anticipacion: $anticipacion,
    );
    app(RegistroAlertas::class)->registrar($alerta);

    return $alerta;
}

function tesorera(): User
{
    return User::factory()->create()->assignRole('tesoreria-prueba');
}

/** @return list<int> */
function quienesRecibieron(string $clave = 'prueba.fianza-por-vencer'): array
{
    return Notificacion::query()->where('type', $clave)->orderBy('notifiable_id')->pluck('notifiable_id')->map(fn (mixed $id): int => (int) $id)->all();
}

test('llega una sola vez por hecho a las personas activas con sus roles, con sus datos reales', function () {
    alertaDePrueba();
    $ana = tesorera();
    $luis = tesorera();
    User::factory()->inactivo()->create()->assignRole('tesoreria-prueba');
    User::factory()->create();

    expect(alertas()->emitir('prueba.fianza-por-vencer', 'fianza:7:30', ['numero' => 'CF-0007', 'dias' => 30]))->toBeTrue()
        ->and(alertas()->emitir('prueba.fianza-por-vencer', 'fianza:7:30', ['numero' => 'CF-0007', 'dias' => 30]))->toBeFalse()
        ->and(quienesRecibieron())->toBe([$ana->id, $luis->id])
        ->and(AlertaEmitida::query()->sole()->clave)->toBe('prueba.fianza-por-vencer:fianza:7:30');

    $aviso = Notificacion::query()->where('notifiable_id', $ana->id)->sole();

    expect($aviso->data['title'])->toBe('La fianza CF-0007 vence en 30 días')
        ->and($aviso->data['body'])->toBe('Renuévala antes de que venza.')
        ->and($aviso->clave_unica)->toBe('fianza:7:30');
    Queue::assertPushed(EnviarCorreoDeAviso::class, 2);
});

test('otro hecho de la misma alerta es otra alerta', function () {
    alertaDePrueba();
    tesorera();

    alertas()->emitir('prueba.fianza-por-vencer', 'fianza:7:30', ['numero' => 'CF-0007', 'dias' => 30]);
    alertas()->emitir('prueba.fianza-por-vencer', 'fianza:7:15', ['numero' => 'CF-0007', 'dias' => 15]);

    expect(AlertaEmitida::query()->count())->toBe(2)
        ->and(Notificacion::query()->count())->toBe(2);
});

test('con la entidad, la alerta queda atada a ella', function () {
    alertaDePrueba();
    tesorera();
    $unidad = UnidadOrganizativa::factory()->create();

    alertas()->emitir('prueba.fianza-por-vencer', 'fianza:7:30', ['numero' => 'CF-0007', 'dias' => 30], $unidad, '/algun/lado');

    $emitida = AlertaEmitida::query()->sole();

    expect($emitida->entidad_type)->toBe($unidad->getMorphClass())
        ->and($emitida->entidad_id)->toBe($unidad->id)
        ->and(Notificacion::query()->sole()->data['actions'][0]['url'])->toBe('/algun/lado');
});

test('las personas que suma quien la emite también la reciben, sin repetirse', function () {
    alertaDePrueba();
    $ana = tesorera();
    $otro = User::factory()->create();

    alertas()->emitir('prueba.fianza-por-vencer', 'fianza:7:30', ['numero' => 'CF-0007', 'dias' => 30], ademas: [$otro, $ana]);

    expect(quienesRecibieron())->toBe([$ana->id, $otro->id]);
});

test('si nadie activo tiene sus roles, llega a los administradores, para que no se pierda', function () {
    alertaDePrueba();
    $admin = User::factory()->create()->assignRole(Role::findOrCreate(SeguridadService::ROL_ADMINISTRADOR, 'web'));

    alertas()->emitir('prueba.fianza-por-vencer', 'fianza:7:30', ['numero' => 'CF-0007', 'dias' => 30]);

    expect(quienesRecibieron())->toBe([$admin->id]);
});

test('usa los textos editados, con los datos reales; sin editar, los de fábrica', function () {
    $alerta = alertaDePrueba();
    tesorera();
    alertas()->tipo($alerta)->forceFill(['asunto' => 'Atención: {numero} vence', 'texto' => 'Faltan {dias} días.'])->save();

    alertas()->emitir('prueba.fianza-por-vencer', 'fianza:7:30', ['numero' => 'CF-0007', 'dias' => 30]);

    expect(Notificacion::query()->sole()->data)->toMatchArray(['title' => 'Atención: CF-0007 vence', 'body' => 'Faltan 30 días.']);
});

test('apagada no se emite', function () {
    $alerta = alertaDePrueba();
    tesorera();
    alertas()->tipo($alerta)->forceFill(['es_activa' => false])->save();

    expect(alertas()->emitir('prueba.fianza-por-vencer', 'fianza:7:30', ['numero' => 'CF-0007', 'dias' => 30]))->toBeFalse()
        ->and(AlertaEmitida::query()->count())->toBe(0);
});

test('una obligatoria se emite aunque figure apagada', function () {
    $alerta = alertaDePrueba(obligatoria: true);
    tesorera();
    alertas()->tipo($alerta)->forceFill(['es_activa' => false])->save();

    expect(alertas()->emitir('prueba.fianza-por-vencer', 'fianza:7:30', ['numero' => 'CF-0007', 'dias' => 30]))->toBeTrue();
});

test('una alerta solo de campana nunca sale por correo, ni con la preferencia encendida', function () {
    alertaDePrueba(soloCampana: true);
    tesorera();

    alertas()->emitir('prueba.fianza-por-vencer', 'fianza:7:30', ['numero' => 'CF-0007', 'dias' => 30]);

    expect(Notificacion::query()->sole()->enviar_correo)->toBeFalse();
    Queue::assertNotPushed(EnviarCorreoDeAviso::class);
});

test('se emite con sus datos exactos y un hecho válido; si no, es un error de programación', function (array $datos, string $hecho) {
    alertaDePrueba();

    expect(fn () => alertas()->emitir('prueba.fianza-por-vencer', $hecho, $datos))->toThrow(InvalidArgumentException::class);
})->with([
    'falta un dato' => [['numero' => 'CF-0007'], 'fianza:7'],
    'sobra un dato' => [['numero' => 'CF-0007', 'dias' => 30, 'monto' => 1], 'fianza:7'],
    'hecho vacío' => [['numero' => 'CF-0007', 'dias' => 30], ' '],
    'hecho muy largo' => [['numero' => 'CF-0007', 'dias' => 30], str_repeat('x', 111)],
]);

test('los textos de fábrica deben traer todos sus datos y ningún marcador que no exista, y al menos un rol', function (array $cambios) {
    $base = ['asunto' => 'La fianza {numero}', 'texto' => 'Faltan {dias} días.', 'roles' => ['tesoreria-prueba']];
    $valores = [...$base, ...$cambios];

    expect(fn () => new DefinicionAlerta('prueba.otra', 'Otra', 'Otra.', 'control', 'heroicon-o-bell', $valores['asunto'], $valores['texto'], ['numero' => 'Número', 'dias' => 'Días'], $valores['roles']))
        ->toThrow(InvalidArgumentException::class);
})->with([
    'le falta un dato' => [['texto' => 'Faltan días.']],
    'marcador que no existe' => [['texto' => 'Faltan {dia} días, {dias}.']],
    'sin roles' => [['roles' => []]],
]);

test('el texto: qué datos faltan, cuáles no existen, cómo se llena', function () {
    expect(TextoAlerta::faltantes('Hay {cantidad}', 'nada', ['cantidad', 'fecha']))->toBe(['fecha'])
        ->and(TextoAlerta::desconocidos('Hay {cantida}', '{fecha}', ['cantidad', 'fecha']))->toBe(['cantida'])
        ->and(TextoAlerta::llenar('Hay {cantidad} desde el {fecha}', ['cantidad' => 3, 'fecha' => '2026-03-10']))->toBe('Hay 3 desde el 2026-03-10')
        // Un valor que trae llaves no se vuelve a llenar.
        ->and(TextoAlerta::llenar('{a} y {b}', ['a' => '{b}', 'b' => 'x']))->toBe('{b} y x');
});

test('la anticipación: de mayor a menor; sin ella, ninguna', function () {
    alertaDePrueba(anticipacion: '15,60,30');

    expect(alertas()->anticipacion('prueba.fianza-por-vencer'))->toBe([60, 30, 15])
        ->and(TextoAlerta::anticipacionValida('60,30,15'))->toBeTrue()
        ->and(TextoAlerta::anticipacionValida('60,60'))->toBeFalse()
        ->and(TextoAlerta::anticipacionValida('60, 30'))->toBeFalse()
        ->and(alertas()->anticipacion(CrucesSinControl::ALERTA))->toBe([]);
});

test('sincronizar crea cada tipo con sus roles por defecto y no toca lo editado', function () {
    $alerta = alertaDePrueba();
    $otroRol = Role::findOrCreate('compras-prueba', 'web');

    alertas()->sincronizar();
    $tipo = AlertaTipo::query()->where('codigo', $alerta->clave)->sole();
    AlertaTipoRol::query()->create(['alerta_tipo_id' => $tipo->id, 'rol_id' => $otroRol->id]);
    $tipo->forceFill(['texto' => 'Otro texto {numero} {dias}.'])->save();

    alertas()->sincronizar();

    expect(AlertaTipo::query()->count())->toBe(count(app(RegistroAlertas::class)->alertas()))
        ->and($tipo->fresh()?->texto)->toBe('Otro texto {numero} {dias}.')
        ->and($tipo->roles()->pluck('rol_id')->sort()->values()->all())->toBe([Role::findByName('tesoreria-prueba')->id, $otroRol->id]);
});

test('si sus roles de fábrica todavía no existen, la alerta nace con el administrador: nunca sin roles', function () {
    $alerta = alertaDePrueba();
    Role::findByName('tesoreria-prueba')->delete();
    $admin = Role::findOrCreate(SeguridadService::ROL_ADMINISTRADOR, 'web');

    expect(alertas()->tipo($alerta)->roles()->pluck('rol_id')->all())->toBe([$admin->id]);
});

test('las alertas del núcleo nacen con sus roles por defecto: control a Gerencia General, fallas al administrador', function () {
    Role::findOrCreate(SeguridadService::ROL_ADMINISTRADOR, 'web');
    Role::findOrCreate(SeguridadService::ROL_GERENCIA_GENERAL, 'web');

    alertas()->sincronizar();

    $roles = fn (string $clave): array => Role::query()->whereIn('id', AlertaTipo::query()->where('codigo', $clave)->sole()->roles()->pluck('rol_id'))->pluck('name')->all();

    expect($roles('nucleo.cruces-sin-control'))->toBe([SeguridadService::ROL_GERENCIA_GENERAL])
        ->and($roles('nucleo.autoaprobacion-por-revisar'))->toBe([SeguridadService::ROL_GERENCIA_GENERAL])
        ->and($roles('nucleo.efecto-externo-fallido'))->toBe([SeguridadService::ROL_ADMINISTRADOR])
        ->and($roles('nucleo.correo-no-enviado'))->toBe([SeguridadService::ROL_ADMINISTRADOR])
        ->and($roles('nucleo.tipo-cambio-faltante'))->toBe([SeguridadService::ROL_ADMINISTRADOR]);
});

test('la revisión diaria corre cada fuente; una que falla no frena a las otras y queda en el registro sin su mensaje', function () {
    Log::spy();
    $corridas = new ArrayObject;
    app()->instance('fuente-que-falla', new class implements FuenteAlertas
    {
        public function revisar(CarbonImmutable $hoy): void
        {
            throw new RuntimeException('dato sensible');
        }
    });
    app()->instance('fuente-que-anota', new class($corridas) implements FuenteAlertas
    {
        public function __construct(private ArrayObject $corridas) {}

        public function revisar(CarbonImmutable $hoy): void
        {
            $this->corridas->append($hoy->toDateString());
        }
    });
    app(RegistroAlertas::class)->registrarFuente('fuente-que-falla');
    app(RegistroAlertas::class)->registrarFuente('fuente-que-anota');

    test()->artisan(RevisarAlertas::class)->assertSuccessful();

    expect($corridas->getArrayCopy())->toBe(['2026-03-10']);
    Log::shouldHaveReceived('error')->withArgs(fn (string $mensaje, array $contexto): bool => $contexto === ['fuente' => 'fuente-que-falla', 'error' => RuntimeException::class])->once();
});

test('la revisión diaria corre a las 07:00 de Lima, sin encimarse y en un solo servidor', function () {
    $tarea = collect(app(Schedule::class)->events())->first(fn (TareaProgramada $t): bool => str_contains((string) $t->command, 'nucleo:revisar-alertas'));

    expect($tarea)->not->toBeNull()
        ->and($tarea->expression)->toBe('0 7 * * *')
        ->and($tarea->timezone)->toBe('America/Lima')
        ->and($tarea->withoutOverlapping)->toBeTrue()
        ->and($tarea->onOneServer)->toBeTrue();
});

// --- Cruces sin control (plan 1.4, D9; plan 1.10, D3 y D4) ---

function rolCruce(string $nombre, string $permiso): Role
{
    $rol = Role::findOrCreate($nombre, 'web');
    $rol->syncPermissions([Permission::findOrCreate($permiso)]);

    return $rol;
}

function parDePagos(): void
{
    app(SeguridadService::class)->registrarIncompatibilidad(Permission::findOrCreate('pagos.registrar')->id, Permission::findOrCreate('pagos.aprobar')->id, 'Quien registra un pago no lo aprueba.');
}

test('cada mañana, un solo resumen a Gerencia General si hay cruces sin control; repetir la revisión no lo repite', function () {
    $gerente = User::factory()->create()->assignRole(Role::findOrCreate(SeguridadService::ROL_GERENCIA_GENERAL, 'web'));
    User::factory()->create()->assignRole(rolCruce('registra', 'pagos.registrar'), rolCruce('aprueba', 'pagos.aprobar'));
    User::factory()->create()->assignRole('registra', 'aprueba');
    parDePagos();

    test()->artisan(RevisarAlertas::class)->assertSuccessful();
    test()->artisan(RevisarAlertas::class)->assertSuccessful();

    $aviso = Notificacion::query()->where('type', CrucesSinControl::ALERTA)->sole();

    expect($aviso->notifiable_id)->toBe($gerente->id)
        ->and($aviso->data['title'])->toBe('Hay 2 cruces de funciones sin control')
        ->and($aviso->enviar_correo)->toBeTrue();

    $this->travelTo(CarbonImmutable::parse('2026-03-11 07:00', 'America/Lima'));
    test()->artisan(RevisarAlertas::class)->assertSuccessful();

    expect(Notificacion::query()->where('type', CrucesSinControl::ALERTA)->count())->toBe(2);
});

test('sin cruces sin control, no hay resumen', function () {
    User::factory()->create()->assignRole(Role::findOrCreate(SeguridadService::ROL_GERENCIA_GENERAL, 'web'));

    test()->artisan(RevisarAlertas::class)->assertSuccessful();

    expect(AlertaEmitida::query()->where('clave', 'like', CrucesSinControl::ALERTA.':%')->count())->toBe(0);
});

test('la revisión cierra el cruce de quien tenía un rol acotado que ya venció, antes de contar', function () {
    $admin = User::factory()->create()->assignRole(rolCruce('gestor', 'usuarios.gestionar')->givePermissionTo(Permission::findOrCreate('roles.gestionar')));
    $persona = User::factory()->create()->assignRole(rolCruce('registra', 'pagos.registrar'));
    app(SeguridadService::class)->asignarRolEnAmbito($persona->id, rolCruce('aprueba', 'pagos.aprobar')->id, 'unidad_organizativa', UnidadOrganizativa::factory()->create()->id, null, CarbonImmutable::parse('2026-03-10'), null, $admin);
    parDePagos();
    $cruce = CruceFunciones::query()->sole();

    $this->travelTo(CarbonImmutable::parse('2026-03-11 07:00', 'America/Lima'));
    test()->artisan(RevisarAlertas::class)->assertSuccessful();

    expect($cruce->fresh()?->hasta?->toDateString())->toBe('2026-03-11')
        ->and(AlertaEmitida::query()->where('clave', 'like', CrucesSinControl::ALERTA.':%')->count())->toBe(0);
});
