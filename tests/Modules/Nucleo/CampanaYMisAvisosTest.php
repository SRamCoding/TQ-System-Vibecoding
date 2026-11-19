<?php

declare(strict_types=1);

use App\Modules\Nucleo\database\seeders\NucleoSeeder;
use App\Modules\Nucleo\Filament\CampanaAvisos;
use App\Modules\Nucleo\Filament\Pages\MisAvisos;
use App\Modules\Nucleo\Models\Notificacion;
use App\Modules\Nucleo\Models\User;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Livewire\Livewire;

/*
 * La campana solo para leer y «Mis avisos» (plan 1.4y, D1 y D3). Personas y avisos de prueba inventados.
 */

beforeEach(function () {
    config(['nucleo.ubigeos.csv' => '/no/existe.csv']);
    test()->seed(NucleoSeeder::class);
    Filament::setCurrentPanel('app');
});

function avisoCampana(User $persona, string $titulo, ?CarbonImmutable $leido = null, ?string $enlace = null): Notificacion
{
    $mensaje = Notification::make()->title($titulo)->body('Texto de prueba.');

    if ($enlace !== null) {
        $mensaje->actions([Action::make('abrir')->label('Abrir')->url($enlace)->markAsRead()]);
    }

    $aviso = new Notificacion;
    $aviso->forceFill([
        'type' => 'prueba', 'notifiable_type' => $persona->getMorphClass(), 'notifiable_id' => $persona->id, 'data' => $mensaje->getDatabaseMessage(),
        'clave_unica' => 'prueba-'.fake()->unique()->numerify('######'), 'enviar_correo' => false, 'read_at' => $leido,
    ])->save();

    return $aviso;
}

it('el panel usa la campana propia', function () {
    expect(Filament::getDatabaseNotificationsLivewireComponent())->toBe(CampanaAvisos::class);
});

it('la campana muestra solo los no leídos, sin «Borrar» ni la ✕, con «Marcar como leído» y el enlace a «Mis avisos»', function () {
    $persona = User::factory()->create();
    avisoCampana($persona, 'Sin leer de prueba');
    avisoCampana($persona, 'Leído de prueba', CarbonImmutable::now());

    Livewire::actingAs($persona)->test(CampanaAvisos::class)
        ->assertSee('Sin leer de prueba')->assertDontSee('Leído de prueba')
        ->assertSee('Marcar como leído')->assertSee('Marcar todos como leídos')
        ->assertDontSee('Borrar')
        ->assertSee('.fi-no-notification-close-btn { display: none; }', escape: false)
        ->assertSee('Ver todos mis avisos')->assertSee(MisAvisos::getUrl());
});

it('marcar uno desde la campana pasa por la acción, con id entero; «Abrir» también lo marca', function () {
    $persona = User::factory()->create();
    $uno = avisoCampana($persona, 'Uno');
    $conEnlace = avisoCampana($persona, 'Con enlace', enlace: '/mis-datos');

    $campana = Livewire::actingAs($persona)->test(CampanaAvisos::class);
    $campana->dispatch('markedNotificationAsRead', id: (string) $uno->id);
    $campana->dispatch('markedNotificationAsRead', id: (string) $conEnlace->id);

    expect($uno->refresh()->read_at)->not->toBeNull()
        ->and($conEnlace->refresh()->read_at)->not->toBeNull()
        ->and($uno->updated_at)->not->toBeNull();
    $campana->assertDontSee('Uno')->assertSee('No tienes avisos sin leer');
});

it('«Marcar todos» deja la campana vacía; borrar no hace nada, ni uno ni todos', function () {
    $persona = User::factory()->create();
    $uno = avisoCampana($persona, 'Uno');
    avisoCampana($persona, 'Dos');

    $campana = Livewire::actingAs($persona)->test(CampanaAvisos::class);
    $campana->dispatch('notificationClosed', id: (string) $uno->id)->call('clearNotifications');
    expect(Notificacion::query()->count())->toBe(2);

    $campana->call('markAllNotificationsAsRead');
    expect(Notificacion::query()->whereNull('read_at')->count())->toBe(0)
        ->and(Notificacion::query()->count())->toBe(2);
});

it('desde la campana no se marca el aviso de otra persona', function () {
    $persona = User::factory()->create();
    $ajeno = avisoCampana(User::factory()->create(), 'Ajeno');

    Livewire::actingAs($persona)->test(CampanaAvisos::class)
        ->dispatch('markedNotificationAsRead', id: (string) $ajeno->id)
        ->assertNotified('Ese aviso no está entre los tuyos.');

    expect($ajeno->refresh()->read_at)->toBeNull();
});

it('«Mis avisos» abre en los leídos, solo con los suyos, y se puede ver todo', function () {
    $persona = User::factory()->create();
    $leido = avisoCampana($persona, 'Leído suyo', CarbonImmutable::now());
    $sinLeer = avisoCampana($persona, 'Sin leer suyo');
    $ajeno = avisoCampana(User::factory()->create(), 'De otra persona', CarbonImmutable::now());

    Livewire::actingAs($persona)->test(MisAvisos::class)
        ->assertCanSeeTableRecords([$leido])->assertCanNotSeeTableRecords([$sinLeer, $ajeno])
        ->filterTable('estado', 'no_leidos')->assertCanSeeTableRecords([$sinLeer])->assertCanNotSeeTableRecords([$leido])
        ->filterTable('estado', null)->assertCanSeeTableRecords([$leido, $sinLeer])->assertCanNotSeeTableRecords([$ajeno]);
});

it('desde «Mis avisos» se marca como no leído (vuelve a la campana) y como leído; «Abrir» solo si tiene enlace', function () {
    $persona = User::factory()->create();
    $leido = avisoCampana($persona, 'Leído', CarbonImmutable::now(), '/mis-datos');
    $sinEnlace = avisoCampana($persona, 'Sin enlace', CarbonImmutable::now());

    $pagina = Livewire::actingAs($persona)->test(MisAvisos::class)
        ->assertActionVisible(TestAction::make('abrir')->table($leido))
        ->assertActionHidden(TestAction::make('abrir')->table($sinEnlace))
        ->assertActionHidden(TestAction::make('marcarLeido')->table($leido))
        ->callAction(TestAction::make('marcarNoLeido')->table($leido));

    expect($leido->refresh()->read_at)->toBeNull();

    $pagina->filterTable('estado', null)->callAction(TestAction::make('marcarLeido')->table($leido));
    expect($leido->refresh()->read_at)->not->toBeNull();
});

it('«Mis avisos» responde a quien inició sesión y ya no está en el menú de la foto (plan 1.4aa, D2)', function () {
    $persona = User::factory()->create();

    $this->actingAs($persona)->get(MisAvisos::getUrl())->assertOk()->assertSee('Mis avisos');
    // El menú de la foto va dibujado en cada pantalla: ya no lleva «Mis avisos» (se llega desde la campana).
    $this->actingAs($persona)->get('/')->assertOk()->assertDontSee('heroicon-o-inbox-stack', escape: false)
        ->assertDontSee('>Mis avisos<', escape: false);
});
