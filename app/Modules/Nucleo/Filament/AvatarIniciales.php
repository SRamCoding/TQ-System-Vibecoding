<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Filament;

use Filament\AvatarProviders\Contracts\AvatarProvider;
use Filament\Facades\Filament;
use Filament\Support\Colors\Color;
use Filament\Support\Facades\FilamentColor;
use Illuminate\Database\Eloquent\Model;

/**
 * Las iniciales de quien no tiene foto (plan 1.4o, revisión de arquitectura), dibujadas aquí. El proveedor de Filament
 * por defecto las pedía a ui-avatars.com, que recibía el nombre de cada persona: un dato personal fuera del sistema
 * (regla 10). Mismo aspecto: hasta dos iniciales blancas sobre el gris más oscuro de Filament.
 */
final class AvatarIniciales implements AvatarProvider
{
    public function get(Model $record): string
    {
        $iniciales = mb_strtoupper(collect(preg_split('/\s+/u', trim(Filament::getNameForDefaultAvatar($record))) ?: [])
            ->map(fn (string $parte): string => mb_substr((string) preg_replace('/^[^\p{L}\p{N}]+/u', '', $parte), 0, 1))
            ->filter()
            ->take(2)
            ->join(''));
        $fondo = Color::convertToHex(FilamentColor::getColor('gray')[950] ?? Color::Gray[950]);

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64">'
            .'<rect width="64" height="64" fill="'.e($fondo).'"/>'
            .'<text x="32" y="32" dy=".35em" text-anchor="middle" fill="#FFFFFF" font-family="ui-sans-serif, system-ui, sans-serif" font-size="26" font-weight="500">'
            .e($iniciales).'</text></svg>';

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
