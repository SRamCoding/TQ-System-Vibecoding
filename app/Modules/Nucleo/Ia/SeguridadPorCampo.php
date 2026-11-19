<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Ia;

use App\Modules\Nucleo\Enums\SeguridadCampoIa;

/**
 * La seguridad de un campo propuesto (plan 1.11, D6), de señales comprobables: la regla fija del proceso para ese campo
 * y la evidencia encontrada. Alta con las dos; media con una; baja sin ninguna o si una falla. Lo que dijo el modelo
 * solo puede bajarla. Aquí se sumará el historial de correcciones por campo, sin tocar a quien llama.
 */
final class SeguridadPorCampo
{
    /**
     * @param  ?bool  $reglaCumple  nulo: el proceso no tiene regla para ese campo
     * @param  ?bool  $evidenciaVerificada  nulo: no se puede verificar (una foto o la página de un PDF escaneado)
     */
    public static function calcular(?bool $reglaCumple, ?bool $evidenciaVerificada, SeguridadCampoIa $declarada): SeguridadCampoIa
    {
        if ($reglaCumple === false || $evidenciaVerificada === false) {
            return SeguridadCampoIa::Baja;
        }

        $calculada = match (true) {
            $reglaCumple === true && $evidenciaVerificada === true => SeguridadCampoIa::Alta,
            $reglaCumple === true || $evidenciaVerificada === true => SeguridadCampoIa::Media,
            default => SeguridadCampoIa::Baja,
        };

        return $calculada->hasta($declarada);
    }
}
