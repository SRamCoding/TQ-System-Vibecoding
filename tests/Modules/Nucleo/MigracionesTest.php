<?php

declare(strict_types=1);

use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Services\UsuarioSistema;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('las migraciones del núcleo corren después de las de Laravel', function () {
    // Laravel ordena por nombre de archivo todas las rutas juntas. El núcleo depende de users y de las
    // demás tablas base de Laravel (0001_01_01_*). Las de paquetes (spatie, Pulse…) no se exigen: se
    // publican con la fecha del día y no dependen del núcleo.
    $raiz = dirname(__DIR__, 3);
    $laravel = array_map('basename', glob("{$raiz}/database/migrations/0001_01_01_*.php") ?: []);
    $nucleo = array_map('basename', glob("{$raiz}/app/Modules/Nucleo/database/migrations/*.php") ?: []);

    expect($laravel)->not->toBeEmpty()
        ->and($nucleo)->not->toBeEmpty()
        ->and(min($nucleo) > max($laravel))->toBeTrue(
            sprintf('%s ordena antes que %s.', min($nucleo), max($laravel)),
        );
});

test('la sesión de la base usa la zona horaria de la aplicación', function () {
    expect(DB::scalar("select current_setting('TimeZone')"))->toBe(config('app.timezone'))
        ->and(config('app.timezone'))->toBe('America/Lima');
});

test('el correo es único sin importar mayúsculas', function () {
    User::factory()->create(['email' => 'ana@techquk.test']);
    User::factory()->create(['email' => 'Ana@TechQuk.test']);
})->throws(QueryException::class);

test('la extensión unaccent está activa', function () {
    expect(DB::scalar("select unaccent('Línea de acción')"))->toBe('Linea de accion');
});

test('existe un solo usuario de sistema y la base impide un segundo', function () {
    $sistema = User::query()->where('es_sistema', true)->sole();

    expect(app(UsuarioSistema::class)->id())->toBe($sistema->id);

    User::factory()->create(['es_sistema' => true]);
})->throws(QueryException::class);

test('la macro auditoria crea sus cuatro columnas con índices y FK a users', function () {
    Schema::create('prueba_auditoria', function (Blueprint $table) {
        $table->id();
        $table->auditoria();
    });

    expect(Schema::getColumnListing('prueba_auditoria'))
        ->toContain('created_by', 'created_at', 'updated_by', 'updated_at');

    $indexadas = collect(Schema::getIndexes('prueba_auditoria'))->pluck('columns')->flatten()->all();
    expect($indexadas)->toContain('created_by', 'updated_by');

    $foraneas = collect(Schema::getForeignKeys('prueba_auditoria'))->pluck('foreign_table')->unique()->all();
    expect($foraneas)->toBe(['users']);

    $creadoNulo = collect(Schema::getColumns('prueba_auditoria'))->firstWhere('name', 'created_by')['nullable'];
    expect($creadoNulo)->toBeFalse();
});
