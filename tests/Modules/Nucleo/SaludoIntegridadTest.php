<?php

declare(strict_types=1);

use App\Modules\Nucleo\database\seeders\CatalogosSeeder;
use App\Modules\Nucleo\database\seeders\ParametrosSeeder;
use App\Modules\Nucleo\Excepciones\ParametroRechazado;
use App\Modules\Nucleo\Models\AuditoriaCambio;
use App\Modules\Nucleo\Models\CategoriaFrase;
use App\Modules\Nucleo\Models\FraseSaludo;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\EjecutorAcciones;
use App\Modules\Nucleo\Services\ParametrosService;
use App\Modules\Nucleo\Services\PreferenciasUsuarioService;
use App\Modules\Nucleo\Services\UsuarioSistema;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;

/*
 * Plan 1.4b, paso 1: la base protege frases, preferencias y cumpleaños aunque se salte el servicio (regla 5), y los
 * parámetros del saludo validan lo que se escribe. Las filas que se ponen a prueba se escriben por query builder.
 */

/**
 * @param  array<string, mixed>  $cambios
 */
function insertarFraseCruda(array $cambios): int
{
    return DB::table('frases_saludo')->insertGetId([
        'codigo' => fake()->unique()->lexify('cruda-????????'),
        'tipo' => 'frase',
        'categoria_frase_id' => CategoriaFrase::factory()->create()->id,
        'idioma' => 'es',
        'texto' => 'Una frase',
        'created_by' => app(UsuarioSistema::class)->id(),
        ...$cambios,
    ]);
}

test('el seeder crea las cuatro categorías de frases', function () {
    config(['nucleo.ubigeos.csv' => '/no/existe.csv']);
    test()->seed(CatalogosSeeder::class);

    expect(CategoriaFrase::query()->orderBy('codigo')->pluck('codigo')->all())->toBe(['autores', 'propias', 'refranes', 'versiculos']);
});

test('una frase y un saludo bien formados se guardan', function () {
    insertarFraseCruda([]);
    insertarFraseCruda(['tipo' => 'saludo', 'categoria_frase_id' => null, 'texto' => 'Bonjour, {nombre}', 'idioma' => 'fr', 'momento' => 'manana']);
    insertarFraseCruda(['fuente' => 'Proverbios 16:3 (RVR1960)', 'dia_semana' => 1, 'area' => 'crm', 'ocasion' => 'navidad']);

    expect(FraseSaludo::query()->count())->toBe(3);
});

test('la base rechaza una frase mal formada', function (array $cambios, string $restriccion) {
    expect(fn () => insertarFraseCruda($cambios))->toThrow(QueryException::class, $restriccion);
})->with([
    'saludo con categoría' => [['tipo' => 'saludo', 'texto' => 'Hola, {nombre}'], 'frases_saludo_categoria_check'],
    'frase sin categoría' => [['categoria_frase_id' => null], 'frases_saludo_categoria_check'],
    'saludo sin {nombre}' => [['tipo' => 'saludo', 'categoria_frase_id' => null, 'texto' => 'Hola'], 'frases_saludo_nombre_check'],
    'tipo desconocido' => [['tipo' => 'cita', 'categoria_frase_id' => null], 'frases_saludo_valores_check'],
    'idioma desconocido' => [['idioma' => 'it'], 'frases_saludo_valores_check'],
    'momento desconocido' => [['momento' => 'madrugada'], 'frases_saludo_valores_check'],
    'día 8' => [['dia_semana' => 8], 'frases_saludo_valores_check'],
    'área del núcleo' => [['area' => 'nucleo'], 'frases_saludo_valores_check'],
    'Halloween' => [['ocasion' => 'halloween'], 'frases_saludo_valores_check'],
    'agradecimiento con categoría' => [['tipo' => 'agradecimiento', 'texto' => 'Gracias por {anios} años'], 'frases_saludo_categoria_check'],
    'agradecimiento sin {anios}' => [['tipo' => 'agradecimiento', 'categoria_frase_id' => null, 'texto' => 'Gracias a Dios'], 'frases_saludo_agradecimiento_check'],
    'agradecimiento de más de 60 letras' => [['tipo' => 'agradecimiento', 'categoria_frase_id' => null, 'texto' => 'Gracias a Dios por {anios} años de camino, de trabajo y de bendición'], 'frases_saludo_agradecimiento_check'],
    'texto en blanco' => [['texto' => '  '], 'frases_saludo_texto_check'],
    'fuente en blanco' => [['fuente' => ' '], 'frases_saludo_texto_check'],
]);

test('sin preferencias cambiadas, la ambientación viene prendida; las categorías apagadas por persona ya no existen (plan 1.4aa)', function () {
    expect(app(PreferenciasUsuarioService::class)->siNo(User::factory()->create(), 'apariencia.ambientacion'))->toBeTrue()
        ->and(Schema::hasTable('categorias_frase_usuario'))->toBeFalse();
});

