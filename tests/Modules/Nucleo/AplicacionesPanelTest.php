<?php

declare(strict_types=1);

use App\Modules\Nucleo\Aplicaciones\AplicacionDelPanel;
use App\Modules\Nucleo\database\seeders\CatalogosSeeder;
use App\Modules\Nucleo\database\seeders\ParametrosSeeder;
use App\Modules\Nucleo\Enums\IconoAplicacion;
use App\Modules\Nucleo\Enums\OrigenAplicacion;
use App\Modules\Nucleo\Enums\ProgramaEscritorio;
use App\Modules\Nucleo\Excepciones\AplicacionRechazada;
use App\Modules\Nucleo\Models\Aplicacion;
use App\Modules\Nucleo\Models\AplicacionOculta;
use App\Modules\Nucleo\Models\AplicacionPrincipal;
use App\Modules\Nucleo\Models\AplicacionPropia;
use App\Modules\Nucleo\Models\OrdenAplicacion;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Models\UsoAplicacion;
use App\Modules\Nucleo\Services\AplicacionesService;
use App\Modules\Nucleo\Services\UsuarioSistema;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/*
 * Plan 1.4p, paso 2, y plan 1.4x: los datos del panel de aplicaciones. Las de Techquk (semilla de 10: Claude, Excel,
 * WhatsApp, Word…), las propias de cada persona, las ocultas, el conteo de uso (que ya no decide nada) y sus 3
 * principales, las del muelle, que elige ella (1.4x, D1 y D2).
 */

beforeEach(function () {
    config(['nucleo.ubigeos.csv' => '/no/existe.csv']);
    test()->seed(ParametrosSeeder::class);
    test()->seed(CatalogosSeeder::class);
});

/**
 * @param  list<AplicacionDelPanel>  $aplicaciones
 * @return list<string>
 */
function nombresDelPanel(array $aplicaciones): array
{
    return array_map(fn (AplicacionDelPanel $aplicacion): string => $aplicacion->nombre, $aplicaciones);
}

function usarAplicacion(User $usuario, OrigenAplicacion $origen, int $id, int $veces = 1): void
{
    test()->actingAs($usuario);

    for ($i = 0; $i < $veces; $i++) {
        app(AplicacionesService::class)->registrarUso($usuario, $origen, $id);
    }
}

test('la semilla deja las 13 de fábrica, en su orden, con su ícono y su versión web', function () {
    $aplicaciones = Aplicacion::query()->orderBy('orden')->get();

    expect($aplicaciones->pluck('nombre')->all())->toBe(['Claude', 'Excel', 'WhatsApp', 'Word', 'PowerPoint', 'Outlook', 'OneDrive', 'Google Drive', 'LinkedIn', 'Gmail', 'Teams', 'Google Meet', 'Excalidraw'])
        ->and($aplicaciones->pluck('icono')->all())->toBe([
            IconoAplicacion::Claude, IconoAplicacion::Excel, IconoAplicacion::Whatsapp, IconoAplicacion::Word, IconoAplicacion::Powerpoint,
            IconoAplicacion::Outlook, IconoAplicacion::Onedrive, IconoAplicacion::GoogleDrive, IconoAplicacion::Linkedin, IconoAplicacion::Gmail,
            IconoAplicacion::Teams, IconoAplicacion::GoogleMeet, IconoAplicacion::Excalidraw,
        ])
        ->and($aplicaciones->pluck('enlace')->every(fn (string $enlace): bool => str_starts_with($enlace, 'https://')))->toBeTrue()
        ->and($aplicaciones->every(fn (Aplicacion $aplicacion): bool => $aplicacion->icono->imagen() !== null))->toBeTrue();
});

test('la semilla se puede repetir y no pisa lo que cambió administración', function () {
    Aplicacion::query()->where('codigo', 'word')->update(['nombre' => 'Word en línea']);

    test()->seed(CatalogosSeeder::class);

    expect(Aplicacion::query()->count())->toBe(13)
        ->and(Aplicacion::query()->where('codigo', 'word')->value('nombre'))->toBe('Word en línea');
});

