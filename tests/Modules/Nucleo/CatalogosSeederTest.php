<?php

declare(strict_types=1);

use App\Modules\Nucleo\database\seeders\CatalogosSeeder;
use App\Modules\Nucleo\database\seeders\NucleoSeeder;
use App\Modules\Nucleo\database\seeders\PermisosSeeder;
use App\Modules\Nucleo\Enums\OrigenUbigeo;
use App\Modules\Nucleo\Models\FormaPago;
use App\Modules\Nucleo\Models\Moneda;
use App\Modules\Nucleo\Models\TipoDocumento;
use App\Modules\Nucleo\Models\Ubigeo;
use App\Modules\Nucleo\Models\UnidadMedida;
use App\Modules\Nucleo\RegistroAcciones\RegistroAcciones;
use App\Modules\Nucleo\Services\UsuarioSistema;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Tests\Modules\Nucleo\Fixtures\AccionDePrueba;

/*
 * Datos maestros (plan 1.2, D8): idempotentes; las listas oficiales siguen al seeder y las del negocio
 * no se pisan. Los ubigeos se prueban con un CSV pequeño, salvo la carga completa.
 */

function usarUbigeosDePrueba(string ...$lineas): void
{
    $ruta = tempnam(sys_get_temp_dir(), 'ubigeos').'.csv';
    file_put_contents($ruta, "codigo,departamento,provincia,distrito\n".implode("\n", $lineas)."\n");
    config(['nucleo.ubigeos.csv' => $ruta, 'nucleo.ubigeos.minimo_distritos' => 1]);
}

function sembrarCatalogos(): void
{
    test()->seed(CatalogosSeeder::class);
}

test('la carga completa deja los catálogos, los ubigeos del CSV y los parámetros, con el sistema como autor', function () {
    test()->seed(NucleoSeeder::class);

    expect(Moneda::query()->pluck('codigo')->sort()->values()->all())->toBe(['PEN', 'USD'])
        ->and(TipoDocumento::query()->where('codigo', '07')->value('signo'))->toBe(-1)
        ->and(Ubigeo::query()->count())->toBeGreaterThanOrEqual(1800)
        ->and(Ubigeo::query()->where('codigo', '140101')->value('distrito'))->toBe('CHICLAYO')
        ->and(DB::table('cubso')->count())->toBe(0)
        ->and(DB::table('tipos_cambio')->count())->toBe(0)
        ->and(DB::table('parametros')->count())->toBeGreaterThanOrEqual(3)
        ->and(Ubigeo::query()->where('created_by', '<>', app(UsuarioSistema::class)->id())->count())->toBe(0);
});

test('la unidad Global (GLB) del presupuesto se factura como ZZ; las demás, con su propio código', function () {
    test()->seed(NucleoSeeder::class);

    expect(UnidadMedida::query()->where('codigo', 'GLB')->firstOrFail()->only(['nombre', 'abreviatura', 'permite_decimales', 'codigo_sunat']))
        ->toBe(['nombre' => 'Global', 'abreviatura' => 'glb', 'permite_decimales' => false, 'codigo_sunat' => 'ZZ'])
        ->and(UnidadMedida::query()->where('codigo', '<>', 'GLB')->whereColumn('codigo_sunat', '<>', 'codigo')->count())->toBe(0)
        ->and(UnidadMedida::factory()->create(['codigo' => 'XYZ'])->codigo_sunat)->toBe('XYZ');
});

test('sembrar dos veces no duplica nada', function () {
    usarUbigeosDePrueba('140101,LAMBAYEQUE,CHICLAYO,CHICLAYO');

    sembrarCatalogos();
    $antes = collect(['monedas', 'tipos_documento', 'unidades_medida', 'formas_pago', 'lineas_solucion', 'tipos_penalidad', 'tipos_trabajo', 'tipos_documento_identidad', 'roles_party', 'ubigeos'])
        ->mapWithKeys(fn (string $t): array => [$t => DB::table($t)->count()]);
    sembrarCatalogos();

    $antes->each(fn (int $cantidad, string $tabla) => expect(DB::table($tabla)->count())->toBe($cantidad, $tabla));
});

test('volver a sembrar no pisa lo editado, tampoco en las listas oficiales', function () {
    usarUbigeosDePrueba('140101,LAMBAYEQUE,CHICLAYO,CHICLAYO');
    sembrarCatalogos();

    Moneda::query()->where('codigo', 'USD')->firstOrFail()->update(['nombre' => 'Dólar americano']);
    TipoDocumento::query()->where('codigo', '09')->firstOrFail()->update(['es_activo' => false]);
    FormaPago::query()->where('codigo', 'CREDITO_30')->firstOrFail()->update(['nombre' => 'Crédito 30 (editado)']);
    sembrarCatalogos();

    expect(Moneda::query()->where('codigo', 'USD')->value('nombre'))->toBe('Dólar americano')
        ->and(TipoDocumento::query()->where('codigo', '09')->value('es_activo'))->toBeFalse()
        ->and(FormaPago::query()->where('codigo', 'CREDITO_30')->value('nombre'))->toBe('Crédito 30 (editado)');
});

