<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Services;

use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Seguridad\RegistroPermisos;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Spatie\Permission\Models\Role;

/**
 * MVP rápido (ficha op-solicitudes-permiso, plan 2.0b): quien no tiene el permiso de una acción ve el botón bloqueado,
 * sabe qué le falta y lo pide; quien administra Roles y permisos lo concede por rol (nunca a una persona suelta) o lo
 * rechaza con comentario.
 */
final readonly class SolicitudesPermisoService
{
    public function nombre(string $permiso): string
    {
        return app(RegistroPermisos::class)->nombre($permiso) ?? $permiso;
    }

    public function solicitar(User $quien, string $permiso, string $accion, ?string $motivo): void
    {
        $existe = DB::table('solicitudes_permiso')->where('user_id', $quien->id)->where('permiso', $permiso)->where('estado', 'pendiente')->exists();

        if ($existe) {
            throw new RuntimeException('Ya pediste ese permiso: está pendiente.');
        }

        DB::table('solicitudes_permiso')->insert(['user_id' => $quien->id, 'permiso' => $permiso, 'accion' => $accion, 'motivo' => $motivo, 'estado' => 'pendiente', 'created_at' => now(), 'updated_at' => now()]);
    }

    /** Lo concede dándoselo a un rol de la persona (si no lo tiene, se le asigna también el rol). */
    public function conceder(int $id, int $rolId, User $quien): void
    {
        DB::transaction(function () use ($id, $rolId, $quien): void {
            $s = DB::table('solicitudes_permiso')->lockForUpdate()->find($id);

            if ($s === null || $s->estado !== 'pendiente') {
                throw new RuntimeException('Esa solicitud ya se resolvió.');
            }

            $rol = Role::findById($rolId, 'web');
            $rol->givePermissionTo($s->permiso);
            $usuario = User::query()->findOrFail($s->user_id);

            if (! $usuario->hasRole($rol)) {
                $usuario->assignRole($rol);
            }

            DB::table('solicitudes_permiso')->where('id', $id)->update(['estado' => 'concedida', 'rol_id' => $rolId, 'resuelto_por' => $quien->id, 'updated_at' => now()]);
        });
    }

    public function rechazar(int $id, string $comentario, User $quien): void
    {
        if (trim($comentario) === '') {
            throw new RuntimeException('Rechazar pide un comentario.');
        }

        DB::table('solicitudes_permiso')->where('id', $id)->where('estado', 'pendiente')->update(['estado' => 'rechazada', 'comentario' => trim($comentario), 'resuelto_por' => $quien->id, 'updated_at' => now()]);
    }
}
