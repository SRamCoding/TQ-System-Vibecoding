<?php

declare(strict_types=1);

use App\Modules\Nucleo\database\seeders\NucleoSeeder;
use App\Modules\Nucleo\Excepciones\PreferenciaRechazada;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Navegacion\AreaModulo;
use App\Modules\Nucleo\Navegacion\CajaModulo;
use App\Modules\Nucleo\Navegacion\ModulosDeLaBarra;
use App\Modules\Nucleo\Navegacion\RegistroModulos;
use App\Modules\Nucleo\Preferencias\RegistroPreferencias;
use App\Modules\Nucleo\Preferencias\TipoPreferencia;
use App\Modules\Nucleo\RegistroAcciones\EjecutorAcciones;
use App\Modules\Nucleo\Services\NavegacionService;
use App\Modules\Nucleo\Services\PreferenciasUsuarioService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/*
 * Plan 1.4g, D3: las preferencias que ya existían (barra, ancho, ambientación) viven en el registro; la lista de módulos
 * de la barra sigue en su tabla y el registro la lee y restablece por su fuente. El saludo no se configura (plan 1.4aa,
 * D5): la ambientación pasó a Apariencia.
 */

beforeEach(function () {
    config(['nucleo.ubigeos.csv' => '/no/existe.csv']);
    test()->seed(NucleoSeeder::class);

    // Solo las cajas de prueba: las del Núcleo («Mien», que ve todo el mundo) se prueban en MienTest.
    app()->instance(RegistroModulos::class, new RegistroModulos);
    $registro = app(RegistroModulos::class);
    $registro->registrar(new CajaModulo('agenda', 'Agenda', '/agenda', permiso: null, area: AreaModulo::Erp));
    $registro->registrar(new CajaModulo('notas', 'Notas', '/notas', permiso: null, area: AreaModulo::Erp));
});

function preferenciaDelNucleo(User $usuario, string $clave, string $valor): mixed
{
    return app(EjecutorAcciones::class)->ejecutar('nucleo.cambiar-preferencia', ['clave' => $clave, 'valor' => $valor], $usuario);
}

test('el núcleo declara la barra, su ancho, sus módulos y la ambientación, con su tipo y su defecto', function () {
    $registro = app(RegistroPreferencias::class);
    $resumen = fn (string $clave): array => [
        $registro->definicion($clave)?->tipo, $registro->definicion($clave)?->defecto,
        $registro->definicion($clave)?->soloEscritorio, $registro->definicion($clave)?->seMuestra,
    ];

    expect($resumen('navegacion.barra_modulos'))->toBe([TipoPreferencia::SiNo, false, true, true])
        ->and($resumen('navegacion.modulos_barra'))->toBe([TipoPreferencia::Lista, null, true, true])
        ->and($resumen('navegacion.ancho_barra'))->toBe([TipoPreferencia::Numero, 320, true, false])
        ->and($resumen('apariencia.ambientacion'))->toBe([TipoPreferencia::SiNo, true, false, true])
        ->and($registro->definicion('navegacion.modulos_barra')?->fuente)->toBe(ModulosDeLaBarra::class)
        ->and($registro->definicion('saludo.ingles'))->toBeNull()
        ->and($registro->definicion('saludo.frances'))->toBeNull()
        ->and($registro->definicion('saludo.ambientacion'))->toBeNull()
        ->and($registro->definicion('saludo.categorias_frase'))->toBeNull();
});

test('el ancho vale 0 (solo íconos) o de 200 a 480, y se guarda con la acción general', function (string $ancho, bool $vale) {
    $usuario = User::factory()->create();
    $cambiar = fn () => preferenciaDelNucleo($usuario, 'navegacion.ancho_barra', $ancho);

    if ($vale) {
        $cambiar();
        expect(app(NavegacionService::class)->anchoBarra($usuario))->toBe((int) $ancho);
    } else {
        expect($cambiar)->toThrow(PreferenciaRechazada::class);
        expect(app(NavegacionService::class)->anchoBarra($usuario))->toBe(320);
    }
})->with([
    'plegada' => ['0', true], 'mínimo' => ['200', true], 'máximo' => ['480', true], 'en medio' => ['260', true],
    'muy angosta' => ['150', false], 'muy ancha' => ['500', false], 'con decimales' => ['260.5', false],
]);

test('la lista entrega lo que se ve: los módulos a la vista', function () {
    $usuario = User::factory()->create();
    app(NavegacionService::class)->ocultar($usuario, ['notas']);

    expect(app(PreferenciasUsuarioService::class)->valor($usuario, 'navegacion.modulos_barra'))->toBe(['agenda']);
});