test('el seeder solo agrega ubigeos que faltan y no deshace lo que actualizó la pantalla', function () {
    usarUbigeosDePrueba('140101,LAMBAYEQUE,CHICLAYO,CHICLAYO', '140103,LAMBAYEQUE,CHICLAYO,ETEN');
    sembrarCatalogos();

    // La acción de la pantalla trajo la lista vigente: renombró uno, retiró otro y agregó uno nuevo.
    Ubigeo::query()->where('codigo', '140103')->firstOrFail()->update(['distrito' => 'CIUDAD ETEN']);
    Ubigeo::query()->where('codigo', '140101')->firstOrFail()->update(['es_activo' => false]);
    Ubigeo::query()->create(['codigo' => '140121', 'departamento' => 'LAMBAYEQUE', 'provincia' => 'CHICLAYO', 'distrito' => 'NUEVO', 'origen' => OrigenUbigeo::Inei]);

    // El siguiente despliegue corre el seeder con el CSV atrasado y uno que el CSV sí trae y la base no.
    usarUbigeosDePrueba('140101,LAMBAYEQUE,CHICLAYO,CHICLAYO', '140103,LAMBAYEQUE,CHICLAYO,ETEN', '140102,LAMBAYEQUE,CHICLAYO,CHONGOYAPE');
    sembrarCatalogos();

    expect(Ubigeo::query()->where('codigo', '140103')->value('distrito'))->toBe('CIUDAD ETEN')
        ->and(Ubigeo::query()->where('codigo', '140101')->value('es_activo'))->toBeFalse()
        ->and(Ubigeo::query()->where('codigo', '140121')->exists())->toBeTrue()
        ->and(Ubigeo::query()->where('codigo', '140102')->value('distrito'))->toBe('CHONGOYAPE');
});

test('los permisos salen de las acciones registradas más los de lectura, sin duplicarse', function () {
    app(RegistroAcciones::class)->registrar(AccionDePrueba::class);

    test()->seed(PermisosSeeder::class);
    test()->seed(PermisosSeeder::class);

    expect(Permission::query()->pluck('name')->sort()->values()->all())->toBe(['alertas.configurar', 'aprobaciones.autoaprobar', 'aprobaciones.configurar', 'aprobaciones.revisar', 'campos.ver-costos', 'campos.ver-datos_bancarios', 'campos.ver-detalle_medico', 'campos.ver-margen', 'campos.ver-precio_compra', 'campos.ver-remuneracion', 'catalogos.editar', 'catalogos.editar-sensibles', 'catalogos.ver', 'configuracion.inicial', 'crm.cotizaciones.cotizacion.aceptar', 'crm.cotizaciones.cotizacion.anular', 'crm.cotizaciones.cotizacion.anular_aceptacion', 'crm.cotizaciones.cotizacion.emitir', 'crm.cotizaciones.cotizacion.reabrir', 'crm.cotizaciones.cotizacion.rechazar', 'crm.cotizaciones.cotizacion.solicitar_aprobacion', 'crm.cotizaciones.crear', 'crm.cotizaciones.editar', 'crm.cotizaciones.nueva-version', 'crm.cotizaciones.todas', 'crm.cotizaciones.ver', 'crm.cuentas.atender', 'crm.cuentas.crear', 'crm.cuentas.desactivar', 'crm.cuentas.editar', 'crm.cuentas.editar-propias', 'crm.cuentas.marcar-cliente', 'crm.cuentas.quitar-marca-cliente', 'crm.cuentas.reasignar', 'crm.cuentas.ver', 'crm.oportunidades.crear', 'crm.oportunidades.editar', 'crm.oportunidades.editar-propias', 'crm.oportunidades.oportunidad.anular', 'crm.oportunidades.oportunidad.anular_ganada', 'crm.oportunidades.oportunidad.calificar', 'crm.oportunidades.oportunidad.enviar_propuesta', 'crm.oportunidades.oportunidad.ganar', 'crm.oportunidades.oportunidad.negociar', 'crm.oportunidades.oportunidad.perder', 'crm.oportunidades.oportunidad.retroceder', 'crm.oportunidades.reasignar', 'crm.oportunidades.ver', 'erp.presupuestos.arbol.aprobar', 'erp.presupuestos.arbol.editar', 'erp.presupuestos.insumos.editar', 'erp.presupuestos.plantillas.publicar', 'erp.presupuestos.presupuesto.anular', 'erp.presupuestos.presupuesto.editar', 'erp.presupuestos.presupuesto.nueva-version', 'erp.presupuestos.presupuesto.ofertar', 'erp.presupuestos.presupuesto.traer_al_proyecto', 'erp.presupuestos.reclasificar-gasto', 'erp.presupuestos.ver', 'erp.proyectos.crear', 'erp.proyectos.proyecto.anular', 'erp.proyectos.proyecto.cerrar', 'erp.proyectos.ver', 'ia.autorizar', 'ia.configurar', 'nucleo.estados.editar-presentacion', 'nucleo.motivos.editar', 'organigrama.gestionar', 'organigrama.ver', 'parametros.editar', 'parametros.editar-sensibles', 'parties.asignar-roles', 'parties.borrar', 'parties.cambiar-rubro', 'parties.crear', 'parties.desactivar', 'parties.editar', 'parties.editar-tributarios', 'parties.gestionar-contactos', 'parties.gestionar-cuentas-bancarias', 'parties.gestionar-redes', 'parties.gestionar-sedes', 'parties.ver', 'probar-acciones', 'roles.gestionar', 'salud.ver', 'tipos_cambio.registrar', 'ubigeos.actualizar', 'usuarios.gestionar', 'usuarios.ver']);
});
