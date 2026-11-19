<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Filament\Widgets;

use App\Modules\Nucleo\Enums\Temporada;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Navegacion\AreaModulo;
use App\Modules\Nucleo\Navegacion\CajaModulo;
use App\Modules\Nucleo\Navegacion\EstacionDeLaApariencia;
use App\Modules\Nucleo\Services\NavegacionService;
use Filament\Facades\Filament;
use Filament\Widgets\Widget;

/**
 * Inicio: una caja por módulo permitido (NavegacionService, plan 1.4f), agrupadas por área (Núcleo, CRM, ERP); cada una abre su
 * módulo en otra pestaña.
 */
final class CajasModulos extends Widget
{
    protected static bool $isDiscovered = false;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    /**
     * @var view-string
     */
    protected string $view = 'nucleo::filament.widgets.cajas-modulos';

    /**
     * @return list<CajaModulo>
     */
    public function cajas(): array
    {
        $usuario = Filament::auth()->user();

        return $usuario instanceof User ? app(NavegacionService::class)->permitidos($usuario) : [];
    }

    /**
     * Las áreas que tienen alguna caja permitida, en el orden de `AreaModulo`, con sus cajas: el mismo orden que la barra.
     *
     * @return list<array{area: AreaModulo, cajas: list<CajaModulo>}>
     */
    public function secciones(): array
    {
        return app(NavegacionService::class)->porArea($this->cajas());
    }

    /** Plan 1.4r: la estación que visten las cajas, o null con la preferencia apagada (las cajas como antes). */
    public function estacion(): ?Temporada
    {
        $usuario = Filament::auth()->user();

        return $usuario instanceof User ? EstacionDeLaApariencia::para($usuario) : null;
    }
}
