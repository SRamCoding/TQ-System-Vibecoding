<?php

declare(strict_types=1);

use App\Modules\Nucleo\database\seeders\NucleoSeeder;
use App\Modules\Nucleo\Excepciones\ContactoRechazado;
use App\Modules\Nucleo\Excepciones\VersionDesactualizada;
use App\Modules\Nucleo\Models\AuditoriaCambio;
use App\Modules\Nucleo\Models\Party;
use App\Modules\Nucleo\Models\PartyContacto;
use App\Modules\Nucleo\Models\PartySede;
use App\Modules\Nucleo\Parties\DatosContacto;
use App\Modules\Nucleo\Services\ContactosPartyService;
use App\Modules\Nucleo\Services\PartiesService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/*
 * Contactos con rotación (plan 1.3b, D1 y D2): cada contacto es un vínculo; cuando alguien cambia de institución
 * se cierra aquí y se abre allá, enlazado. Un solo principal por party, siempre vigente.
 */

beforeEach(function () {
    config(['nucleo.ubigeos.csv' => '/no/existe.csv']);
    test()->seed(NucleoSeeder::class);
    CarbonImmutable::setTestNow('2026-10-16 10:00:00');
});

afterEach(fn () => CarbonImmutable::setTestNow());

function contactos(): ContactosPartyService
{
    return app(ContactosPartyService::class);
}

function datosContacto(string $nombres = 'Luis', string $apellidos = 'Fernández Ruiz', mixed ...$otros): DatosContacto
{
    return new DatosContacto($nombres, $apellidos, ...$otros);
}

function versionContacto(int $id): int
{
    return (int) PartyContacto::query()->whereKey($id)->value('version');
}

test('agrega un contacto con sus datos limpios y el correo en minúsculas', function () {
    $party = Party::factory()->juridica()->create();

    $id = contactos()->agregar($party->id, datosContacto(' Luis ', 'Fernández Ruiz', cargo: 'Jefe de Logística', email: 'LUIS@EJEMPLO.PE', esDecisor: true));
    $contacto = PartyContacto::query()->findOrFail($id);

    expect($contacto->nombres)->toBe('Luis')
        ->and($contacto->email)->toBe('luis@ejemplo.pe')
        ->and($contacto->es_decisor)->toBeTrue()
        ->and($contacto->es_principal)->toBeFalse()
        ->and($contacto->version)->toBe(1);
});

test('se rechaza un contacto sin nombres, con correo inválido o en una party desactivada', function (Closure $intento, string $mensaje) {
    expect($intento)->toThrow(ContactoRechazado::class, $mensaje);
})->with([
    'sin nombres' => [fn () => contactos()->agregar(Party::factory()->juridica()->create()->id, datosContacto(' ', 'Ruiz')), 'nombres y apellidos'],
    'correo inválido' => [fn () => contactos()->agregar(Party::factory()->juridica()->create()->id, datosContacto(email: 'no-es-correo')), 'correo del contacto no es válido'],
    'party desactivada' => [fn () => contactos()->agregar(Party::factory()->juridica()->inactiva()->create()->id, datosContacto()), 'está desactivada'],
    'termina antes de empezar' => [fn () => contactos()->agregar(Party::factory()->juridica()->create()->id, datosContacto(desde: CarbonImmutable::parse('2026-08-01'), hasta: CarbonImmutable::parse('2026-07-31'))), 'no puede terminar antes de empezar'],
    'principal ya cerrado' => [fn () => contactos()->agregar(Party::factory()->juridica()->create()->id, datosContacto(esPrincipal: true, hasta: CarbonImmutable::parse('2026-07-31'))), 'no puede ser el principal'],
]);

test('el mensaje de rechazo no repite datos personales del contacto', function () {
    $party = Party::factory()->juridica()->create();

    $mensaje = null;

    try {
        contactos()->agregar($party->id, datosContacto('Luis', 'Fernández', email: 'luis.fernandez@@ejemplo'));
    } catch (ContactoRechazado $e) {
        $mensaje = $e->getMessage();
    }

    expect($mensaje)->toBeString()->not->toContain('Luis')->not->toContain('luis.fernandez');
});

