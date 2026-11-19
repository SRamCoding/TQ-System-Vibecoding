<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Seguridad;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

/**
 * Contra el olvido (plan 1.4, D6): busca en las columnas reales de la tabla de un modelo las que suenan sensibles y
 * no están declaradas. Lee la base, no las migraciones: también ve una columna agregada después.
 */
final class RevisorCamposSensibles
{
    /** Partes de nombre que delatan un dato sensible. Única lista: se amplía aquí. */
    public const SOSPECHOSOS = ['costo', 'precio', 'margen', 'sueldo', 'remuneracion', 'salario', 'cci', 'cuenta', 'medic', 'examen', 'utilidad'];

    /**
     * Columnas sospechosas sin declarar, y las declaradas que no existen o no tienen motivo.
     *
     * @return list<string>
     */
    public static function problemas(Model $modelo): array
    {
        $columnas = Schema::getColumnListing($modelo->getTable());
        $sensibles = $modelo instanceof DeclaraCamposSensibles ? $modelo->camposSensibles() : [];
        $noSensibles = $modelo instanceof DeclaraCamposSensibles ? $modelo->camposNoSensibles() : [];
        $problemas = [];

        foreach ($columnas as $columna) {
            if (self::esSospechosa($columna) && ! isset($sensibles[$columna]) && ! isset($noSensibles[$columna])) {
                $problemas[] = "{$modelo->getTable()}.{$columna}: suena sensible y no está en camposSensibles() ni en camposNoSensibles()";
            }
        }

        foreach ([...array_keys($sensibles), ...array_keys($noSensibles)] as $declarada) {
            if (! in_array($declarada, $columnas, true)) {
                $problemas[] = "{$modelo->getTable()}.{$declarada}: está declarada y la tabla no la tiene";
            }
        }

        foreach ($noSensibles as $columna => $motivo) {
            if (trim($motivo) === '') {
                $problemas[] = "{$modelo->getTable()}.{$columna}: camposNoSensibles() sin motivo";
            }
        }

        return $problemas;
    }

    /**
     * Si alguna palabra del nombre (separadas por `_`) empieza con una parte sospechosa: `costo_unitario`,
     * `costos_directos`, `detalle_medico`. Por palabras y no por subcadenas: «dire**cci**on» no es un CCI.
     */
    public static function esSospechosa(string $columna): bool
    {
        foreach (explode('_', $columna) as $palabra) {
            foreach (self::SOSPECHOSOS as $parte) {
                if (str_starts_with($palabra, $parte)) {
                    return true;
                }
            }
        }

        return false;
    }
}
