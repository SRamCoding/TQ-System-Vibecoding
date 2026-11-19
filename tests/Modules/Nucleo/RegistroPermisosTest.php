<?php

declare(strict_types=1);

use App\Modules\Nucleo\database\seeders\PermisosSeeder;
use App\Modules\Nucleo\Seguridad\PermisosLegibles;
use App\Modules\Nucleo\Seguridad\RegistroPermisos;
use Spatie\Permission\Models\Permission;

/*
 * Plan 2.20a, N7 y N8: los módulos declaran los permisos que no son acciones, con su nombre legible, y los de negocio
 * se agrupan por área y módulo.
 */

test('un permiso declarado se crea al sembrar, con su nombre legible', function () {
    app(RegistroPermisos::class)->declarar('crm.prueba.ver', 'Ver la prueba');

    test()->seed(PermisosSeeder::class);

    expect(Permission::query()->where('name', 'crm.prueba.ver')->exists())->toBeTrue()
        ->and(PermisosLegibles::etiqueta('crm.prueba.ver'))->toBe('Ver la prueba');
});

test('los permisos de solo lectura del núcleo salen del registro', function () {
    test()->seed(PermisosSeeder::class);

    expect(Permission::query()->whereIn('name', ['catalogos.ver', 'parties.ver', 'usuarios.ver', 'organigrama.ver', 'salud.ver'])->count())->toBe(5)
        ->and(PermisosLegibles::etiqueta('parties.ver'))->toBe('Ver personas y empresas');
});

test('nombrar da el nombre legible sin crear el permiso', function () {
    app(RegistroPermisos::class)->nombrar('crm.prueba.crear', 'Crear pruebas');

    test()->seed(PermisosSeeder::class);

    expect(Permission::query()->where('name', 'crm.prueba.crear')->exists())->toBeFalse()
        ->and(PermisosLegibles::etiqueta('crm.prueba.crear'))->toBe('Crear pruebas');
});

test('un permiso mal escrito, sin nombre o con dos nombres se rechaza', function (string $clave, string $nombre) {
    $registro = app(RegistroPermisos::class);
    $registro->nombrar('crm.prueba.editar', 'Editar pruebas');

    expect(fn () => $registro->nombrar($clave, $nombre))->toThrow(InvalidArgumentException::class);
})->with([
    'mayúsculas' => ['CRM.prueba.ver', 'Ver'],
    'sin punto' => ['ver', 'Ver'],
    'sin nombre' => ['crm.prueba.ver', '  '],
    'otro nombre' => ['crm.prueba.editar', 'Cambiar pruebas'],
]);

test('los permisos de negocio se agrupan por área y módulo; los del núcleo, como antes', function () {
    expect(PermisosLegibles::grupo('crm.cuentas.ver'))->toBe('CRM · Cuentas')
        ->and(PermisosLegibles::grupo('erp.compras.orden.anular'))->toBe('ERP · Compras')
        ->and(PermisosLegibles::grupo('erp.obra-publica.ver'))->toBe('ERP · Obra publica')
        ->and(PermisosLegibles::grupo('parties.ver'))->toBe('Personas y empresas')
        ->and(PermisosLegibles::grupo('nucleo.motivos.editar'))->toBe('Documentos');
});

test('un módulo puede dar a su grupo un nombre mejor que el derivado', function () {
    app(RegistroPermisos::class)->agrupar('erp.obra-publica', 'ERP · Obra pública');

    expect(PermisosLegibles::grupo('erp.obra-publica.ver'))->toBe('ERP · Obra pública');
});