test('agregar un principal desmarca al anterior y lo deja en la bitácora', function () {
    $party = Party::factory()->juridica()->create();
    $luis = contactos()->agregar($party->id, datosContacto(esPrincipal: true));
    $carmen = contactos()->agregar($party->id, datosContacto('Carmen', 'Ruiz', esPrincipal: true));

    $antes = PartyContacto::query()->findOrFail($luis);
    $cambio = AuditoriaCambio::query()->where('auditable_type', $antes->getMorphClass())->where('auditable_id', $luis)->latest('id')->firstOrFail();

    expect($antes->es_principal)->toBeFalse()
        ->and($antes->version)->toBe(2)
        ->and(PartyContacto::query()->findOrFail($carmen)->es_principal)->toBeTrue()
        ->and($cambio->valores_nuevos)->toBe(['es_principal' => false]);
});

test('marcar principal desmarca al anterior; uno cerrado o ya principal se rechaza', function () {
    $party = Party::factory()->juridica()->create();
    $luis = contactos()->agregar($party->id, datosContacto(esPrincipal: true));
    $carmen = contactos()->agregar($party->id, datosContacto('Carmen', 'Ruiz'));
    $jorge = contactos()->agregar($party->id, datosContacto('Jorge', 'Castillo', hasta: CarbonImmutable::parse('2026-07-31')));

    contactos()->marcarPrincipal($carmen, 1);

    expect(PartyContacto::query()->where('party_id', $party->id)->where('es_principal', true)->pluck('id')->all())->toBe([$carmen])
        ->and(fn () => contactos()->marcarPrincipal($carmen, versionContacto($carmen)))->toThrow(ContactoRechazado::class, 'ya es el principal')
        ->and(fn () => contactos()->marcarPrincipal($jorge, 1))->toThrow(ContactoRechazado::class, 'ya no está vigente')
        ->and(PartyContacto::query()->findOrFail($luis)->es_principal)->toBeFalse();
});

test('si dos personas marcan principal a la vez y la base rechaza a una, el mensaje es de negocio', function () {
    $party = Party::factory()->juridica()->create();
    $carmen = contactos()->agregar($party->id, datosContacto('Carmen', 'Ruiz'));
    $ana = contactos()->agregar($party->id, datosContacto('Ana', 'Soto'));

    // La otra persona guarda a Ana como principal justo después de que este servicio desmarcó al anterior y
    // justo antes de que guarde a Carmen: el hueco que el bloqueo de la party cierra y la base vigila.
    PartyContacto::saving(function (PartyContacto $contacto) use ($carmen, $ana): void {
        if ($contacto->id === $carmen && $contacto->es_principal) {
            DB::table('party_contactos')->where('id', $ana)->update(['es_principal' => true]);
        }
    });

    DB::transaction(function () use ($party, $carmen): void {
        expect(fn () => contactos()->marcarPrincipal($carmen, 1))
            ->toThrow(ContactoRechazado::class, 'Otra persona acaba de cambiar el contacto principal. Vuelve a abrir la ficha.');

        // La transacción de afuera sigue sana: el choque quedó en su punto de guardado.
        expect(PartyContacto::query()->where('party_id', $party->id)->count())->toBe(2);
    });
});

test('editar reemplaza los datos y exige la versión leída', function () {
    $party = Party::factory()->juridica()->create();
    $id = contactos()->agregar($party->id, datosContacto(cargo: 'Jefe', telefono: '979 555 214'));

    contactos()->editar($id, datosContacto(cargo: 'Gerente', desde: CarbonImmutable::parse('2024-03-01')), 1);
    $contacto = PartyContacto::query()->findOrFail($id);

    expect($contacto->cargo)->toBe('Gerente')
        ->and($contacto->telefono)->toBeNull()
        ->and($contacto->desde?->toDateString())->toBe('2024-03-01')
        ->and($contacto->version)->toBe(2)
        ->and(fn () => contactos()->editar($id, datosContacto(), 1))->toThrow(VersionDesactualizada::class, 'este contacto');
});

