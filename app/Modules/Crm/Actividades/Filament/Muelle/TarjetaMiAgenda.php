<?php

declare(strict_types=1);

namespace App\Modules\Crm\Actividades\Filament\Muelle;

use App\Modules\Crm\Actividades\Services\ActividadesService;
use App\Modules\Nucleo\Mien\ComponenteTarjetaMien;
use App\Modules\Nucleo\Models\User;
use Illuminate\Contracts\View\View;

/** MV-025: «Mi agenda» en Mien: lo de hoy, lo vencido y lo que está por vencer; el botón abre la agenda del muelle. */
final class TarjetaMiAgenda extends ComponenteTarjetaMien
{
    protected function contenido(): View
    {
        $u = auth()->user();

        return view('actividades::muelle.tarjeta-mien', ['grupos' => $u instanceof User ? app(ActividadesService::class)->miAgenda($u->id) : ['hoy' => [], 'vencidas' => [], 'por_vencer' => []]]);
    }
}
