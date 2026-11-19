<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Excepciones;

/**
 * El cambio junta en una persona dos permisos incompatibles y no trae el control compensatorio (plan 1.4, D9). No se
 * guardó nada. Dice qué cruces faltan para que el formulario pida el control ahí mismo.
 */
final class CruceSinControl extends ErrorDeNegocio
{
    /**
     * @param  list<array{usuario_id: int, usuario: string, incompatibilidad_id: int, permisos: string}>  $faltantes
     */
    public function __construct(public readonly array $faltantes)
    {
        $lista = implode('; ', array_map(fn (array $f): string => "{$f['usuario']}: {$f['permisos']}", $faltantes));

        parent::__construct("Este cambio junta permisos incompatibles ({$lista}). Registra el control compensatorio y su responsable para guardarlo.");
    }
}