test('cerrar el vínculo del principal le quita la marca y lo saca de sus sedes', function () {
    $party = Party::factory()->juridica()->create();
    $luis = contactos()->agregar($party->id, datosContacto(esPrincipal: true, desde: CarbonImmutable::parse('2025-01-01')));
    $sede = PartySede::factory()->create(['party_id' => $party->id, 'contacto_id' => $luis]);

    contactos()->cerrarVinculo($luis, CarbonImmutable::parse('2026-07-31'), 1);
    $contacto = PartyContacto::query()->findOrFail($luis);

    expect($contacto->hasta?->toDateString())->toBe('2026-07-31')
        ->and($contacto->es_principal)->toBeFalse()
        ->and($sede->refresh()->contacto_id)->toBeNull()
        ->and($sede->version)->toBe(2)
        ->and(app(PartiesService::class)->contactosVigentes($party->id))->toBe([]);
});

test('no se cierra dos veces ni antes de empezar', function () {
    $party = Party::factory()->juridica()->create();
    $id = contactos()->agregar($party->id, datosContacto(desde: CarbonImmutable::parse('2026-08-01')));

    expect(fn () => contactos()->cerrarVinculo($id, CarbonImmutable::parse('2026-07-31'), 1))->toThrow(ContactoRechazado::class, 'antes de empezar');

    contactos()->cerrarVinculo($id, CarbonImmutable::parse('2026-08-01'), 1);

    expect(fn () => contactos()->cerrarVinculo($id, CarbonImmutable::parse('2026-09-01'), 2))->toThrow(ContactoRechazado::class, 'ya no está vigente');
});

test('trasladar cierra aquí y abre allá con los datos nuevos, enlazados, desde el día siguiente', function () {
    $pimentel = Party::factory()->juridica()->create(['razon_social' => 'Municipalidad de Ejemplo']);
    $gore = Party::factory()->juridica()->create(['razon_social' => 'Gobierno Regional de Ejemplo']);
    $jorge = contactos()->agregar($pimentel->id, datosContacto('Jorge', 'Castillo', cargo: 'Gerente de Administración', email: 'jorge@muni.pe', esPrincipal: true));

    $nuevo = contactos()->trasladar($jorge, $gore->id, CarbonImmutable::parse('2026-07-31'), datosContacto('Jorge', 'Castillo', cargo: 'Jefe de Abastecimiento', email: 'jorge@gore.pe'), 1);

    $cerrado = PartyContacto::query()->findOrFail($jorge);
    $abierto = PartyContacto::query()->findOrFail($nuevo);

    expect($cerrado->hasta?->toDateString())->toBe('2026-07-31')
        ->and($cerrado->es_principal)->toBeFalse()
        ->and($cerrado->siguiente?->party->razon_social)->toBe('Gobierno Regional de Ejemplo')
        ->and($abierto->party_id)->toBe($gore->id)
        ->and($abierto->contacto_anterior_id)->toBe($jorge)
        ->and($abierto->anterior?->party->razon_social)->toBe('Municipalidad de Ejemplo')
        ->and($abierto->cargo)->toBe('Jefe de Abastecimiento')
        ->and($abierto->email)->toBe('jorge@gore.pe')
        ->and($abierto->desde?->toDateString())->toBe('2026-08-01')
        ->and($abierto->es_principal)->toBeFalse();
});

test('el desde del traslado se puede elegir, y el nuevo vínculo puede ser el principal allá', function () {
    $gore = Party::factory()->juridica()->create();
    $principalGore = contactos()->agregar($gore->id, datosContacto('Ana', 'Soto', esPrincipal: true));
    $jorge = contactos()->agregar(Party::factory()->juridica()->create()->id, datosContacto('Jorge', 'Castillo'));

    $nuevo = contactos()->trasladar($jorge, $gore->id, CarbonImmutable::parse('2026-07-31'), datosContacto('Jorge', 'Castillo', cargo: 'Gerente', esPrincipal: true, desde: CarbonImmutable::parse('2026-09-15')), 1);

    expect(PartyContacto::query()->findOrFail($nuevo)->desde?->toDateString())->toBe('2026-09-15')
        ->and(PartyContacto::query()->findOrFail($nuevo)->es_principal)->toBeTrue()
        ->and(PartyContacto::query()->findOrFail($principalGore)->es_principal)->toBeFalse();
});

