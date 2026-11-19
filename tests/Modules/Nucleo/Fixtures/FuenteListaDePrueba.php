<?php

declare(strict_types=1);

namespace Tests\Modules\Nucleo\Fixtures;

use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Preferencias\FuenteDeLista;

/**
 * Una lista de prueba que vive en memoria: lo que la persona eligió y si se restableció.
 */
final class FuenteListaDePrueba implements FuenteDeLista
{
    /** @var array<int, list<string>> */
    public static array $elegidos = [];

    public function valores(User $usuario): array
    {
        return self::$elegidos[$usuario->id] ?? [];
    }

    public function opciones(User $usuario): array
    {
        return ['uno' => 'Uno', 'dos' => 'Dos'];
    }

    public function restablecer(User $usuario): void
    {
        unset(self::$elegidos[$usuario->id]);
    }

    public function accion(): string
    {
        return 'pruebas.lista';
    }

    public function datosParaGuardar(array $elegidos, array $mostrados): array
    {
        return ['no_elegidos' => array_values(array_diff($mostrados, $elegidos))];
    }
}
