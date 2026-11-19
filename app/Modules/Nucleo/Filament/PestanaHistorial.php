<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Filament;

use Closure;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\Tabs\Tab;

/**
 * La pestaña «Historial» de cualquier ficha (v4 parte 7; plan 1.3, D10): la tabla de la ficha, que extiende
 * TablaHistorial y dice de qué registros leer.
 */
final class PestanaHistorial
{
    /**
     * @param  class-string<TablaHistorial>  $tabla
     * @param  Closure  $registroId  recibe `$record` por inyección y devuelve el id de la ficha
     */
    public static function make(string $tabla, Closure $registroId): Tab
    {
        return Tab::make('Historial')
            ->icon('heroicon-o-clock')
            ->schema([
                Livewire::make($tabla, fn (Livewire $component): array => ['registroId' => (int) $component->evaluate($registroId)])->key('historial'),
            ]);
    }
}
