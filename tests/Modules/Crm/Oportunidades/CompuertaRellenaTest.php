<?php

declare(strict_types=1);

use App\Modules\Crm\Cotizaciones\Requisitos\UnaCotizacionEmitida;
use App\Modules\Crm\Cuentas\CuentasServiceProvider;
use App\Modules\Crm\Cuentas\Models\Cuenta;
use App\Modules\Crm\Cuentas\Services\CuentasService;
use App\Modules\Crm\Oportunidades\Filament\Resources\Oportunidades\Pages\TableroOportunidades;
use App\Modules\Crm\Oportunidades\Filament\Resources\Oportunidades\Pages\VerOportunidad;
use App\Modules\Crm\Oportunidades\Models\Oportunidad;
use App\Modules\Crm\Oportunidades\OportunidadesServiceProvider;
use App\Modules\Crm\Oportunidades\Requisitos\RequisitosDeEtapa;
use App\Modules\Crm\Oportunidades\Services\OportunidadRechazada;
use App\Modules\Nucleo\database\seeders\NucleoSeeder;
use App\Modules\Nucleo\Models\Adjunto;
use App\Modules\Nucleo\Models\Party;
use App\Modules\Nucleo\Models\PartyContacto;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\EjecutorAcciones;
use App\Modules\Nucleo\Services\FotoPerfilService;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Modules\Crm\Oportunidades\Fixtures\OportunidadesDePrueba;

/*
 * Plan 2.21d, D8: la compuerta del avance se rellena ahí mismo. Lo de la oportunidad (cómo se confirmó la necesidad, la
 * certificación del presupuesto, el decisor) va como campos del mismo avance; lo de otro módulo, con su acción o su
 * enlace. No deja pasar con un requisito vacío. Empresas y personas inventadas.
 */

beforeEach(function () {
    config(['nucleo.ubigeos.csv' => '/no/existe.csv']);
    test()->seed(NucleoSeeder::class);
    OportunidadesDePrueba::sinExigirCotizacion();
    Filament::setCurrentPanel('app');
    Storage::fake(FotoPerfilService::DISCO);
    test()->travelTo('2026-11-16 10:00');
    $ejecutivo = User::factory()->create()->givePermissionTo(CuentasService::PERMISO_ATENDER);
    test()->cuenta = Cuenta::factory()->create([
        'ejecutivo_user_id' => $ejecutivo->id,
        'party_id' => Party::factory()->juridica()->create(['razon_social' => 'Municipalidad Compuerta Inventada'])->id,
    ]);
    test()->oportunidad = OportunidadesDePrueba::crear(test()->cuenta->id, ['ejecutivo_user_id' => $ejecutivo->id]);
    test()->actingAs(User::factory()->create()->givePermissionTo([
        OportunidadesServiceProvider::PERMISO_VER, CuentasServiceProvider::PERMISO_VER, 'crm.oportunidades.editar', 'crm.oportunidades.oportunidad.calificar',
    ]));
});

test('al marcar la necesidad pide cómo se confirmó; al marcar el presupuesto, la certificación', function () {
    Livewire::test(VerOportunidad::class, ['record' => test()->oportunidad->id])
        ->mountAction('calificar')
        ->fillForm(['necesidad_confirmada' => true, 'presupuesto_confirmado' => true])
        ->callMountedAction()
        ->assertHasActionErrors(['necesidad_detalle' => 'required', 'certificacion_presupuestal' => 'required']);

    expect(test()->oportunidad->refresh()->necesidad_confirmada)->toBeFalse();
});

test('completándola avanza: queda cómo se confirmó y la certificación como adjunto de la oportunidad', function () {
    $decisor = PartyContacto::factory()->create(['party_id' => test()->cuenta->party_id, 'nombres' => 'Lucía', 'apellidos' => 'Decisora Inventada']);
    $pdf = (string) tempnam(sys_get_temp_dir(), 'pia');
    file_put_contents($pdf, "%PDF-1.4\n%inventado\n");

    // Sin pantalla, con el mismo avance: el archivo de una subida de prueba no vive en la carpeta de subidas.
    app(EjecutorAcciones::class)->ejecutar('crm.oportunidades.oportunidad.calificar', [
        'oportunidad_id' => test()->oportunidad->id, 'version' => test()->oportunidad->version,
        'necesidad_confirmada' => true, 'necesidad_detalle' => 'Oficio 123 del área usuaria',
        'presupuesto_confirmado' => true, 'certificacion_presupuestal' => ['ruta' => $pdf, 'nombre' => 'pia.pdf'],
        'decisor_contacto_id' => $decisor->id,
    ], auth()->user());

    $o = test()->oportunidad->refresh();
    expect($o->estado->codigo)->toBe('calificada')
        ->and($o->necesidad_detalle)->toBe('Oficio 123 del área usuaria')
        ->and(Adjunto::query()->where('adjuntable_type', 'oportunidad')->where('adjuntable_id', $o->id)->count())->toBe(1);
});

