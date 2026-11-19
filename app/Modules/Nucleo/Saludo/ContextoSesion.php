<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Saludo;

use App\Modules\Nucleo\Enums\IdiomaSaludo;
use App\Modules\Nucleo\Enums\SesionSaludo;

/**
 * Lo que el saludo sabe de la sesión por la historia de la persona (plan 1.4aa, D7 y D8). Lo arma `HistorialSaludos`.
 */
final readonly class ContextoSesion
{
    public function __construct(
        /** La clase de sesión: primera del día, vuelve, vuelve después de trabajar. */
        public SesionSaludo $sesion,
        /** El idioma ya guardado de esta sesión (se queda toda la sesión); nulo si todavía no se la saludó. */
        public ?IdiomaSaludo $idiomaGuardado,
        /** Si esta sesión puede salir en otro idioma: la anterior fue en español y hoy no hubo otro idioma. */
        public bool $puedeOtroIdioma,
        /** El último idioma que no fue español, para no repetirlo si hay otro. */
        public ?IdiomaSaludo $ultimoOtroIdioma,
    ) {}
}
