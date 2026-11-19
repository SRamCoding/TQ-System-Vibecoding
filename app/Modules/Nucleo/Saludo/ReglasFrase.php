<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Saludo;

use App\Modules\Nucleo\Enums\TipoFraseSaludo;
use App\Modules\Nucleo\Models\FraseSaludo;

/**
 * Lo que la base exige a un saludo, una frase o un agradecimiento (sus CHECKs), dicho antes de guardar y con un
 * mensaje claro para quien lo edita en la pantalla de catálogos (plan 1.4b). La base lo sigue garantizando.
 */
final class ReglasFrase
{
    public const int LARGO_AGRADECIMIENTO = 60;

    public static function rechazo(FraseSaludo $frase): ?string
    {
        $tieneCategoria = $frase->categoria_frase_id !== null;

        // Plan 1.4e: una especial de fuera de horario sale sola; con fecha especial o momento, nunca saldría.
        if ($frase->fuera_de_horario !== null && ($frase->tipo === TipoFraseSaludo::Agradecimiento || $frase->ocasion !== null || $frase->momento !== null)) {
            return 'Un texto de fuera de horario no es agradecimiento ni lleva fecha especial ni momento del día: déjalos vacíos.';
        }

        // Plan 1.4aa, D8: la fecha especial y fuera de horario mandan antes que la sesión; con ellas, nunca saldría.
        if ($frase->sesion !== null && ($frase->tipo !== TipoFraseSaludo::Saludo || $frase->ocasion !== null || $frase->fuera_de_horario !== null)) {
            return 'Solo un saludo lleva sesión, y sin fecha especial ni fuera de horario: déjalos vacíos.';
        }

        return match ($frase->tipo) {
            TipoFraseSaludo::Frase => $tieneCategoria ? null : 'Una frase necesita su categoría.',
            TipoFraseSaludo::Saludo => match (true) {
                $tieneCategoria => 'Un saludo no lleva categoría: déjala vacía.',
                ! str_contains($frase->texto, '{nombre}') => 'Un saludo lleva {nombre}, donde va el nombre de la persona.',
                default => null,
            },
            TipoFraseSaludo::Agradecimiento => match (true) {
                $tieneCategoria => 'Un agradecimiento no lleva categoría: déjala vacía.',
                ! str_contains($frase->texto, '{anios}') => 'Un agradecimiento lleva {anios}, donde van los años de Techquk.',
                mb_strlen($frase->texto) > self::LARGO_AGRADECIMIENTO => 'Un agradecimiento no pasa de '.self::LARGO_AGRADECIMIENTO.' letras: no cabe en la barra.',
                default => null,
            },
        };
    }
}
