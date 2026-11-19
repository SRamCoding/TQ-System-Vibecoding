<?php

declare(strict_types=1);

use App\Modules\Crm\Cuentas\Models\Cuenta;
use App\Modules\Crm\Cuentas\Services\CuentasService;
use App\Modules\Crm\Oportunidades\Models\Oportunidad;
use App\Modules\Crm\Oportunidades\OportunidadesServiceProvider;
use App\Modules\Crm\Oportunidades\Services\FotoOportunidadService;
use App\Modules\Crm\Oportunidades\Services\OportunidadRechazada;
use App\Modules\Nucleo\database\seeders\NucleoSeeder;
use App\Modules\Nucleo\Excepciones\FotoRechazada;
use App\Modules\Nucleo\Models\Party;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\EjecutorAcciones;
use App\Modules\Nucleo\Services\FotoPerfilService;
use App\Modules\Nucleo\Services\HuerfanosAdjuntosService;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Storage;
use Tests\Modules\Crm\Oportunidades\Fixtures\OportunidadesDePrueba;

/*
 * Plan 2.21d, D2 y D13: la foto de referencia de la oportunidad, una sola, que cambian o quitan su ejecutivo y Gerencia
 * Comercial (quien edita todas), y que ve quien puede ver oportunidades. Empresas y personas inventadas.
 */

beforeEach(function () {
    config(['nucleo.ubigeos.csv' => '/no/existe.csv']);
    test()->seed(NucleoSeeder::class);
    OportunidadesDePrueba::sinExigirCotizacion();
    Filament::setCurrentPanel('app');
    Storage::fake(FotoPerfilService::DISCO);
});

function oportunidadConEjecutivo(User $ejecutivo): Oportunidad
{
    $cuenta = Cuenta::factory()->create([
        'ejecutivo_user_id' => $ejecutivo->givePermissionTo(CuentasService::PERMISO_ATENDER)->id,
        'party_id' => Party::factory()->juridica()->create(['razon_social' => 'Hospital Foto Inventado S.A.C.'])->id,
    ]);

    return OportunidadesDePrueba::crear($cuenta->id, ['ejecutivo_user_id' => $ejecutivo->id]);
}

function pngDeLaOportunidad(): string
{
    $ruta = (string) tempnam(sys_get_temp_dir(), 'foto');
    file_put_contents($ruta, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='));

    return $ruta;
}

function cambiarFotoDeLaOportunidad(Oportunidad $oportunidad, User $quien, string $archivo): void
{
    app(EjecutorAcciones::class)->ejecutar('crm.oportunidades.cambiar-foto', ['oportunidad_id' => $oportunidad->id, 'foto' => ['ruta' => $archivo, 'nombre' => 'sala.png']], $quien);
}

test('su ejecutivo sube la foto, la cambia (la anterior se borra) y la quita, sin tocar la versión', function () {
    $ejecutivo = User::factory()->create();
    $oportunidad = oportunidadConEjecutivo($ejecutivo);
    $version = $oportunidad->refresh()->version;

    cambiarFotoDeLaOportunidad($oportunidad, $ejecutivo, pngDeLaOportunidad());
    $primera = (string) $oportunidad->refresh()->foto_ruta;
    expect($primera)->toStartWith("fotos-oportunidad/{$oportunidad->id}/")->and($oportunidad->version)->toBe($version);

    cambiarFotoDeLaOportunidad($oportunidad, $ejecutivo, pngDeLaOportunidad());
    expect(Storage::disk(FotoPerfilService::DISCO)->exists($primera))->toBeFalse();

    app(EjecutorAcciones::class)->ejecutar('crm.oportunidades.quitar-foto', ['oportunidad_id' => $oportunidad->id], $ejecutivo);
    expect($oportunidad->refresh()->foto_ruta)->toBeNull()->and(Storage::disk(FotoPerfilService::DISCO)->allFiles())->toBe([]);
});

test('Gerencia Comercial (edita todas) también la cambia; otro ejecutivo no', function () {
    $oportunidad = oportunidadConEjecutivo(User::factory()->create());
    $gerencia = User::factory()->create()->givePermissionTo('crm.oportunidades.editar');
    $otro = User::factory()->create()->givePermissionTo(['crm.oportunidades.editar-propias', OportunidadesServiceProvider::PERMISO_VER]);

    cambiarFotoDeLaOportunidad($oportunidad, $gerencia, pngDeLaOportunidad());
    expect($oportunidad->refresh()->foto_ruta)->not->toBeNull()
        ->and(fn () => cambiarFotoDeLaOportunidad($oportunidad, $otro, pngDeLaOportunidad()))->toThrow(OportunidadRechazada::class, 'o Gerencia Comercial cambian su foto')
        ->and(fn () => app(EjecutorAcciones::class)->ejecutar('crm.oportunidades.quitar-foto', ['oportunidad_id' => $oportunidad->id], $otro))->toThrow(OportunidadRechazada::class)
        ->and(Storage::disk(FotoPerfilService::DISCO)->allFiles())->toHaveCount(1);
});

test('solo acepta imágenes; la limpieza de adjuntos no toca su carpeta', function () {
    $ejecutivo = User::factory()->create();
    $oportunidad = oportunidadConEjecutivo($ejecutivo);
    $texto = (string) tempnam(sys_get_temp_dir(), 'foto');
    file_put_contents($texto, 'no soy una imagen');

    expect(fn () => cambiarFotoDeLaOportunidad($oportunidad, $ejecutivo, $texto))->toThrow(FotoRechazada::class)
        ->and(HuerfanosAdjuntosService::esDeOtroServicio('fotos-oportunidad/1/x.png'))->toBeTrue();
});

test('la foto se sirve con sesión a quien ve oportunidades', function () {
    $ejecutivo = User::factory()->create();
    $oportunidad = oportunidadConEjecutivo($ejecutivo);
    cambiarFotoDeLaOportunidad($oportunidad, $ejecutivo, pngDeLaOportunidad());
    $url = (string) FotoOportunidadService::url($oportunidad->refresh());

    expect($url)->toContain("/imagenes/oportunidad/{$oportunidad->id}?v=");
    test()->actingAs(User::factory()->create())->get($url)->assertNotFound();
    test()->actingAs(User::factory()->create()->givePermissionTo(OportunidadesServiceProvider::PERMISO_VER))->get($url)
        ->assertOk()->assertHeader('Content-Type', 'image/png');
});
