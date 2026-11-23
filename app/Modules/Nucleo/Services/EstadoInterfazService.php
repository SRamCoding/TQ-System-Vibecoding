<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Services;

use Illuminate\Support\Facades\DB;

/**
 * MVP rápido (observación 8.3): lo que cada persona deja puesto en la interfaz (grupos de filtros abiertos, etc.), que
 * no es una preferencia de «Mis preferencias». La última escritura gana.
 */
final readonly class EstadoInterfazService
{
    public function leer(int $usuarioId, string $clave, mixed $defecto = null): mixed
    {
        $v = DB::table('estado_interfaz_usuario')->where('user_id', $usuarioId)->where('clave', $clave)->value('valor');

        return $v === null ? $defecto : json_decode((string) $v, true);
    }

    public function guardar(int $usuarioId, string $clave, mixed $valor): void
    {
        DB::table('estado_interfaz_usuario')->upsert(
            [['user_id' => $usuarioId, 'clave' => mb_substr($clave, 0, 80), 'valor' => json_encode($valor, JSON_UNESCAPED_UNICODE), 'updated_at' => now()]],
            ['user_id', 'clave'], ['valor', 'updated_at'],
        );
    }
}
