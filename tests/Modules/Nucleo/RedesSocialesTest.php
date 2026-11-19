<?php

declare(strict_types=1);

use App\Modules\Nucleo\database\seeders\NucleoSeeder;
use App\Modules\Nucleo\Excepciones\AccionRechazada;
use App\Modules\Nucleo\Excepciones\ContactoRechazado;
use App\Modules\Nucleo\Excepciones\RedSocialRechazada;
use App\Modules\Nucleo\Excepciones\VersionDesactualizada;
use App\Modules\Nucleo\Models\Party;
use App\Modules\Nucleo\Models\PartyContacto;
use App\Modules\Nucleo\Models\PartyRedSocial;
use App\Modules\Nucleo\Models\RedSocial;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Parties\DatosContacto;
use App\Modules\Nucleo\Parties\DatosRedSocial;
use App\Modules\Nucleo\Parties\ResumenRedSocial;
use App\Modules\Nucleo\RegistroAcciones\EjecutorAcciones;
use App\Modules\Nucleo\Services\ContactosPartyService;
use App\Modules\Nucleo\Services\PartiesService;
use App\Modules\Nucleo\Services\RedesSocialesService;
use App\Modules\Nucleo\Services\UsuarioSistema;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/*
 * Plan 1.4o, D2 y D3: las redes sociales de una party, de sus contactos y de cada persona del equipo, con la revisión
 * del enlace contra el catálogo y la visibilidad red por red. Todo por las acciones registradas, sin interfaz.
 */

beforeEach(function () {
    config(['nucleo.ubigeos.csv' => '/no/existe.csv']);
    test()->seed(NucleoSeeder::class);
});

function idRed(string $codigo): int
{
    return RedSocial::query()->where('codigo', $codigo)->value('id');
}

function accionRedes(string $clave, array $datos, User $usuario): mixed
{
    return app(EjecutorAcciones::class)->ejecutar($clave, $datos, $usuario);
}

/** Quien gestiona las redes de clientes y proveedores. */
function gestorDeRedes(): User
{
    return User::factory()->create()->givePermissionTo('parties.gestionar-redes');
}

test('revisa el enlace contra los dominios de la red, también sus subdominios', function (string $enlace, ?string $dominios, ?string $normalizado, bool $esDeLaRed) {
    expect(RedesSocialesService::normalizar($enlace))->toBe($normalizado);

    if ($normalizado !== null) {
        expect(RedesSocialesService::esDeLaRed($normalizado, $dominios))->toBe($esDeLaRed);
    }
})->with([
    'perfil de LinkedIn' => ['https://www.linkedin.com/in/ana-prueba', 'linkedin.com', 'https://www.linkedin.com/in/ana-prueba', true],
    'subdominio de país' => ['https://pe.linkedin.com/in/ana-prueba', 'linkedin.com', 'https://pe.linkedin.com/in/ana-prueba', true],
    'sin esquema' => ['linkedin.com/company/ejemplo', 'linkedin.com', 'https://linkedin.com/company/ejemplo', true],
    'dominio en mayúsculas' => ['  HTTPS://WWW.LinkedIn.COM/in/Ana-Prueba  ', 'linkedin.com', 'https://www.linkedin.com/in/Ana-Prueba', true],
    'dominio corto de la red' => ['https://youtu.be/abc123', 'youtube.com,youtu.be', 'https://youtu.be/abc123', true],
    'otro dominio' => ['https://www.facebook.com/ejemplo', 'linkedin.com', 'https://www.facebook.com/ejemplo', false],
    'parecido que no es subdominio' => ['https://falsolinkedin.com/in/ana', 'linkedin.com', 'https://falsolinkedin.com/in/ana', false],
    'la red escondida en la ruta' => ['https://ejemplo.com/linkedin.com', 'linkedin.com', 'https://ejemplo.com/linkedin.com', false],
    'sitio web: cualquier dominio' => ['www.ejemplo.com.pe', null, 'https://www.ejemplo.com.pe', true],
    'con espacios dentro' => ['https://www.linkedin.com/in/ana prueba', 'linkedin.com', null, false],
    'otro esquema' => ['ftp://ejemplo.com/archivo', null, null, false],
    'con usuario y clave' => ['https://usuario:clave@linkedin.com/in/ana', 'linkedin.com', null, false],
    'sin dominio' => ['https://localhost/ana', null, null, false],
    'vacío' => ['   ', null, null, false],
]);