test('una base con el orden de fábrica viejo pasa al nuevo; si administración lo cambió, no se toca', function (int $ordenWord, array $esperado) {
    Aplicacion::query()->whereNotIn('codigo', ['word', 'excel', 'powerpoint'])->delete();
    foreach (['word' => $ordenWord, 'excel' => 2, 'powerpoint' => 3] as $codigo => $orden) {
        Aplicacion::query()->where('codigo', $codigo)->update(['orden' => $orden]);
    }

    test()->seed(CatalogosSeeder::class);

    expect(Aplicacion::query()->whereIn('codigo', ['word', 'excel', 'powerpoint'])->orderBy('codigo')->pluck('orden', 'codigo')->all())->toBe($esperado);
})->with([
    'el viejo de fábrica' => [1, ['excel' => 2, 'powerpoint' => 5, 'word' => 4]],
    'cambiado por administración' => [7, ['excel' => 2, 'powerpoint' => 3, 'word' => 7]],
]);

test('sin elegir todavía, sus 3 principales (y el muelle) son las 3 primeras del orden de administración', function () {
    $usuario = User::factory()->create();

    expect(nombresDelPanel(app(AplicacionesService::class)->delMuelle($usuario)))->toBe(['Claude', 'Excel', 'WhatsApp']);

    Aplicacion::factory()->create(['nombre' => 'Primera', 'orden' => 0]);
    expect(nombresDelPanel(app(AplicacionesService::class)->delMuelle($usuario)))->toBe(['Primera', 'Claude', 'Excel']);
});

test('el uso se sigue contando, pero ya no decide el muelle', function () {
    $usuario = User::factory()->create();
    $gmail = Aplicacion::query()->where('codigo', 'gmail')->sole();

    usarAplicacion($usuario, OrigenAplicacion::Techquk, $gmail->id, 5);

    expect(nombresDelPanel(app(AplicacionesService::class)->delMuelle($usuario)))->toBe(['Claude', 'Excel', 'WhatsApp'])
        ->and(UsoAplicacion::query()->where('aplicacion_id', $gmail->id)->value('usos'))->toBe(5);
});

test('poner una en un lugar reemplaza a la que estaba; una que ya era principal intercambia lugar', function () {
    $usuario = User::factory()->create();
    $servicio = app(AplicacionesService::class);
    $id = fn (string $codigo): int => Aplicacion::query()->where('codigo', $codigo)->value('id');
    test()->actingAs($usuario);

    $servicio->ponerPrincipal($usuario, 1, OrigenAplicacion::Techquk, $id('gmail'));
    expect(nombresDelPanel($servicio->delMuelle($usuario)))->toBe(['Gmail', 'Excel', 'WhatsApp']);

    $servicio->ponerPrincipal($usuario, 1, OrigenAplicacion::Techquk, $id('whatsapp'));
    expect(nombresDelPanel($servicio->delMuelle($usuario)))->toBe(['WhatsApp', 'Excel', 'Gmail']);

    $propia = AplicacionPropia::factory()->create(['user_id' => $usuario->id, 'nombre' => 'Mi tablero']);
    $servicio->ponerPrincipal($usuario, 2, OrigenAplicacion::Propia, $propia->id);

    $panel = $servicio->delPanel($usuario);
    expect(nombresDelPanel($panel['muelle']))->toBe(['WhatsApp', 'Mi tablero', 'Gmail'])
        ->and(nombresDelPanel($panel['cuadricula']['techquk']))->toBe(['Claude', 'Excel', 'Word', 'PowerPoint', 'Outlook', 'OneDrive', 'Google Drive', 'LinkedIn', 'Teams', 'Google Meet', 'Excalidraw'])
        ->and(nombresDelPanel($panel['cuadricula']['propias']))->toBe([])
        ->and(nombresDelPanel($servicio->delMuelle(User::factory()->create())))->toBe(['Claude', 'Excel', 'WhatsApp']);
});

