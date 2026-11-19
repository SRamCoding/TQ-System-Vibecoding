<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Seguridad;

/**
 * Un modelo dice, junto a sus columnas, cuáles son sensibles y de qué grupo (plan 1.4, D6). La sensibilidad vive en
 * el código, no en una pantalla: nadie la apaga. Lo configurable es qué rol tiene cada permiso `campos.ver-<grupo>`.
 *
 * Una columna con nombre sospechoso (RevisorCamposSensibles::SOSPECHOSOS) va en uno de los dos métodos; si no,
 * `composer check` falla.
 */
interface DeclaraCamposSensibles
{
    /**
     * @return array<string, GrupoSensible> columna => grupo
     */
    public function camposSensibles(): array;

    /**
     * Columnas que suenan sensibles y no lo son, con el motivo.
     *
     * @return array<string, string> columna => motivo
     */
    public function camposNoSensibles(): array;
}
