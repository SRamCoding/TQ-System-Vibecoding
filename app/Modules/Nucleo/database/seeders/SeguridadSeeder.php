<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\database\seeders;

use App\Modules\Nucleo\Services\ConfiguracionInicialService;
use App\Modules\Nucleo\Services\SaludService;
use App\Modules\Nucleo\Services\SeguridadService;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Los roles que trae el sistema. `administrador` (plan 1.4, D3 y D8), con los permisos de configuración y seguridad. Los
 * de negocio no: así no nace en conflicto con el catálogo de permisos incompatibles, y la grilla real de roles la arma
 * Gerencia como dato (pendiente «Grilla de roles real»). Sus permisos se fijan aquí en cada despliegue: nadie los cambia
 * desde una pantalla (SeguridadService lo rechaza). Y `gerencia general` (plan 1.10, D2), que recibe las alertas de
 * control: nace sin permisos y aquí no se le tocan, porque los decide la grilla. Va después de PermisosSeeder.
 */
final class SeguridadSeeder extends Seeder
{
    /**
     * Usuarios, roles, organigrama, catálogos, parámetros, alertas y la configuración de IA. Sin los que mueven dinero (`*-sensibles`, tipos de cambio),
     * sin `campos.*` y sin personas y empresas: son del negocio.
     */
    public const array PERMISOS_ADMINISTRADOR = [
        'usuarios.ver',
        SeguridadService::PERMISO_GESTIONAR_USUARIOS,
        'roles.gestionar',
        'organigrama.ver',
        'organigrama.gestionar',
        'catalogos.ver',
        'catalogos.editar',
        'parametros.editar',
        'ubigeos.actualizar',
        // Plan 1.10, D7: a quién llega cada alerta y sus textos.
        'alertas.configurar',
        // Plan 1.11, D2: proveedores, modelos, precios y procesos de IA. Autorizar lo que sale (`ia.autorizar`) es de
        // Gerencia, y resolver sugerencias, del permiso del módulo de cada proceso: los decide la grilla de roles.
        'ia.configurar',
        // Plan 1.12, D2 y D6: la página de salud y Pulse.
        SaludService::PERMISO_VER,
        // Plan 1.13, D7: el asistente de configuración inicial.
        ConfiguracionInicialService::PERMISO,
    ];

    /**
     * `syncPermissions` no pasa por SeguridadService: no revisa cruces ni emite PermisosDeRolCambiados. Vale porque
     * esta lista no cambia sin un despliegue y ningún par incompatible debería tocar sus permisos. Si un par llega a
     * incluir uno, al registrarlo se abren igual los cruces pendientes de quienes lo tengan.
     */
    public function run(): void
    {
        $rol = Role::findOrCreate(SeguridadService::ROL_ADMINISTRADOR, 'web');
        // Por nombre: si alguno no existe (PermisosSeeder no lo creó), falla aquí y no en producción días después.
        $rol->syncPermissions(self::PERMISOS_ADMINISTRADOR);
        Role::findOrCreate(SeguridadService::ROL_GERENCIA_GENERAL, 'web');

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
