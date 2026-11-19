<?php

declare(strict_types=1);

namespace App\Modules\Erp\Proyectos\Filament;

use App\Modules\Nucleo\Models\User;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Select;

/**
 * Selectores de las pantallas de Proyectos (plan 2.10). Solo presentación: la regla la valida ProyectosService.
 */
final class SelectoresProyecto
{
    /**
     * Los usuarios activos (sin los del sistema) y, si el valor actual dejó de serlo, también ese, marcado (AGENTS.md: el
     * selector incluye el valor que el registro ya tiene).
     */
    public static function jefe(string $nombre = 'jefe_proyecto_user_id'): Field
    {
        return Select::make($nombre)->searchable()->options(function (mixed $state): array {
            $opciones = User::query()->where('es_activo', true)->where('es_sistema', false)
                ->orderBy('name')->pluck('name', 'id')->all();

            if (is_numeric($state) && ! isset($opciones[(int) $state])) {
                $actual = User::query()->whereKey((int) $state)->value('name');
                $opciones[(int) $state] = "{$actual} (inactivo)";
            }

            return $opciones;
        });
    }
}