test('elegir las suyas no cambia las que otra persona ya eligió', function () {
    $una = User::factory()->create();
    $otra = User::factory()->create();
    $servicio = app(AplicacionesService::class);
    $id = fn (string $codigo): int => Aplicacion::query()->where('codigo', $codigo)->value('id');
    test()->actingAs($otra);
    $servicio->ponerPrincipal($otra, 1, OrigenAplicacion::Techquk, $id('linkedin'));
    $antes = AplicacionPrincipal::query()->where('user_id', $otra->id)->orderBy('posicion')->get(['id', 'posicion', 'aplicacion_id'])->toArray();

    test()->actingAs($una);
    $servicio->ponerPrincipal($una, 1, OrigenAplicacion::Techquk, $id('gmail'));
    $servicio->ponerPrincipal($una, 2, OrigenAplicacion::Techquk, $id('linkedin'));

    expect(AplicacionPrincipal::query()->where('user_id', $otra->id)->orderBy('posicion')->get(['id', 'posicion', 'aplicacion_id'])->toArray())->toBe($antes)
        ->and(nombresDelPanel($servicio->delMuelle($otra)))->toBe(['LinkedIn', 'Excel', 'WhatsApp'])
        ->and(nombresDelPanel($servicio->delMuelle($una)))->toBe(['Gmail', 'LinkedIn', 'WhatsApp']);
});

test('reordenar la base vieja pasa por el modelo: sube la versión de cada una (también al poner su programa)', function () {
    Aplicacion::query()->whereNotIn('codigo', ['word', 'excel', 'powerpoint'])->delete();
    foreach (['word' => 1, 'excel' => 2, 'powerpoint' => 3] as $codigo => $orden) {
        Aplicacion::query()->where('codigo', $codigo)->update(['orden' => $orden, 'version' => 1]);
    }

    test()->seed(CatalogosSeeder::class);

    expect(Aplicacion::query()->whereIn('codigo', ['word', 'excel', 'powerpoint'])->pluck('version')->all())->each->toBeGreaterThan(1);
});

test('si una principal ya no está en su panel, su lugar lo toma la siguiente del orden', function () {
    $usuario = User::factory()->create();
    $servicio = app(AplicacionesService::class);
    test()->actingAs($usuario);
    $gmail = Aplicacion::query()->where('codigo', 'gmail')->sole();
    $propia = AplicacionPropia::factory()->create(['user_id' => $usuario->id, 'nombre' => 'Mi tablero']);
    $servicio->ponerPrincipal($usuario, 2, OrigenAplicacion::Techquk, $gmail->id);
    $servicio->ponerPrincipal($usuario, 3, OrigenAplicacion::Propia, $propia->id);

    AplicacionOculta::query()->create(['user_id' => $usuario->id, 'aplicacion_id' => $gmail->id]);
    expect(nombresDelPanel($servicio->delMuelle($usuario)))->toBe(['Claude', 'Excel', 'Mi tablero']);

    $propia->delete();
    expect(nombresDelPanel($servicio->delMuelle($usuario)))->toBe(['Claude', 'Excel', 'WhatsApp']);

    Aplicacion::query()->where('codigo', 'claude')->update(['es_activo' => false]);
    expect(nombresDelPanel($servicio->delMuelle($usuario)))->toBe(['Excel', 'WhatsApp', 'Word']);
});

