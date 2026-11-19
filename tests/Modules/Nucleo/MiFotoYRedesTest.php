<?php

declare(strict_types=1);

use App\Modules\Nucleo\database\seeders\NucleoSeeder;
use App\Modules\Nucleo\Excepciones\FotoRechazada;
use App\Modules\Nucleo\Filament\MisDatos\MisRedesSociales;
use App\Modules\Nucleo\Filament\Pages\MisDatos;
use App\Modules\Nucleo\Models\PartyRedSocial;
use App\Modules\Nucleo\Models\RedSocial;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\EjecutorAcciones;
use App\Modules\Nucleo\RegistroAcciones\RegistroAcciones;
use App\Modules\Nucleo\Services\FotoPerfilService;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/*
 * Plan 1.4o, paso 5: en «Mis datos», la foto de perfil (D4 a D7) y mis redes con su visibilidad (D2 y D3).
 */

beforeEach(function () {
    config(['nucleo.ubigeos.csv' => '/no/existe.csv']);
    test()->seed(NucleoSeeder::class);
    Filament::setCurrentPanel('app');
    Storage::fake(FotoPerfilService::DISCO);
});

/** Un PNG de verdad (1 × 1), escrito en un archivo temporal: aquí no hay GD para fabricar imágenes. */
function fotoDePrueba(): string
{
    $ruta = tempnam(sys_get_temp_dir(), 'foto');
    file_put_contents($ruta, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='));

    return $ruta;
}

function archivoConContenido(string $contenido): string
{
    $ruta = tempnam(sys_get_temp_dir(), 'foto');
    file_put_contents($ruta, $contenido);

    return $ruta;
}

function cambiarMiFoto(User $usuario, string $ruta): void
{
    app(EjecutorAcciones::class)->ejecutar('nucleo.cambiar-mi-foto', ['foto' => ['ruta' => $ruta, 'nombre' => 'IMG_0001.png']], $usuario);
}

test('sube la foto, la cambia por otra (la anterior se borra) y la quita', function () {
    $persona = User::factory()->create();

    cambiarMiFoto($persona, fotoDePrueba());
    $primera = $persona->refresh()->foto_ruta;

    expect($primera)->toMatch('#^fotos-perfil/'.$persona->id.'/[0-9a-f-]{36}\.png$#');
    Storage::disk(FotoPerfilService::DISCO)->assertExists($primera);

    cambiarMiFoto($persona, fotoDePrueba());
    $segunda = $persona->refresh()->foto_ruta;

    expect($segunda)->not->toBe($primera);
    Storage::disk(FotoPerfilService::DISCO)->assertMissing($primera);
    Storage::disk(FotoPerfilService::DISCO)->assertExists($segunda);

    app(EjecutorAcciones::class)->ejecutar('nucleo.quitar-mi-foto', [], $persona);

    expect($persona->refresh()->foto_ruta)->toBeNull()
        ->and($persona->getFilamentAvatarUrl())->toBeNull();
    Storage::disk(FotoPerfilService::DISCO)->assertMissing($segunda);
});

test('nadie puede cambiar la foto de otra persona: la acción no dice de quién es, es siempre la propia', function () {
    $ana = User::factory()->create();
    $otra = User::factory()->create();
    cambiarMiFoto($ana, fotoDePrueba());
    $deAna = $ana->refresh()->foto_ruta;

    $registro = app(RegistroAcciones::class);
    $campos = fn (string $clave): array => array_map(fn ($c): string => $c->nombre, $registro->obtener($clave)->campos());

    expect($campos('nucleo.cambiar-mi-foto'))->toBe(['foto'])
        ->and($campos('nucleo.quitar-mi-foto'))->toBe([])
        ->and($registro->obtener('nucleo.cambiar-mi-foto')->permiso())->toBeNull();

    cambiarMiFoto($otra, fotoDePrueba());
    app(EjecutorAcciones::class)->ejecutar('nucleo.quitar-mi-foto', [], $otra);

    expect($ana->refresh()->foto_ruta)->toBe($deAna);
});

test('solo acepta imágenes: un texto con otro nombre o una foto HEIC se rechazan con un mensaje claro', function () {
    $persona = User::factory()->create();

    expect(fn () => cambiarMiFoto($persona, archivoConContenido('no soy una foto')))
        ->toThrow(FotoRechazada::class, 'La foto debe ser una imagen JPG, PNG o WebP.');

    // Cabecera de un HEIC del iPhone: según la versión de `finfo`, se reconoce como HEIC o como algo que no es imagen.
    expect(fn () => cambiarMiFoto($persona, archivoConContenido("\x00\x00\x00\x18ftypheic\x00\x00\x00\x00mif1heic".str_repeat("\x00", 64))))
        ->toThrow(FotoRechazada::class);

    expect($persona->refresh()->foto_ruta)->toBeNull();
});

test('la base solo guarda rutas de fotos de perfil', function () {
    $persona = User::factory()->create();

    expect(fn () => User::query()->whereKey($persona->id)->update(['foto_ruta' => '../.env']))
        ->toThrow(QueryException::class, 'users_foto_ruta_check');
});

test('la foto se sirve solo con sesión, como imagen, y el menú la muestra en el círculo', function () {
    $persona = User::factory()->create();
    cambiarMiFoto($persona, fotoDePrueba());
    $url = $persona->refresh()->getFilamentAvatarUrl();

    expect($url)->toContain('/fotos/'.$persona->id.'?v=');

    test()->get($url)->assertRedirect();

    test()->actingAs(User::factory()->create())->get($url)
        ->assertOk()
        ->assertHeader('Content-Type', 'image/png');

    test()->actingAs($persona)->get('/fotos/'.User::factory()->create()->id)->assertNotFound();

    expect((string) test()->actingAs($persona)->get('/mis-datos')->getContent())->toContain(e($url));
});

test('«Mis datos» muestra la foto al costado de los datos de la cuenta: tocarla abre la ventana para subirla', function () {
    $persona = User::factory()->create();

    $pantalla = Livewire::actingAs($persona)->test(MisDatos::class)
        ->assertSee(['Mi cuenta', 'Subir mi foto', 'Mis redes'])
        ->assertDontSee('Quitar mi foto');

    // El círculo hace la misma llamada que el botón «Subir mi foto».
    expect($pantalla->html())->toContain('wire:click="mountAction(\'nucleoCambiarMiFoto\', {}, { schemaComponent: \'form\' })"')
        // A la derecha de los datos: va después de ellos.
        ->and(strpos($pantalla->html(), 'class="tq-mi-foto"'))->toBeGreaterThan(strpos($pantalla->html(), 'Último acceso'));

    cambiarMiFoto($persona, fotoDePrueba());

    Livewire::actingAs($persona->refresh())->test(MisDatos::class)
        ->assertSee(['Cambiar mi foto', 'Quitar mi foto']);
});

test('sube la foto desde la ventana de «Mis datos»', function () {
    $persona = User::factory()->create();
    $foto = UploadedFile::fake()->createWithContent('IMG_0001.png', (string) file_get_contents(fotoDePrueba()));

    Livewire::actingAs($persona)->test(MisDatos::class)
        ->call('mountAction', 'nucleoCambiarMiFoto', [], ['schemaComponent' => 'form'])
        // El círculo de la ventana va al centro.
        ->assertSeeHtml('tq-subir-foto')
        ->assertSeeHtml('.tq-subir-foto .fi-fo-file-upload-input-ctn { width: 13rem; margin-inline: auto; }')
        // Excepciones de interfaz.md: si Filament renombra sus clases, esto avisa.
        ->assertSeeHtml('fi-fo-file-upload-input-ctn')
        ->fillForm(['foto' => $foto])
        ->callMountedAction()
        ->assertHasNoFormErrors()
        ->assertNotified('Listo: tu foto nueva ya se ve en el menú.');

    expect($persona->refresh()->foto_ruta)->not->toBeNull()
        // La foto del menú se cambia en el sitio buscando esta clase de Filament (excepción de interfaz.md).
        ->and((string) test()->actingAs($persona)->get('/mis-datos')->getContent())->toContain('fi-user-avatar');
});

test('sin foto, las iniciales se dibujan aquí: el nombre no sale a otro sitio', function () {
    $persona = User::factory()->create(['name' => 'Ana María Prueba']);
    $url = filament()->getUserAvatarUrl($persona);

    expect($url)->toStartWith('data:image/svg+xml;base64,')
        ->and(base64_decode(substr($url, strlen('data:image/svg+xml;base64,'))))->toContain('>AM</text>')
        ->and((string) test()->actingAs($persona)->get('/mis-datos')->getContent())->not->toContain('ui-avatars.com');
});

test('mis redes: agrego una solo para mí y la veo marcada; las de otro no aparecen', function () {
    $persona = User::factory()->create();
    $otra = User::factory()->create();
    PartyRedSocial::factory()->create(['party_id' => $otra->party_id, 'red_social_id' => RedSocial::query()->where('codigo', 'facebook')->value('id'), 'enlace' => 'https://facebook.com/otra-persona']);

    Livewire::actingAs($persona)->test(MisRedesSociales::class)
        ->callAction(TestAction::make('nucleoAgregarMiRedSocial')->table(), [
            'red_social_id' => RedSocial::query()->where('codigo', 'instagram')->value('id'),
            'enlace' => 'instagram.com/persona.prueba',
            'es_privada' => true,
        ])
        ->assertHasNoFormErrors()
        ->assertNotified('Guardado.');

    Livewire::actingAs($persona)->test(MisRedesSociales::class)
        ->assertSee(['Instagram', 'https://instagram.com/persona.prueba', 'Solo yo'])
        ->assertDontSee('https://facebook.com/otra-persona');
});
