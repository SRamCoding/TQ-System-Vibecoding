<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Filament\Widgets;

use App\Modules\Nucleo\Filament\Pages\ConfiguracionInicial;
use App\Modules\Nucleo\Services\ConfiguracionInicialService;
use Filament\Widgets\Widget;

/**
 * Inicio (plan 1.13, D6): mientras falten pasos obligatorios de la configuración inicial, a quien puede configurar, un
 * aviso con «Seguir configurando». Al completarlos, desaparece solo.
 */
final class AvisoConfiguracionInicial extends Widget
{
    protected static bool $isDiscovered = false;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    /**
     * @var view-string
     */
    protected string $view = 'nucleo::filament.widgets.aviso-configuracion-inicial';

    public static function canView(): bool
    {
        return ConfiguracionInicial::canAccess() && app(ConfiguracionInicialService::class)->faltan() !== [];
    }

    /** @return list<string> los títulos de los que faltan, en orden */
    public function faltan(): array
    {
        return array_map(fn ($paso): string => $paso->titulo, app(ConfiguracionInicialService::class)->faltan());
    }
}