test('no se pone entre las principales una que no está en su panel, ni en un lugar fuera de los 3', function () {
    $usuario = User::factory()->create();
    $servicio = app(AplicacionesService::class);
    test()->actingAs($usuario);
    $word = Aplicacion::query()->where('codigo', 'word')->sole();
    AplicacionOculta::query()->create(['user_id' => $usuario->id, 'aplicacion_id' => $word->id]);
    $ajena = AplicacionPropia::factory()->create(['user_id' => User::factory()->create()->id]);

    expect(fn () => $servicio->ponerPrincipal($usuario, 1, OrigenAplicacion::Techquk, $word->id))->toThrow(AplicacionRechazada::class)
        ->and(fn () => $servicio->ponerPrincipal($usuario, 1, OrigenAplicacion::Propia, $ajena->id))->toThrow(AplicacionRechazada::class)
        ->and(fn () => $servicio->ponerPrincipal($usuario, 4, OrigenAplicacion::Techquk, $word->id))->toThrow(AplicacionRechazada::class, 'lugar 1, 2 o 3')
        ->and(AplicacionPrincipal::query()->count())->toBe(0);
});

test('la cuadrícula completa: las de Techquk en su orden y las propias por nombre, con su conteo', function () {
    $usuario = User::factory()->create();
    AplicacionPropia::factory()->create(['user_id' => $usuario->id, 'nombre' => 'zeta']);
    $alfa = AplicacionPropia::factory()->create(['user_id' => $usuario->id, 'nombre' => 'Alfa']);
    usarAplicacion($usuario, OrigenAplicacion::Propia, $alfa->id, 2);

    $cuadricula = app(AplicacionesService::class)->deLaCuadricula($usuario);

    expect(nombresDelPanel($cuadricula['techquk']))->toBe(['Claude', 'Excel', 'WhatsApp', 'Word', 'PowerPoint', 'Outlook', 'OneDrive', 'Google Drive', 'LinkedIn', 'Gmail', 'Teams', 'Google Meet', 'Excalidraw'])
        ->and(nombresDelPanel($cuadricula['propias']))->toBe(['Alfa', 'zeta'])
        ->and($cuadricula['propias'][0]->usos)->toBe(2)
        ->and($cuadricula['propias'][0]->version)->toBe(1)
        ->and($cuadricula['techquk'][0]->origen)->toBe(OrigenAplicacion::Techquk);
});

test('una aplicación propia de una persona no la ve otra ni puede contar su uso', function () {
    $duena = User::factory()->create();
    $otra = User::factory()->create();
    $propia = AplicacionPropia::factory()->create(['user_id' => $duena->id, 'nombre' => 'Solo mía']);

    expect(nombresDelPanel(app(AplicacionesService::class)->deLaCuadricula($otra)['propias']))->toBe([])
        ->and(fn () => usarAplicacion($otra, OrigenAplicacion::Propia, $propia->id))->toThrow(AplicacionRechazada::class)
        ->and(UsoAplicacion::query()->count())->toBe(0);
});

test('no se cuenta el uso de una oculta ni de una desactivada', function () {
    $usuario = User::factory()->create();
    $word = Aplicacion::query()->where('codigo', 'word')->sole();
    $excel = Aplicacion::query()->where('codigo', 'excel')->sole();
    AplicacionOculta::query()->create(['user_id' => $usuario->id, 'aplicacion_id' => $word->id]);
    $excel->update(['es_activo' => false]);

    expect(fn () => usarAplicacion($usuario, OrigenAplicacion::Techquk, $word->id))->toThrow(AplicacionRechazada::class)
        ->and(fn () => usarAplicacion($usuario, OrigenAplicacion::Techquk, $excel->id))->toThrow(AplicacionRechazada::class);
});

