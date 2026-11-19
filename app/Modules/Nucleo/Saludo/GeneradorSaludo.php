<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Saludo;

use App\Modules\Nucleo\Models\User;
use Carbon\CarbonImmutable;

/**
 * Arma el saludo de una persona en un momento (plan 1.4b, D4). Hoy, solo `SaludoPorReglas`. Una futura implementación
 * con IA se enchufa en el provider, sin tocar la pantalla, cuando Gerencia lo permita (pendiente «IA y datos»):
 * por el mecanismo de IA del núcleo (1.11), solo frases propias, enviando solo nombre de pila, momento y fecha
 * especial, y con este generador por reglas como respaldo si falla o tarda.
 */
interface GeneradorSaludo
{
    /**
     * @param  CarbonImmutable  $ahora  en hora de Lima
     * @param  string  $sesion  la semilla de la sesión (`SemillaSesion`): otra sesión, otro saludo (plan 1.4e)
     */
    public function para(User $usuario, CarbonImmutable $ahora, string $sesion = ''): Saludo;
}