test('agrega las redes de una empresa y de su contacto; el enlace se guarda normalizado y a nombre de quien lo pide', function () {
    $gestor = gestorDeRedes();
    $contacto = PartyContacto::factory()->create();

    $deEmpresa = accionRedes('nucleo.parties.agregar-red-social', ['party_id' => $contacto->party_id, 'red_social_id' => idRed('sitio_web'), 'enlace' => 'WWW.Ejemplo.com.pe'], $gestor);
    $deContacto = accionRedes('nucleo.parties.agregar-red-social', ['party_id' => $contacto->party_id, 'contacto_id' => $contacto->id, 'red_social_id' => idRed('linkedin'), 'enlace' => 'pe.linkedin.com/in/contacto-prueba'], $gestor);

    expect(PartyRedSocial::query()->findOrFail($deEmpresa))
        ->enlace->toBe('https://www.ejemplo.com.pe')
        ->contacto_id->toBeNull()
        ->created_by->toBe($gestor->id)
        ->and(PartyRedSocial::query()->findOrFail($deContacto))
        ->enlace->toBe('https://pe.linkedin.com/in/contacto-prueba')
        ->contacto_id->toBe($contacto->id);
});

test('rechaza un enlace que no es de la red elegida, sin mostrar el enlace en el mensaje', function () {
    $empresa = Party::factory()->juridica()->create();

    expect(fn () => accionRedes('nucleo.parties.agregar-red-social', ['party_id' => $empresa->id, 'red_social_id' => idRed('linkedin'), 'enlace' => 'https://www.facebook.com/empresa-prueba'], gestorDeRedes()))
        ->toThrow(RedSocialRechazada::class, 'El enlace no es de LinkedIn: debe ser de linkedin.com.')
        ->and(fn () => accionRedes('nucleo.parties.agregar-red-social', ['party_id' => $empresa->id, 'red_social_id' => idRed('linkedin'), 'enlace' => 'no es un enlace'], gestorDeRedes()))
        ->toThrow(RedSocialRechazada::class, 'El enlace no es una dirección web válida');
});

test('el mismo enlace no se repite en el mismo dueño, pero sí en otro', function () {
    $contacto = PartyContacto::factory()->create();
    $datos = ['party_id' => $contacto->party_id, 'red_social_id' => idRed('linkedin'), 'enlace' => 'https://www.linkedin.com/company/ejemplo'];

    accionRedes('nucleo.parties.agregar-red-social', $datos, gestorDeRedes());
    accionRedes('nucleo.parties.agregar-red-social', [...$datos, 'contacto_id' => $contacto->id], gestorDeRedes());

    expect(fn () => accionRedes('nucleo.parties.agregar-red-social', [...$datos, 'enlace' => 'WWW.LINKEDIN.COM/company/ejemplo'], gestorDeRedes()))
        ->toThrow(RedSocialRechazada::class, 'Ese enlace ya está registrado aquí.');
});

test('un contacto de otra empresa, uno con el vínculo cerrado o una red desactivada no se aceptan', function () {
    $empresa = Party::factory()->juridica()->create();
    $ajeno = PartyContacto::factory()->create();
    $cerrado = PartyContacto::factory()->create(['party_id' => $empresa->id, 'desde' => '2026-01-01', 'hasta' => '2026-06-30']);
    RedSocial::query()->where('codigo', 'tiktok')->update(['es_activo' => false]);

    foreach ([$ajeno->id, $cerrado->id] as $contactoId) {
        expect(fn () => accionRedes('nucleo.parties.agregar-red-social', ['party_id' => $empresa->id, 'contacto_id' => $contactoId, 'red_social_id' => idRed('sitio_web'), 'enlace' => 'ejemplo.com'], gestorDeRedes()))
            ->toThrow(RedSocialRechazada::class, 'El contacto elegido no es un contacto vigente');
    }

    expect(fn () => accionRedes('nucleo.parties.agregar-red-social', ['party_id' => $empresa->id, 'red_social_id' => idRed('tiktok'), 'enlace' => 'tiktok.com/@ejemplo'], gestorDeRedes()))
        ->toThrow(RedSocialRechazada::class, 'La red elegida no existe o está desactivada')
        // La base también lo impide: la red de un contacto es de la misma party.
        ->and(fn () => DB::table('party_redes_sociales')->insert(['party_id' => $empresa->id, 'contacto_id' => $ajeno->id, 'red_social_id' => idRed('sitio_web'), 'enlace' => 'https://ejemplo.com', 'created_by' => app(UsuarioSistema::class)->id()]))
        ->toThrow(QueryException::class, 'party_redes_sociales_contacto_foreign');
});

