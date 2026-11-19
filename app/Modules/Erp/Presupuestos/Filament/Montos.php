<?php

declare(strict_types=1);

namespace App\Modules\Erp\Presupuestos\Filament;

use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Seguridad\GrupoSensible;
use App\Modules\Nucleo\Services\SeguridadService;
use Filament\Facades\Filament;

/**
 * Cómo se muestran los montos en las pantallas del área 1 (plan 2.10): como texto decimal, sin pasar por `float`
 * (AGENTS.md, regla 4), y solo a quien tiene el permiso del grupo sensible del campo (ADR 0016; `campos.ver-costos`
 * por omisión, `campos.ver-margen` para la utilidad): los demás ven «—». Un formulario no se rellena con un monto que
 * la persona no puede ver (lo oculto no se envía al navegador): la acción se oculta o el campo llega vacío.
 */
final class Montos
{
    public static function visibles(GrupoSensible $grupo = GrupoSensible::Costos): bool
    {
        $usuario = Filament::auth()->user();

        return $usuario instanceof User && app(SeguridadService::class)->puede($usuario, $grupo->permiso());
    }

    /** «19,525.00»; con signo si es negativo. Nunca redondea: el monto ya viene con sus decimales. */
    public static function texto(string $monto): string
    {
        $negativo = str_starts_with($monto, '-');
        [$entero, $decimales] = array_pad(explode('.', ltrim($monto, '-')), 2, '');
        $entero = ltrim($entero, '0') === '' ? '0' : ltrim($entero, '0');
        $conMiles = strrev(implode(',', str_split(strrev($entero), 3)));

        return ($negativo ? '-' : '').$conMiles.($decimales === '' ? '' : '.'.$decimales);
    }

    /** El monto si la persona ve su grupo (costos, si no se dice); si no, «—». */
    public static function siVisible(string $monto, GrupoSensible $grupo = GrupoSensible::Costos): string
    {
        return self::visibles($grupo) ? self::texto($monto) : '—';
    }
}
