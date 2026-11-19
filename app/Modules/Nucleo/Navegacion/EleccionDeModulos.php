<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Navegacion;

use Illuminate\Contracts\Session\Session;

/**
 * Si en este inicio de sesión ya se aceptó la ventana «Elegir módulos» al prender la barra (plan 1.4f, D6). Vive en la
 * sesión de Laravel, no en la base: al cerrar sesión, o si vence y se vuelve a entrar, cuenta como primera vez otra
 * vez. La limpia el oyente del inicio de sesión (ReiniciarEleccionDeModulos).
 */
final readonly class EleccionDeModulos
{
    public const string CLAVE = 'nucleo.barra_modulos.eleccion_aceptada';

    public function __construct(private Session $sesion) {}

    public function yaSeAcepto(): bool
    {
        return $this->sesion->get(self::CLAVE) === true;
    }

    public function marcarAceptada(): void
    {
        $this->sesion->put(self::CLAVE, true);
    }

    public function reiniciar(): void
    {
        $this->sesion->forget(self::CLAVE);
    }
}