test('una red que ya tenía se puede seguir cambiando aunque el catálogo la haya desactivado', function () {
    $red = PartyRedSocial::factory()->create(['party_id' => Party::factory()->juridica(), 'red_social_id' => idRed('tiktok'), 'enlace' => 'https://www.tiktok.com/@antes']);
    RedSocial::query()->whereKey($red->red_social_id)->update(['es_activo' => false]);

    accionRedes('nucleo.parties.cambiar-red-social', ['red_id' => $red->id, 'red_social_id' => $red->red_social_id, 'enlace' => 'tiktok.com/@despues', 'version' => 1], gestorDeRedes());

    expect($red->refresh())->enlace->toBe('https://tiktok.com/@despues')->version->toBe(2);
});

test('cambiar o quitar con una versión vieja se rechaza', function () {
    $red = PartyRedSocial::factory()->create(['party_id' => Party::factory()->juridica()]);
    PartyRedSocial::query()->whereKey($red->id)->update(['version' => 2]);

    expect(fn () => accionRedes('nucleo.parties.quitar-red-social', ['red_id' => $red->id, 'version' => 1], gestorDeRedes()))
        ->toThrow(VersionDesactualizada::class);

    accionRedes('nucleo.parties.quitar-red-social', ['red_id' => $red->id, 'version' => 2], gestorDeRedes());

    expect(PartyRedSocial::query()->whereKey($red->id)->exists())->toBeFalse();
});

test('las redes de clientes y contactos piden el permiso; las propias no', function () {
    $empresa = Party::factory()->juridica()->create();

    expect(fn () => accionRedes('nucleo.parties.agregar-red-social', ['party_id' => $empresa->id, 'red_social_id' => idRed('sitio_web'), 'enlace' => 'ejemplo.com'], User::factory()->create()))
        ->toThrow(AccionRechazada::class);

    $persona = User::factory()->create();
    $id = accionRedes('nucleo.agregar-mi-red-social', ['red_social_id' => idRed('linkedin'), 'enlace' => 'linkedin.com/in/persona-prueba', 'es_privada' => false], $persona);

    expect(PartyRedSocial::query()->findOrFail($id)->party_id)->toBe($persona->party_id);
});

test('las redes de una persona del equipo no se cambian desde su ficha: solo ella, desde Mis datos', function () {
    $persona = User::factory()->create();
    $id = accionRedes('nucleo.agregar-mi-red-social', ['red_social_id' => idRed('linkedin'), 'enlace' => 'linkedin.com/in/persona-prueba', 'es_privada' => false], $persona);

    expect(fn () => accionRedes('nucleo.parties.agregar-red-social', ['party_id' => $persona->party_id, 'red_social_id' => idRed('sitio_web'), 'enlace' => 'ejemplo.com'], gestorDeRedes()))
        ->toThrow(RedSocialRechazada::class, 'desde «Mis datos»')
        ->and(fn () => accionRedes('nucleo.parties.quitar-red-social', ['red_id' => $id, 'version' => 1], gestorDeRedes()))
        ->toThrow(RedSocialRechazada::class, 'desde «Mis datos»');
});

