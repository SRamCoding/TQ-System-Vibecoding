<?php

declare(strict_types=1);

use App\Modules\Nucleo\Acciones\Alertas\ConfigurarAlerta;
use App\Modules\Nucleo\Alertas\DefinicionAlerta;
use App\Modules\Nucleo\Alertas\RegistroAlertas;
use App\Modules\Nucleo\database\seeders\NucleoSeeder;
use App\Modules\Nucleo\Filament\Pages\Alertas;
use App\Modules\Nucleo\Models\AlertaTipo;
use App\Modules\Nucleo\Models\Notificacion;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Services\AlertasService;
use App\Modules\Nucleo\Services\SeguridadService;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

/*
 * Plan 2.2, D6: una alerta que siempre llega a una persona puntual («al responsable de la partida») puede no tener
 * roles. Si esa persona no puede recibirla y no hay roles con alguien activo, entra el respaldo de siempre: el
 * administrador. Las demás alertas siguen exigiendo al menos un rol. Personas y alertas inventadas.
 */

beforeEach(function () {
    config(['nucleo.ubigeos.csv' => '/no/existe.csv']);
    test()->seed(NucleoSeeder::class);
    Filament::setCurrentPanel('app');
    Queue::fake();
});

function adpAlerta(): DefinicionAlerta
{
    $alerta = new DefinicionAlerta(
        'prueba.partida-al-limite',
        'Partida al límite',
        'Cuando una partida de prueba se acerca a su presupuesto.',
        'control',
        'heroicon-o-chart-bar',
        'La partida {partida} está al límite',
        'Revisa su saldo.',
        ['partida' => 'Partida'],
        [],
        destinatarioPropio: 'al responsable de la partida',
    );
    app(RegistroAlertas::class)->registrar($alerta);

    return $alerta;
}

/** @return list<int> */
function adpQuienesRecibieron(): array
{
    return Notificacion::query()->where('type', 'prueba.partida-al-limite')->orderBy('notifiable_id')->pluck('notifiable_id')->map(fn (mixed $id): int => (int) $id)->all();
}

function adpAdministrador(): User
{
    return User::factory()->create()->assignRole(SeguridadService::ROL_ADMINISTRADOR);
}

test('sin destinatario propio, una alerta sin roles no se puede declarar', function () {
    expect(fn () => new DefinicionAlerta('prueba.sin-roles', 'Sin roles', 'Nada.', 'control', 'heroicon-o-bell', 'Asunto', 'Texto', [], []))
        ->toThrow(InvalidArgumentException::class, 'al menos un rol');
});

test('nace sin roles y llega solo al responsable, no al administrador', function () {
    $alerta = adpAlerta();

    expect(app(AlertasService::class)->tipo($alerta)->roles()->count())->toBe(0);
    adpAdministrador();
    $responsable = User::factory()->create();

    app(AlertasService::class)->emitir('prueba.partida-al-limite', 'partida:1', ['partida' => 'PC-01 Suministro'], ademas: [$responsable]);

    expect(adpQuienesRecibieron())->toBe([$responsable->id]);
});

test('si el responsable está inactivo y no hay roles con alguien, le llega al administrador', function () {
    adpAlerta();
    $administrador = adpAdministrador();
    $inactivo = User::factory()->inactivo()->create();

    app(AlertasService::class)->emitir('prueba.partida-al-limite', 'partida:1', ['partida' => 'PC-01 Suministro'], ademas: [$inactivo]);

    expect(adpQuienesRecibieron())->toBe([$administrador->id]);
});

test('se guarda sin roles, no figura como «sin nadie» y la pantalla dice a quién llega siempre', function () {
    $alerta = adpAlerta();
    app(AlertasService::class)->tipo($alerta);
    $version = (int) AlertaTipo::query()->where('codigo', $alerta->clave)->value('version');

    app(AlertasService::class)->configurar($alerta->clave, [], true, null, $alerta->asunto, $alerta->texto, $version);

    expect(AlertaTipo::query()->where('codigo', $alerta->clave)->sole()->roles()->count())->toBe(0)
        ->and(app(AlertasService::class)->sinDestinatariosPropiosDe([$alerta]))->toBe([]);

    Livewire::withQueryParams(['alerta' => $alerta->clave])
        ->actingAs(User::factory()->create()->givePermissionTo(ConfigurarAlerta::PERMISO))
        ->test(Alertas::class)
        ->assertSee('Siempre llega al responsable de la partida');
});

test('en una alerta normal, una persona puntual inactiva tampoco la recibe', function () {
    Role::findOrCreate('obras-prueba', 'web');
    app(RegistroAlertas::class)->registrar(new DefinicionAlerta('prueba.con-rol', 'Con rol', 'Una alerta con su rol.', 'control', 'heroicon-o-bell', 'Asunto', 'Texto', [], ['obras-prueba']));
    $conRol = User::factory()->create()->assignRole('obras-prueba');
    $inactivo = User::factory()->inactivo()->create();

    app(AlertasService::class)->emitir('prueba.con-rol', 'hecho:1', [], ademas: [$inactivo]);

    expect(Notificacion::query()->where('type', 'prueba.con-rol')->pluck('notifiable_id')->map(fn (mixed $id): int => (int) $id)->all())->toBe([$conRol->id]);
});