test('la base exige enlaces https, un ícono de la lista, un uso de una sola aplicación y principales sin repetir', function (string $sql) {
    $usuario = User::factory()->create();
    $sistema = app(UsuarioSistema::class)->id();
    $word = Aplicacion::query()->where('codigo', 'word')->value('id');
    $propia = AplicacionPropia::factory()->create(['user_id' => $usuario->id])->id;

    expect(fn () => DB::statement(strtr($sql, [':u' => $usuario->id, ':s' => $sistema, ':w' => $word, ':p' => $propia])))->toThrow(QueryException::class);
})->with([
    'enlace http' => ["INSERT INTO aplicaciones (codigo, nombre, enlace, icono, created_by) VALUES ('x', 'X', 'http://x.example', 'generica', :s)"],
    'enlace sin dominio' => ["INSERT INTO aplicaciones_propias (user_id, nombre, enlace, created_by) VALUES (:u, 'X', 'https://local', :s)"],
    'ícono fuera de la lista' => ["INSERT INTO aplicaciones_propias (user_id, nombre, enlace, icono, created_by) VALUES (:u, 'X', 'https://x.example', 'slack', :s)"],
    'nombre repetido sin mayúsculas' => ['INSERT INTO aplicaciones_propias (user_id, nombre, enlace, created_by) SELECT user_id, upper(nombre), enlace, :s FROM aplicaciones_propias WHERE id = :p'],
    'uso de las dos' => ['INSERT INTO usos_aplicacion (user_id, aplicacion_id, aplicacion_propia_id, usos, ultimo_uso_at, created_by) VALUES (:u, :w, :p, 1, now(), :s)'],
    'uso de ninguna' => ['INSERT INTO usos_aplicacion (user_id, usos, ultimo_uso_at, created_by) VALUES (:u, 1, now(), :s)'],
    'uso de la propia de otra persona' => ['INSERT INTO usos_aplicacion (user_id, aplicacion_propia_id, usos, ultimo_uso_at, created_by) VALUES (:s, :p, 1, now(), :s)'],
    'ocultar dos veces' => ['INSERT INTO aplicaciones_ocultas (user_id, aplicacion_id, created_by) VALUES (:u, :w, :s), (:u, :w, :s)'],
    'principal de las dos' => ['INSERT INTO aplicaciones_principales (user_id, posicion, aplicacion_id, aplicacion_propia_id, created_by) VALUES (:u, 1, :w, :p, :s)'],
    'principal de ninguna' => ['INSERT INTO aplicaciones_principales (user_id, posicion, created_by) VALUES (:u, 1, :s)'],
    'principal en el lugar 4' => ['INSERT INTO aplicaciones_principales (user_id, posicion, aplicacion_id, created_by) VALUES (:u, 4, :w, :s)'],
    'dos en el mismo lugar' => ['INSERT INTO aplicaciones_principales (user_id, posicion, aplicacion_id, aplicacion_propia_id, created_by) VALUES (:u, 1, :w, NULL, :s), (:u, 1, NULL, :p, :s)'],
    'la misma en dos lugares' => ['INSERT INTO aplicaciones_principales (user_id, posicion, aplicacion_id, created_by) VALUES (:u, 1, :w, :s), (:u, 2, :w, :s)'],
    'principal la propia de otra persona' => ['INSERT INTO aplicaciones_principales (user_id, posicion, aplicacion_propia_id, created_by) VALUES (:s, 1, :p, :s)'],
    'orden de las dos' => ['INSERT INTO aplicaciones_orden (user_id, aplicacion_id, aplicacion_propia_id, posicion, created_by) VALUES (:u, :w, :p, 1, :s)'],
    'orden en la posición 0' => ['INSERT INTO aplicaciones_orden (user_id, aplicacion_id, posicion, created_by) VALUES (:u, :w, 0, :s)'],
    'orden de la propia de otra persona' => ['INSERT INTO aplicaciones_orden (user_id, aplicacion_propia_id, posicion, created_by) VALUES (:s, :p, 1, :s)'],
    'programa de escritorio fuera de la lista' => ["UPDATE aplicaciones SET escritorio = 'javascript' WHERE id = :w"],
    'programa de una propia fuera de la lista' => ["UPDATE aplicaciones_propias SET escritorio = 'file' WHERE id = :p"],
    'escritorio de las dos' => ['INSERT INTO aplicaciones_en_escritorio (user_id, aplicacion_id, aplicacion_propia_id, created_by) VALUES (:u, :w, :p, :s)'],
    'escritorio de la propia de otra persona' => ['INSERT INTO aplicaciones_en_escritorio (user_id, aplicacion_propia_id, created_by) VALUES (:s, :p, :s)'],
    'ícono nuevo fuera de la lista' => ["INSERT INTO aplicaciones (codigo, nombre, enlace, icono, created_by) VALUES ('x', 'X', 'https://x.example', 'slack', :s)"],
]);

