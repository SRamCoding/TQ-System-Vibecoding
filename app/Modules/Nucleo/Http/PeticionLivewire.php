<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Http;

use Illuminate\Http\Request;

/**
 * Para los middleware que dejan pasar una sola pantalla (ExigirClaveRenovada, ExigirGerenteGeneral). Livewire vuelve a
 * pasar sus peticiones por los middleware persistentes haciéndose pasar por la página de origen, así que la ruta sola
 * dejaría pasar cualquier componente dibujado en ella (la barra superior, el menú de la foto, la búsqueda).
 */
final class PeticionLivewire
{
    /** Si todos los componentes de la petición de Livewire son esa pantalla. */
    public static function soloDe(Request $request, string $pantalla): bool
    {
        $componentes = $request->input('components');
        $nombre = app('livewire.finder')->normalizeName($pantalla);

        if ($nombre === null || ! is_array($componentes) || $componentes === []) {
            return false;
        }

        foreach ($componentes as $componente) {
            $snapshot = is_array($componente) && is_string($componente['snapshot'] ?? null) ? json_decode($componente['snapshot'], true) : null;

            if (! is_array($snapshot) || ($snapshot['memo']['name'] ?? null) !== $nombre) {
                return false;
            }
        }

        return true;
    }
}
