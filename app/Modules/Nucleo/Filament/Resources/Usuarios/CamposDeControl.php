<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Filament\Resources\Usuarios;

use App\Modules\Nucleo\Filament\SelectoresSeguridad;
use Closure;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Textarea;

/**
 * Cómo se piden en pantalla los campos del control compensatorio (plan 1.4, D9 y D10): solo presentación para
 * `AccionFilament::desde(controles: …)`. Si el cambio no abre ningún cruce, se ignoran.
 */
final class CamposDeControl
{
    public const AVISO = 'Si con este cambio la persona junta permisos incompatibles, el sistema te lo dirá: describe '
        .'entonces el control compensatorio y elige a su responsable (otra persona).';

    /**
     * @return array<string, Closure(): Field>
     */
    public static function controles(): array
    {
        return [
            'control' => fn (): Field => Textarea::make('control')->rows(3),
            'responsable_user_id' => fn (): Field => SelectoresSeguridad::usuarioActivo('responsable_user_id'),
        ];
    }
}
