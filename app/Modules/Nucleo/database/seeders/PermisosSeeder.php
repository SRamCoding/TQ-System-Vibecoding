<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\database\seeders;

use App\Modules\Nucleo\Estados\RegistroDocumentos;
use App\Modules\Nucleo\RegistroAcciones\RegistroAcciones;
use App\Modules\Nucleo\Seguridad\GrupoSensible;
use App\Modules\Nucleo\Seguridad\RegistroPermisos;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Crea los permisos que el sistema usa: el de cada acción registrada (una acción nueva trae el suyo
 * sin tocar este archivo), el de cada transición de persona (también las de acción propia, que su módulo ejecuta
 * con otra acción) y los que cada módulo declara en RegistroPermisos sin ser acciones (ver pantallas, plan 2.20a, N8).
 * Los de campos sensibles salen de GrupoSensible. Quién los tiene llega en la 1.4.
 */
final class PermisosSeeder extends Seeder
{
    public function run(RegistroAcciones $acciones, RegistroDocumentos $documentos, RegistroPermisos $declarados): void
    {
        $permisos = [...$declarados->declarados(), ...GrupoSensible::permisos()];

        foreach ($documentos->definiciones() as $definicion) {
            foreach ($definicion->transiciones() as $transicion) {
                $permisos[] = $definicion->permiso($transicion);
            }
        }

        foreach ($acciones->todas() as $clave => $clase) {
            $permiso = $acciones->obtener($clave)->permiso();

            if ($permiso !== null) {
                $permisos[] = $permiso;
            }
        }

        foreach (array_unique(array_filter($permisos)) as $permiso) {
            Permission::findOrCreate($permiso, 'web');
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
