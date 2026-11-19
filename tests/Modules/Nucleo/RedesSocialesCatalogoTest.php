<?php

declare(strict_types=1);

use App\Modules\Nucleo\database\seeders\CatalogosSeeder;
use App\Modules\Nucleo\database\seeders\ParametrosSeeder;
use App\Modules\Nucleo\Enums\Catalogo;
use App\Modules\Nucleo\Enums\GrupoCatalogo;
use App\Modules\Nucleo\Enums\IconoRed;
use App\Modules\Nucleo\Filament\Pages\Catalogos;
use App\Modules\Nucleo\Models\RedSocial;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\EjecutorAcciones;
use App\Modules\Nucleo\Services\UsuarioSistema;
use BladeUI\Icons\Factory as IconFactory;
use Filament\Facades\Filament;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

/*
 * Plan 1.4o, D1: el catálogo de redes sociales, con su ícono y los dominios de los que puede ser un enlace suyo.
 */

beforeEach(function () {
    config(['nucleo.ubigeos.csv' => '/no/existe.csv']);
    test()->seed(ParametrosSeeder::class);
    test()->seed(CatalogosSeeder::class);
    Permission::findOrCreate('catalogos.ver', 'web');
    Permission::findOrCreate('catalogos.editar', 'web');
});

/**
 * @param  array<string, mixed>  $cambios
 * @return array<string, mixed>
 */
function filaRedSocial(array $cambios = []): array
{
    return ['codigo' => 'mastodon', 'nombre' => 'Mastodon', 'dominios' => 'mastodon.social', 'icono' => 'enlace', 'created_by' => app(UsuarioSistema::class)->id(), ...$cambios];
}

test('la semilla deja las siete redes y «Otra red» (plan 2.0, D5), cada una con su ícono; el sitio web y «Otra red» aceptan cualquier dominio', function () {
    $redes = RedSocial::query()->orderBy('id')->get();

    expect($redes->pluck('nombre')->all())->toBe(['LinkedIn', 'Facebook', 'Instagram', 'TikTok', 'YouTube', 'X (Twitter)', 'Sitio web', 'Otra red'])
        ->and($redes->whereNull('dominios')->pluck('codigo')->all())->toBe(['sitio_web', 'otra'])
        ->and($redes->firstWhere('codigo', 'youtube')?->dominios)->toBe('youtube.com,youtu.be')
        ->and($redes->firstWhere('codigo', 'linkedin')?->icono)->toBe(IconoRed::Linkedin);
});

test('cada ícono de red existe y los de marca son del repositorio', function (IconoRed $icono) {
    expect(fn () => app(IconFactory::class)->svg($icono->componente()))->not->toThrow(Throwable::class);

    if (! in_array($icono, [IconoRed::SitioWeb, IconoRed::Enlace], true)) {
        expect($icono->componente())->toStartWith('tq-red-')
            ->and(app_path('Modules/Nucleo/resources/svg/red-'.$icono->value.'.svg'))->toBeFile();
    }
})->with(IconoRed::cases());

test('la base acepta la lista de íconos de IconoRed y ninguno más', function () {
    foreach (IconoRed::cases() as $i => $icono) {
        DB::table('redes_sociales')->insert(filaRedSocial(['codigo' => "red_{$i}", 'icono' => $icono->value]));
    }

    expect(fn () => DB::table('redes_sociales')->insert(filaRedSocial(['icono' => 'whatsapp'])))
        ->toThrow(QueryException::class, 'redes_sociales_icono_check');
});

test('la base rechaza un valor imposible', function (array $cambios, string $restriccion) {
    expect(fn () => DB::table('redes_sociales')->insert(filaRedSocial($cambios)))
        ->toThrow(QueryException::class, $restriccion);
})->with([
    'código repetido' => [['codigo' => 'linkedin'], 'redes_sociales_codigo_unique'],
    'código en mayúsculas' => [['codigo' => 'Mastodon'], 'redes_sociales_codigo_check'],
    'nombre en blanco' => [['nombre' => '  '], 'redes_sociales_nombre_check'],
    'dominio sin punto' => [['dominios' => 'mastodon'], 'redes_sociales_dominios_check'],
    'dominio con esquema' => [['dominios' => 'https://mastodon.social'], 'redes_sociales_dominios_check'],
    'dominios con espacio' => [['dominios' => 'mastodon.social, mstdn.social'], 'redes_sociales_dominios_check'],
    'dominio en mayúsculas' => [['dominios' => 'Mastodon.social'], 'redes_sociales_dominios_check'],
    'versión cero' => [['version' => 0], 'redes_sociales_version_check'],
]);

test('se agrega una red desde la acción registrada, con varios dominios o sin ninguno', function () {
    $editor = User::factory()->create()->givePermissionTo('catalogos.editar');
    $ejecutor = app(EjecutorAcciones::class);

    $ejecutor->ejecutar('nucleo.redes-sociales.crear', ['codigo' => 'mastodon', 'nombre' => 'Mastodon', 'dominios' => 'mastodon.social,mstdn.social', 'icono' => 'enlace'], $editor);
    $ejecutor->ejecutar('nucleo.redes-sociales.crear', ['codigo' => 'blog', 'nombre' => 'Blog', 'dominios' => null, 'icono' => 'sitio_web'], $editor);

    expect(RedSocial::query()->where('codigo', 'mastodon')->sole())
        ->dominios->toBe('mastodon.social,mstdn.social')
        ->icono->toBe(IconoRed::Enlace)
        ->created_by->toBe($editor->id)
        ->and(RedSocial::query()->where('codigo', 'blog')->sole()->dominios)->toBeNull();
});

test('la acción rechaza dominios mal escritos y un ícono que no existe antes de llegar a la base', function (array $cambios, string $campo) {
    $editor = User::factory()->create()->givePermissionTo('catalogos.editar');

    expect(fn () => app(EjecutorAcciones::class)->ejecutar('nucleo.redes-sociales.crear', [
        'codigo' => 'mastodon', 'nombre' => 'Mastodon', 'dominios' => 'mastodon.social', 'icono' => 'enlace', ...$cambios,
    ], $editor))->toThrow(fn (ValidationException $e) => expect($e->errors())->toHaveKey($campo));
})->with([
    'con esquema' => [['dominios' => 'https://mastodon.social'], 'dominios'],
    'con espacio' => [['dominios' => 'mastodon.social, mstdn.social'], 'dominios'],
    'ícono inventado' => [['icono' => 'whatsapp'], 'icono'],
]);

test('el catálogo va con personas y lugares, y su tabla muestra el ícono de cada red', function () {
    Filament::setCurrentPanel('app');
    $usuario = User::factory()->create()->givePermissionTo('catalogos.ver');

    expect(Catalogo::RedesSociales->grupo())->toBe(GrupoCatalogo::PersonasYLugares);

    $html = Livewire::withQueryParams(['catalogo' => 'redes_sociales'])->actingAs($usuario)->test(Catalogos::class)
        ->assertCanSeeTableRecords(RedSocial::query()->get())
        ->html();

    // El trazo del SVG de LinkedIn, tal como está en el repositorio.
    expect($html)->toContain('M19 3a2 2 0 0 1 2 2v14');
});