test('nadie cambia ni quita las redes de otra persona con las acciones propias', function () {
    $duena = User::factory()->create();
    $otra = User::factory()->create();
    $id = accionRedes('nucleo.agregar-mi-red-social', ['red_social_id' => idRed('instagram'), 'enlace' => 'instagram.com/persona.prueba', 'es_privada' => true], $duena);
    $deEmpresa = PartyRedSocial::factory()->create(['party_id' => Party::factory()->juridica()]);

    expect(fn () => accionRedes('nucleo.cambiar-mi-red-social', ['red_id' => $id, 'red_social_id' => idRed('instagram'), 'enlace' => 'instagram.com/otra', 'es_privada' => false, 'version' => 1], $otra))
        ->toThrow(RedSocialRechazada::class, 'Esa red no es tuya.')
        ->and(fn () => accionRedes('nucleo.quitar-mi-red-social', ['red_id' => $id, 'version' => 1], $otra))
        ->toThrow(RedSocialRechazada::class, 'Esa red no es tuya.')
        ->and(fn () => accionRedes('nucleo.quitar-mi-red-social', ['red_id' => $deEmpresa->id, 'version' => 1], $otra))
        ->toThrow(RedSocialRechazada::class, 'Esa red no es tuya.');

    accionRedes('nucleo.cambiar-mi-red-social', ['red_id' => $id, 'red_social_id' => idRed('instagram'), 'enlace' => 'instagram.com/persona.prueba', 'es_privada' => false, 'version' => 1], $duena);

    expect(PartyRedSocial::query()->findOrFail($id))->es_privada->toBeFalse()->version->toBe(2);
});

test('la regla de quién escribe vive en el servicio: también vale para quien lo llame sin las acciones', function () {
    $redes = app(RedesSocialesService::class);
    $persona = User::factory()->create();
    $otra = User::factory()->create();
    $datos = new DatosRedSocial(idRed('sitio_web'), 'ejemplo.com');
    $id = $redes->agregar($persona->party_id, null, $datos, propia: $persona);

    expect(fn () => $redes->agregar($persona->party_id, null, $datos))->toThrow(RedSocialRechazada::class, 'desde «Mis datos»')
        ->and(fn () => $redes->quitar($id, 1))->toThrow(RedSocialRechazada::class, 'desde «Mis datos»')
        ->and(fn () => $redes->cambiar($id, new DatosRedSocial(idRed('sitio_web'), 'otro.com'), 1, propia: $otra))->toThrow(RedSocialRechazada::class, 'Esa red no es tuya.')
        ->and(fn () => $redes->agregar($persona->party_id, null, $datos, propia: $otra))->toThrow(RedSocialRechazada::class, 'Esa red no es tuya.');
});

test('«solo la veo yo» es solo para el equipo: ni un cliente ni un contacto', function () {
    $contacto = PartyContacto::factory()->create();
    $redes = app(RedesSocialesService::class);
    $datos = new DatosRedSocial(idRed('sitio_web'), 'ejemplo.com', esPrivada: true);

    expect(fn () => $redes->agregar($contacto->party_id, null, $datos))->toThrow(RedSocialRechazada::class, 'Solo una persona del equipo')
        ->and(fn () => $redes->agregar($contacto->party_id, $contacto->id, $datos))->toThrow(RedSocialRechazada::class, 'Solo una persona del equipo')
        ->and(fn () => DB::table('party_redes_sociales')->insert(['party_id' => $contacto->party_id, 'contacto_id' => $contacto->id, 'red_social_id' => idRed('sitio_web'), 'enlace' => 'https://ejemplo.com', 'es_privada' => true, 'created_by' => app(UsuarioSistema::class)->id()]))
        ->toThrow(QueryException::class, 'party_redes_sociales_privada_check');
});

test('una red privada no la ve otro compañero, ni con permisos de administración; sí la persona', function () {
    $persona = User::factory()->create();
    $companero = User::factory()->create();
    $administracion = User::factory()->create()->givePermissionTo(['usuarios.gestionar', 'parties.gestionar-redes']);
    accionRedes('nucleo.agregar-mi-red-social', ['red_social_id' => idRed('linkedin'), 'enlace' => 'linkedin.com/in/persona-prueba', 'es_privada' => false], $persona);
    accionRedes('nucleo.agregar-mi-red-social', ['red_social_id' => idRed('instagram'), 'enlace' => 'instagram.com/persona.prueba', 'es_privada' => true], $persona);
    $redes = app(RedesSocialesService::class);
    $nombres = fn (User $quien): array => array_map(fn (ResumenRedSocial $r): string => $r->red, $redes->redesQueVe($persona->party_id, null, $quien));

    expect($nombres($companero))->toBe(['LinkedIn'])
        ->and($nombres($persona))->toBe(['LinkedIn', 'Instagram'])
        ->and($nombres($administracion))->toBe(['LinkedIn'])
        ->and($redes->redesQueVe($persona->party_id, null, $persona)[1])
        ->icono->toBe('tq-red-instagram')
        ->enlace->toBe('https://instagram.com/persona.prueba')
        ->esPrivada->toBeTrue();
});

