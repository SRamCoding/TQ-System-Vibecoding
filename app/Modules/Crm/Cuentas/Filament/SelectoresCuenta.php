<?php

declare(strict_types=1);

namespace App\Modules\Crm\Cuentas\Filament;

use App\Modules\Crm\Cuentas\CuentasServiceProvider;
use App\Modules\Crm\Cuentas\Services\CuentasService;
use App\Modules\Nucleo\Models\Motivo;
use App\Modules\Nucleo\Models\User;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Select;

/**
 * Controles de presentación para las acciones de Cuentas (plan 2.20). Quien valida sigue siendo el servicio.
 */
final class SelectoresCuenta
{
    /**
     * D7.2: los usuarios activos que pueden atender cuentas y, si el valor actual dejó de poder (desactivado o sin el
     * permiso), también ese, marcado (AGENTS.md: el selector incluye el valor que el registro ya tiene).
     */
    public static function ejecutivo(string $nombre = 'ejecutivo_user_id'): Field
    {
        return Select::make($nombre)->searchable()->options(function (mixed $state): array {
            $opciones = User::permission(CuentasService::PERMISO_ATENDER)
                ->where('es_activo', true)->where('es_sistema', false)
                ->orderBy('name')->pluck('name', 'id')->all();

            if (is_numeric($state) && ! isset($opciones[(int) $state])) {
                $actual = User::query()->whereKey((int) $state)->value('name');
                $opciones[(int) $state] = "{$actual} (ya no puede atender cuentas)";
            }

            return $opciones;
        });
    }

    /** «Creada por error (RUC equivocado)»: el motivo que ofrece desactivar también la ficha (D9.2). */
    public static function esCreadaPorError(mixed $motivoId): bool
    {
        return is_numeric($motivoId) && Motivo::query()->whereKey((int) $motivoId)->value('codigo') === CuentasServiceProvider::MOTIVO_CREADA_POR_ERROR;
    }
}