test('un vínculo ya cerrado también se traslada, sin fecha de cierre', function () {
    $jorge = contactos()->agregar(Party::factory()->juridica()->create()->id, datosContacto('Jorge', 'Castillo', hasta: CarbonImmutable::parse('2026-07-31')));

    $nuevo = contactos()->trasladar($jorge, Party::factory()->juridica()->create()->id, null, datosContacto('Jorge', 'Castillo', cargo: 'Gerente'), 1);

    expect(PartyContacto::query()->findOrFail($nuevo)->desde?->toDateString())->toBe('2026-08-01')
        ->and(PartyContacto::query()->findOrFail($jorge)->version)->toBe(1);
});

test('el traslado se rechaza sin cargo nuevo, a la misma party, a una desactivada, sin fecha o por segunda vez', function () {
    $origen = Party::factory()->juridica()->create();
    $destino = Party::factory()->juridica()->create();
    $abierto = contactos()->agregar($origen->id, datosContacto());
    $nuevos = datosContacto(cargo: 'Gerente');

    expect(fn () => contactos()->trasladar($abierto, $destino->id, CarbonImmutable::parse('2026-07-31'), datosContacto(cargo: ' '), 1))->toThrow(ContactoRechazado::class, 'cargo que ocupa')
        ->and(fn () => contactos()->trasladar($abierto, $origen->id, CarbonImmutable::parse('2026-07-31'), $nuevos, 1))->toThrow(ContactoRechazado::class, 'a otra persona o empresa')
        ->and(fn () => contactos()->trasladar($abierto, Party::factory()->juridica()->inactiva()->create()->id, CarbonImmutable::parse('2026-07-31'), $nuevos, 1))->toThrow(ContactoRechazado::class, 'destino está desactivada')
        ->and(fn () => contactos()->trasladar($abierto, $destino->id, null, $nuevos, 1))->toThrow(ContactoRechazado::class, 'hasta cuándo');

    contactos()->trasladar($abierto, $destino->id, CarbonImmutable::parse('2026-07-31'), $nuevos, 1);

    expect(fn () => contactos()->trasladar($abierto, Party::factory()->juridica()->create()->id, null, $nuevos, versionContacto($abierto)))
        ->toThrow(ContactoRechazado::class, 'ya continúa en otra institución');
});

test('si el traslado falla, nada queda a medias', function () {
    $origen = Party::factory()->juridica()->create();
    $abierto = contactos()->agregar($origen->id, datosContacto(esPrincipal: true));

    expect(fn () => contactos()->trasladar($abierto, Party::factory()->juridica()->create()->id, CarbonImmutable::parse('2026-07-31'), datosContacto(cargo: 'Gerente', email: 'malo'), 1))
        ->toThrow(ContactoRechazado::class, 'correo');

    $contacto = PartyContacto::query()->findOrFail($abierto);

    expect($contacto->hasta)->toBeNull()
        ->and($contacto->es_principal)->toBeTrue()
        ->and(PartyContacto::query()->count())->toBe(1);
});

test('se borra un contacto no usado; uno usado en una sede o en un traslado no', function () {
    $party = Party::factory()->juridica()->create();
    $libre = contactos()->agregar($party->id, datosContacto());
    $enSede = contactos()->agregar($party->id, datosContacto('Carmen', 'Ruiz'));
    PartySede::factory()->create(['party_id' => $party->id, 'contacto_id' => $enSede]);
    $trasladado = contactos()->agregar($party->id, datosContacto('Jorge', 'Castillo'));
    contactos()->trasladar($trasladado, Party::factory()->juridica()->create()->id, CarbonImmutable::parse('2026-07-31'), datosContacto('Jorge', 'Castillo', cargo: 'Gerente'), 1);

    contactos()->borrar($libre, 1);

    expect(PartyContacto::query()->find($libre))->toBeNull()
        ->and(fn () => contactos()->borrar($enSede, 1))->toThrow(ContactoRechazado::class, 'ya se usa')
        ->and(fn () => contactos()->borrar($trasladado, versionContacto($trasladado)))->toThrow(ContactoRechazado::class, 'ya se usa');
});

