<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Filament;

use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Services\PreferenciasUsuarioService;

/**
 * Las preferencias de apariencia como el navegador las aplica (plan 1.4g, D4 y D5): clases propias en `<html>` y el
 * tema de Filament. La vista `nucleo::filament.apariencia` las pone antes de dibujar la página y, al cambiar una
 * desde «Mis preferencias», las aplica al instante con el evento `tq-apariencia`.
 */
final class Apariencia
{
    /** Evento del navegador con `clases` (las de abajo que van) y `tema`. */
    public const string EVENTO = 'tq-apariencia';

    public const string LETRA_GRANDE = 'tq-letra-grande';

    public const string COMPACTO = 'tq-compacto';

    public const string SIN_ANIMACIONES = 'tq-sin-animaciones';

    /** Todas las clases que maneja: el navegador quita las que no vienen. */
    public const array CLASES = [self::LETRA_GRANDE, self::COMPACTO, self::SIN_ANIMACIONES];

    /**
     * Las clases que lleva `<html>` para esta persona.
     *
     * @return list<string>
     */
    public static function clases(User $usuario): array
    {
        $preferencias = app(PreferenciasUsuarioService::class);

        return array_values(array_filter([
            $preferencias->opcion($usuario, 'apariencia.letra') === 'grande' ? self::LETRA_GRANDE : null,
            $preferencias->opcion($usuario, 'apariencia.densidad') === 'compacto' ? self::COMPACTO : null,
            $preferencias->opcion($usuario, 'apariencia.animaciones') === 'reducir' ? self::SIN_ANIMACIONES : null,
        ]));
    }

    /** `light`, `dark` o `system`: lo que Filament guarda en el navegador. */
    public static function tema(User $usuario): string
    {
        return app(PreferenciasUsuarioService::class)->opcion($usuario, 'apariencia.tema');
    }
}