test('lo que ya estaba confirmado no se vuelve a pedir', function () {
    Oportunidad::query()->whereKey(test()->oportunidad->id)->update(['necesidad_confirmada' => true, 'presupuesto_confirmado' => true]);
    $decisor = PartyContacto::factory()->create(['party_id' => test()->cuenta->party_id]);

    Livewire::test(VerOportunidad::class, ['record' => test()->oportunidad->id])
        ->callAction('calificar', ['decisor_contacto_id' => $decisor->id])
        ->assertHasNoActionErrors();

    expect(test()->oportunidad->refresh()->estado->codigo)->toBe('calificada');
});

test('un requisito de otro módulo trae su forma de cumplirlo; sin cotización aprobada, el enlace para prepararla', function () {
    $requisito = new UnaCotizacionEmitida;
    $forma = $requisito->formaDeCumplir(test()->oportunidad->id);

    expect($forma->accion)->toBeNull()->and($forma->etiqueta)->toBe('Preparar la cotización')->and($forma->url)->not->toBeNull()
        ->and(app(RequisitosDeEtapa::class)->paraCumplir(UnaCotizacionEmitida::class))->toBeInstanceOf(UnaCotizacionEmitida::class)
        ->and(app(RequisitosDeEtapa::class)->paraCumplir('NoExiste'))->toBeNull();

    // Desde la compuerta, un requisito sin acción a la mano no ejecuta nada y lo dice.
    Livewire::test(VerOportunidad::class, ['record' => test()->oportunidad->id])
        ->call('cumplirRequisito', UnaCotizacionEmitida::class, test()->oportunidad->id)
        ->assertNotified('Ese requisito no se cumple desde aquí.');
});

test('la regla vive en el servicio: sin pantalla, la necesidad pide cómo y el presupuesto su certificación', function () {
    $decisor = PartyContacto::factory()->create(['party_id' => test()->cuenta->party_id]);
    $base = ['oportunidad_id' => test()->oportunidad->id, 'version' => test()->oportunidad->version, 'decisor_contacto_id' => $decisor->id];
    $calificar = fn (array $datos) => app(EjecutorAcciones::class)->ejecutar('crm.oportunidades.oportunidad.calificar', [...$base, ...$datos], auth()->user());

    expect(fn () => $calificar(['necesidad_confirmada' => true, 'presupuesto_confirmado' => true]))
        ->toThrow(OportunidadRechazada::class, 'di cómo se confirmó')
        ->and(fn () => $calificar(['necesidad_confirmada' => true, 'necesidad_detalle' => 'Correo', 'presupuesto_confirmado' => true]))
        ->toThrow(OportunidadRechazada::class, 'adjunta la certificación presupuestal')
        ->and(test()->oportunidad->refresh()->necesidad_confirmada)->toBeFalse();
});

test('arrastrando con la certificación, el avance se hace en el servidor en una sola transacción: si falla, no queda nada', function () {
    $decisor = PartyContacto::factory()->create(['party_id' => test()->cuenta->party_id]);
    $o = test()->oportunidad;

    // En pruebas, una subida falsa no vive en la carpeta de subidas: los adjuntos la rechazan y todo se deshace.
    Livewire::test(TableroOportunidades::class)
        ->call('abrirMovimiento', $o->id, 'prospecto', 'calificada', $o->version)
        ->fillForm([
            'necesidad_confirmada' => true, 'necesidad_detalle' => 'Oficio inventado', 'presupuesto_confirmado' => true,
            'certificacion_presupuestal' => UploadedFile::fake()->createWithContent('pia.pdf', '%PDF-1.4 inventado'),
            'decisor_contacto_id' => $decisor->id,
        ])
        ->callMountedAction()
        ->assertDispatched('tq-oportunidad-dialogo-listo', fn (string $nombre, array $p): bool => $p['oportunidad'] === $o->id
            && ($p['resultado']['ok'] ?? null) === false && ! array_key_exists('certificacion_presupuestal', $p['datos']));

    $o->refresh();
    expect($o->estado->codigo)->toBe('prospecto')
        ->and($o->necesidad_confirmada)->toBeFalse()->and($o->presupuesto_confirmado)->toBeFalse()
        ->and(Adjunto::query()->where('adjuntable_type', 'oportunidad')->where('adjuntable_id', $o->id)->count())->toBe(0);
});