test('contactosVigentes da los que no tienen fecha de cierre, el principal primero, con su cargo', function () {
    $party = Party::factory()->juridica()->create();
    contactos()->agregar($party->id, datosContacto('Carmen', 'Ruiz', cargo: 'Asistente'));
    $luis = contactos()->agregar($party->id, datosContacto('Luis', 'Zapata', cargo: 'Jefe de Logística', esPrincipal: true, esDecisor: true));
    contactos()->agregar($party->id, datosContacto('Jorge', 'Castillo', hasta: CarbonImmutable::parse('2026-07-31')));
    contactos()->agregar($party->id, datosContacto('Ana', 'Futura', desde: CarbonImmutable::parse('2026-11-01')));
    contactos()->agregar($party->id, datosContacto('Pedro', 'Hoy', hasta: CarbonImmutable::parse('2026-10-16')));

    $vigentes = app(PartiesService::class)->contactosVigentes($party->id);

    // Vigente = sin fecha de cierre: el que cerró hoy ya no está; el que empieza después, sí.
    expect(array_map(fn ($c): string => $c->etiqueta, $vigentes))->toBe(['Luis Zapata · Jefe de Logística', 'Ana Futura', 'Carmen Ruiz · Asistente'])
        ->and($vigentes[0]->id)->toBe($luis)
        ->and($vigentes[0]->esPrincipal)->toBeTrue()
        ->and($vigentes[0]->esDecisor)->toBeTrue();
});

test('borrar una party nunca usada se lleva también sus contactos y sedes', function () {
    $party = Party::factory()->juridica()->create();
    $contacto = contactos()->agregar($party->id, datosContacto());
    PartySede::factory()->create(['party_id' => $party->id, 'contacto_id' => $contacto]);

    app(PartiesService::class)->borrar($party->id, 1);

    expect(Party::query()->find($party->id))->toBeNull()
        ->and(PartyContacto::query()->count())->toBe(0)
        ->and(PartySede::query()->count())->toBe(0);
});

test('un vínculo no se cierra con fecha futura: ni al cerrar, ni al trasladar, ni al agregar', function () {
    $party = Party::factory()->juridica()->create();
    $id = contactos()->agregar($party->id, datosContacto());
    $manana = CarbonImmutable::parse('2026-10-17');

    expect(fn () => contactos()->cerrarVinculo($id, $manana, 1))->toThrow(ContactoRechazado::class, 'no puede ser posterior a hoy')
        ->and(fn () => contactos()->trasladar($id, Party::factory()->juridica()->create()->id, $manana, datosContacto(cargo: 'Gerente'), 1))->toThrow(ContactoRechazado::class, 'no puede ser posterior a hoy')
        ->and(fn () => contactos()->agregar($party->id, datosContacto('Ana', 'Soto', hasta: $manana)))->toThrow(ContactoRechazado::class, 'no puede ser posterior a hoy');

    contactos()->cerrarVinculo($id, CarbonImmutable::parse('2026-10-16'), 1);

    expect(app(PartiesService::class)->contactosVigentes($party->id))->toBe([]);
});

test('faltaContactoPrincipal: solo con vigentes y ninguno principal', function () {
    $party = Party::factory()->juridica()->create();
    $parties = app(PartiesService::class);

    expect($parties->faltaContactoPrincipal($party->id))->toBeFalse();

    $luis = contactos()->agregar($party->id, datosContacto());

    expect($parties->faltaContactoPrincipal($party->id))->toBeTrue();

    contactos()->marcarPrincipal($luis, 1);

    expect($parties->faltaContactoPrincipal($party->id))->toBeFalse();
});