test('«Restablecer» de Navegación apaga la barra, la devuelve a 320 px y muestra todos los módulos; Apariencia queda igual', function () {
    $usuario = User::factory()->conBarraModulos()->create();
    preferenciaDelNucleo($usuario, 'navegacion.ancho_barra', '260');
    preferenciaDelNucleo($usuario, 'apariencia.ambientacion', 'false');
    app(NavegacionService::class)->ocultar($usuario, ['notas']);

    app(EjecutorAcciones::class)->ejecutar('nucleo.restablecer-preferencias', ['grupo' => 'navegacion'], $usuario);

    $navegacion = app(NavegacionService::class);
    expect($navegacion->barraVisible($usuario))->toBeFalse()
        ->and($navegacion->anchoBarra($usuario))->toBe(320)
        ->and($navegacion->ocultos($usuario))->toBe([])
        ->and(app(PreferenciasUsuarioService::class)->siNo($usuario, 'apariencia.ambientacion'))->toBeFalse();
});

test('«Restablecer» de Apariencia vuelve a prender la ambientación; el grupo Saludo ya no existe', function () {
    $usuario = User::factory()->create();
    preferenciaDelNucleo($usuario, 'apariencia.ambientacion', 'false');

    app(EjecutorAcciones::class)->ejecutar('nucleo.restablecer-preferencias', ['grupo' => 'apariencia'], $usuario);

    expect(app(PreferenciasUsuarioService::class)->siNo($usuario, 'apariencia.ambientacion'))->toBeTrue()
        ->and(fn () => app(EjecutorAcciones::class)->ejecutar('nucleo.restablecer-preferencias', ['grupo' => 'saludo'], $usuario))->toThrow(ValidationException::class);
});

test('no queda rastro de cómo se guardaban antes la barra, el ancho y el saludo', function () {
    $prohibidas = [
        'es_barra_modulos_visible', 'ancho_barra_lateral', 'preferencias_saludo', 'PreferenciaSaludo::', 'PreferenciaSaludoFactory',
        'cambiar-preferencias-saludo', 'CambiarPreferenciasSaludo', 'cambiar-ancho-barra-lateral', 'CambiarAnchoBarraLateral',
        'cambiarAnchoBarraLateral', 'cambiarSaludo', 'anchoBarraLateralInvalido', 'accionMiSaludo',
        // Plan 1.4aa, D5: el saludo ya no se configura.
        'ElegirCategoriasFrase', 'elegir-categorias-frase', 'CategoriasDeFrases', 'apagarCategorias', 'PreferenciasSaludo', 'CategoriaFraseUsuario',
    ];
    $hallazgos = [];

    foreach (['app', 'resources', 'config', 'database', 'routes', 'lang', 'tests'] as $carpeta) {
        $archivos = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(base_path($carpeta), FilesystemIterator::SKIP_DOTS));

        foreach ($archivos as $archivo) {
            if (! $archivo instanceof SplFileInfo || ! $archivo->isFile() || $archivo->getRealPath() === __FILE__) {
                continue;
            }

            $contenido = (string) file_get_contents($archivo->getPathname());

            foreach ($prohibidas as $palabra) {
                if (str_contains($contenido, $palabra)) {
                    $hallazgos[] = str_replace(base_path().'/', '', $archivo->getPathname())." → {$palabra}";
                }
            }
        }
    }

    expect($hallazgos)->toBe([]);
});

test('la migración de la 1.4aa pasa la ambientación guardada a Apariencia y borra las claves del saludo', function () {
    $migracion = require base_path('app/Modules/Nucleo/database/migrations/2026_11_09_000001_retirar_preferencias_del_saludo.php');
    $migracion->down();
    [$apagada, $conAmbas, $sinNada] = User::factory()->count(3)->create()->all();
    $fila = fn (User $usuario, string $clave, string $valor): bool => DB::table('preferencias_usuario')->insert(['user_id' => $usuario->id, 'clave' => $clave, 'valor' => $valor, 'created_by' => $usuario->id, 'created_at' => now()]);
    $fila($apagada, 'saludo.ambientacion', 'false');
    $fila($apagada, 'saludo.ingles', 'false');
    $fila($apagada, 'saludo.categorias_frase', '1');
    $fila($conAmbas, 'saludo.ambientacion', 'false');
    $fila($conAmbas, 'apariencia.ambientacion', 'true');
    $fila($sinNada, 'saludo.frances', 'false');

    $migracion->up();

    expect(DB::table('preferencias_usuario')->whereIn('user_id', [$apagada->id, $conAmbas->id, $sinNada->id])->orderBy('user_id')->get(['user_id', 'clave', 'valor'])->map(fn (object $f): array => [$f->user_id, $f->clave, $f->valor])->all())
        ->toBe([[$apagada->id, 'apariencia.ambientacion', 'false'], [$conAmbas->id, 'apariencia.ambientacion', 'true']])
        ->and(Schema::hasTable('categorias_frase_usuario'))->toBeFalse();
});