test('el cumpleaños se guarda sin año, los dos o ninguno, y en una fecha que exista', function (?int $dia, ?int $mes, bool $vale) {
    $usuario = User::factory()->create();
    // En su propia transacción: un rechazo no deja abortada la de la prueba.
    $guardar = fn () => DB::transaction(fn () => DB::table('users')->where('id', $usuario->id)->update(['cumpleanos_dia' => $dia, 'cumpleanos_mes' => $mes]));

    $vale ? $guardar() : expect($guardar)->toThrow(QueryException::class, 'users_cumpleanos_check');

    expect($usuario->refresh()->cumpleanos_dia)->toBe($vale ? $dia : null);
})->with([
    'ninguno' => [null, null, true],
    '29 de febrero' => [29, 2, true],
    '31 de diciembre' => [31, 12, true],
    'solo el día' => [15, null, false],
    'solo el mes' => [null, 6, false],
    '30 de febrero' => [30, 2, false],
    '31 de abril' => [31, 4, false],
    'mes 13' => [1, 13, false],
    'día 0' => [0, 1, false],
]);

test('el cumpleaños no entra en la bitácora de la cuenta', function () {
    $usuario = User::factory()->create();
    $usuario->update(['cumpleanos_dia' => 9, 'cumpleanos_mes' => 3, 'email' => 'nuevo@techquk.test']);

    $cambios = AuditoriaCambio::query()->where('auditable_type', 'user')->where('auditable_id', $usuario->id)->where('evento', 'actualizado')->get();
    $campos = $cambios->flatMap(fn (AuditoriaCambio $cambio): array => array_keys((array) $cambio->valores_nuevos))->all();

    expect($campos)->toContain('email')
        ->not->toContain('cumpleanos_dia')
        ->not->toContain('cumpleanos_mes');
});

// --- Parámetros (D1b, D7) ---

test('el porcentaje de español va de 0 a 100', function () {
    test()->seed(ParametrosSeeder::class);
    $parametros = app(ParametrosService::class);

    expect(fn () => $parametros->cambiarDesdeTexto('interfaz.saludo_porcentaje_espanol', '101', 1))->toThrow(ParametroRechazado::class, 'entre 0 y 100');

    $parametros->cambiarDesdeTexto('interfaz.saludo_porcentaje_espanol', '100', 1);

    expect(app(ParametrosService::class)->entero('interfaz.saludo_porcentaje_espanol'))->toBe(100);
});

test('el aniversario empieza «sin fecha» y acepta solo una fecha completa que exista', function () {
    test()->seed(ParametrosSeeder::class);
    $parametros = app(ParametrosService::class);

    expect($parametros->texto('interfaz.aniversario_techquk'))->toBe('sin fecha')
        ->and(fn () => $parametros->cambiarDesdeTexto('interfaz.aniversario_techquk', '05-15', 1))->toThrow(ParametroRechazado::class, 'AAAA-MM-DD')
        ->and(fn () => $parametros->cambiarDesdeTexto('interfaz.aniversario_techquk', '2013-02-29', 1))->toThrow(ParametroRechazado::class, 'AAAA-MM-DD');

    $parametros->cambiarDesdeTexto('interfaz.aniversario_techquk', '2012-02-29', 1);
    expect(app(ParametrosService::class)->texto('interfaz.aniversario_techquk'))->toBe('2012-02-29');

    app(ParametrosService::class)->cambiarDesdeTexto('interfaz.aniversario_techquk', ' Sin fecha ', 2);
    expect(app(ParametrosService::class)->texto('interfaz.aniversario_techquk'))->toBe('sin fecha');
});

// --- Catálogo editable (D1) ---

test('una frase se crea por su acción de catálogo, y omitir un campo que se puede vaciar falla', function () {
    test()->seed(ParametrosSeeder::class);
    Permission::findOrCreate('catalogos.editar', 'web');
    $editor = User::factory()->create()->givePermissionTo('catalogos.editar');
    $categoria = CategoriaFrase::factory()->create();
    $datos = [
        'codigo' => 'lunes-es-01', 'tipo' => 'frase', 'categoria_frase_id' => $categoria->id, 'idioma' => 'es',
        'texto' => 'Buen comienzo de semana.', 'fuente' => null, 'momento' => 'manana', 'fuera_de_horario' => null, 'sesion' => null, 'dia_semana' => 1, 'area' => null, 'ocasion' => null,
    ];

    app(EjecutorAcciones::class)->ejecutar('nucleo.frases-saludo.crear', $datos, $editor);
    $frase = FraseSaludo::query()->where('codigo', 'lunes-es-01')->firstOrFail();

    expect($frase->dia_semana)->toBe(1)
        ->and($frase->categoria?->is($categoria))->toBeTrue()
        ->and(fn () => app(EjecutorAcciones::class)->ejecutar('nucleo.frases-saludo.crear', [...array_diff_key($datos, ['fuente' => 0]), 'codigo' => 'otra'], $editor))
        ->toThrow(ValidationException::class)
        ->and(fn () => app(EjecutorAcciones::class)->ejecutar('nucleo.frases-saludo.crear', [...$datos, 'codigo' => 'otra', 'area' => 'nucleo'], $editor))
        ->toThrow(ValidationException::class);
});