test('quitar una propia borra su conteo; una de Techquk usada no se borra, se desactiva', function () {
    $usuario = User::factory()->create();
    $propia = AplicacionPropia::factory()->create(['user_id' => $usuario->id]);
    $word = Aplicacion::query()->where('codigo', 'word')->sole();
    usarAplicacion($usuario, OrigenAplicacion::Propia, $propia->id);
    usarAplicacion($usuario, OrigenAplicacion::Techquk, $word->id);

    $propia->delete();

    expect(UsoAplicacion::query()->whereNotNull('aplicacion_propia_id')->count())->toBe(0)
        ->and(fn () => $word->delete())->toThrow(QueryException::class);
});

test('cada persona ordena su cuadrícula: soltar una sobre otra de su sección las intercambia, solo para ella (1.4z)', function () {
    $usuario = User::factory()->create();
    $otra = User::factory()->create();
    $servicio = app(AplicacionesService::class);
    $id = fn (string $codigo): int => Aplicacion::query()->where('codigo', $codigo)->value('id');
    test()->actingAs($usuario);

    $servicio->mover($usuario, OrigenAplicacion::Techquk, $id('gmail'), $id('word'));

    expect(array_slice(nombresDelPanel($servicio->deLaCuadricula($usuario)['techquk']), 0, 5))->toBe(['Claude', 'Excel', 'WhatsApp', 'Gmail', 'PowerPoint'])
        ->and(nombresDelPanel($servicio->deLaCuadricula($usuario)['techquk'])[9])->toBe('Word')
        ->and(array_slice(nombresDelPanel($servicio->deLaCuadricula($otra)['techquk']), 0, 4))->toBe(['Claude', 'Excel', 'WhatsApp', 'Word']);
});

test('una aplicación que se suma después va al final de su sección; las propias se ordenan entre sí', function () {
    $usuario = User::factory()->create();
    $servicio = app(AplicacionesService::class);
    test()->actingAs($usuario);
    $alfa = AplicacionPropia::factory()->create(['user_id' => $usuario->id, 'nombre' => 'Alfa']);
    $beta = AplicacionPropia::factory()->create(['user_id' => $usuario->id, 'nombre' => 'Beta']);
    $servicio->mover($usuario, OrigenAplicacion::Techquk, Aplicacion::query()->where('codigo', 'gmail')->value('id'), Aplicacion::query()->where('codigo', 'claude')->value('id'));
    $servicio->mover($usuario, OrigenAplicacion::Propia, $alfa->id, $beta->id);

    Aplicacion::factory()->create(['nombre' => 'Primera de administración', 'orden' => 0]);

    expect(nombresDelPanel($servicio->deLaCuadricula($usuario)['techquk'])[0])->toBe('Gmail')
        ->and(collect(nombresDelPanel($servicio->deLaCuadricula($usuario)['techquk']))->last())->toBe('Primera de administración')
        ->and(nombresDelPanel($servicio->deLaCuadricula($usuario)['propias']))->toBe(['Beta', 'Alfa']);
});