test('las redes de la empresa y las de cada contacto se consultan por separado', function () {
    $contacto = PartyContacto::factory()->create();
    PartyRedSocial::factory()->create(['party_id' => $contacto->party_id, 'red_social_id' => idRed('facebook'), 'enlace' => 'https://facebook.com/empresa']);
    PartyRedSocial::factory()->create(['party_id' => $contacto->party_id, 'contacto_id' => $contacto->id, 'red_social_id' => idRed('linkedin'), 'enlace' => 'https://linkedin.com/in/contacto']);
    $redes = app(RedesSocialesService::class);
    $quien = User::factory()->create();

    expect(array_map(fn (ResumenRedSocial $r): string => $r->red, $redes->redesQueVe($contacto->party_id, null, $quien)))->toBe(['Facebook'])
        ->and(array_map(fn (ResumenRedSocial $r): string => $r->red, $redes->redesQueVe($contacto->party_id, $contacto->id, $quien)))->toBe(['LinkedIn']);
});

test('borrar un contacto o una empresa se lleva sus redes; trasladar un contacto se las lleva a su nuevo lugar', function () {
    $contacto = PartyContacto::factory()->create(['desde' => '2026-01-01']);
    $empresa = $contacto->party_id;
    PartyRedSocial::factory()->create(['party_id' => $empresa, 'contacto_id' => $contacto->id, 'red_social_id' => idRed('linkedin'), 'enlace' => 'https://linkedin.com/in/contacto']);
    $destino = Party::factory()->juridica()->create();

    $nuevo = app(ContactosPartyService::class)->trasladar($contacto->id, $destino->id, CarbonImmutable::parse('2026-09-30'), new DatosContacto($contacto->nombres, $contacto->apellidos, cargo: 'Gerente'), $contacto->refresh()->version);

    // Se copian: el vínculo cerrado conserva las suyas (su historia), y el nuevo arranca con las mismas.
    expect(PartyRedSocial::query()->where('contacto_id', $nuevo)->sole())
        ->party_id->toBe($destino->id)
        ->enlace->toBe('https://linkedin.com/in/contacto')
        ->and(PartyRedSocial::query()->where('contacto_id', $contacto->id)->sole()->enlace)->toBe('https://linkedin.com/in/contacto');

    $otro = PartyContacto::factory()->create(['party_id' => $destino->id]);
    PartyRedSocial::factory()->create(['party_id' => $destino->id, 'contacto_id' => $otro->id, 'red_social_id' => idRed('sitio_web'), 'enlace' => 'https://ejemplo.com']);
    app(ContactosPartyService::class)->borrar($otro->id, $otro->refresh()->version);

    expect(PartyRedSocial::query()->where('contacto_id', $otro->id)->exists())->toBeFalse();

    $sola = Party::factory()->juridica()->create();
    PartyRedSocial::factory()->create(['party_id' => $sola->id, 'red_social_id' => idRed('sitio_web'), 'enlace' => 'https://ejemplo.com']);
    app(PartiesService::class)->borrar($sola->id, $sola->refresh()->version);

    expect(PartyRedSocial::query()->where('party_id', $sola->id)->exists())->toBeFalse();
});

test('el contacto trasladado sigue sin poder borrarse: las redes no cuentan como uso, el traslado sí', function () {
    $contacto = PartyContacto::factory()->create(['desde' => '2026-01-01']);
    app(ContactosPartyService::class)->trasladar($contacto->id, Party::factory()->juridica()->create()->id, CarbonImmutable::parse('2026-09-30'), new DatosContacto($contacto->nombres, $contacto->apellidos, cargo: 'Gerente'), $contacto->refresh()->version);

    expect(fn () => app(ContactosPartyService::class)->borrar($contacto->id, $contacto->refresh()->version))->toThrow(ContactoRechazado::class);
});