test('no se mueve una que no está en su panel, ni una de otra persona', function () {
    $usuario = User::factory()->create();
    $servicio = app(AplicacionesService::class);
    test()->actingAs($usuario);
    $ajena = AplicacionPropia::factory()->create(['user_id' => User::factory()->create()->id]);
    $suya = AplicacionPropia::factory()->create(['user_id' => $usuario->id]);

    expect(fn () => $servicio->mover($usuario, OrigenAplicacion::Propia, $ajena->id, $suya->id))->toThrow(AplicacionRechazada::class)
        ->and(OrdenAplicacion::query()->count())->toBe(0);
});

test('cada persona elige abrir en el escritorio una que tiene programa; sin elegir, en la web (1.4z)', function () {
    $usuario = User::factory()->create();
    $servicio = app(AplicacionesService::class);
    test()->actingAs($usuario);
    $word = Aplicacion::query()->where('codigo', 'word')->sole();
    $deWord = fn (User $persona): AplicacionDelPanel => collect($servicio->deLaCuadricula($persona)['techquk'])->firstWhere('id', $word->id);

    expect($deWord($usuario)->destino())->toBe('https://www.office.com/launch/word');

    $servicio->abrirEn($usuario, OrigenAplicacion::Techquk, $word->id, true);
    $servicio->abrirEn($usuario, OrigenAplicacion::Techquk, $word->id, true);
    expect($deWord($usuario)->destino())->toBe('ms-word:')
        ->and($deWord(User::factory()->create())->destino())->toBe('https://www.office.com/launch/word');

    $servicio->abrirEn($usuario, OrigenAplicacion::Techquk, $word->id, false);
    expect($deWord($usuario)->destino())->toBe('https://www.office.com/launch/word');
});

test('una sin programa de escritorio no se abre en el escritorio', function () {
    $usuario = User::factory()->create();
    test()->actingAs($usuario);

    expect(fn () => app(AplicacionesService::class)->abrirEn($usuario, OrigenAplicacion::Techquk, Aplicacion::query()->where('codigo', 'gmail')->value('id'), true))
        ->toThrow(AplicacionRechazada::class, 'no tiene versión de escritorio');
});

test('las de fábrica traen su programa; si administración se lo quita, la semilla no se lo devuelve', function () {
    expect(Aplicacion::query()->whereNotNull('escritorio')->pluck('codigo')->sort()->values()->all())
        ->toBe(['claude', 'excel', 'outlook', 'powerpoint', 'teams', 'whatsapp', 'word']);

    Aplicacion::query()->where('codigo', 'word')->update(['escritorio' => null]);
    Aplicacion::query()->where('codigo', 'excel')->update(['escritorio' => 'onenote']);
    test()->seed(CatalogosSeeder::class);

    expect(Aplicacion::query()->where('codigo', 'word')->sole()->escritorio)->toBeNull()
        ->and(Aplicacion::query()->where('codigo', 'excel')->sole()->escritorio)->toBe(ProgramaEscritorio::Onenote);
});

test('no se abre en el escritorio una propia de otra persona', function () {
    $usuario = User::factory()->create();
    test()->actingAs($usuario);
    $ajena = AplicacionPropia::factory()->create(['user_id' => User::factory()->create()->id, 'escritorio' => 'zoom']);

    expect(fn () => app(AplicacionesService::class)->abrirEn($usuario, OrigenAplicacion::Propia, $ajena->id, true))->toThrow(AplicacionRechazada::class, 'no está en tu panel');
});

test('Whiteboard ya no está: la semilla no lo crea y su programa de escritorio no es de la lista (2026-10-30)', function () {
    expect(Aplicacion::query()->where('codigo', 'whiteboard')->exists())->toBeFalse()
        ->and(array_map(fn ($programa): string => $programa->value, ProgramaEscritorio::cases()))->not->toContain('whiteboard')
        ->and(fn () => DB::transaction(fn () => Aplicacion::query()->where('codigo', 'word')->update(['escritorio' => 'whiteboard'])))
        ->toThrow(QueryException::class, 'aplicaciones_escritorio_check');
});
