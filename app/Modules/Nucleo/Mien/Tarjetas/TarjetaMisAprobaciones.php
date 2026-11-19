<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Mien\Tarjetas;

use App\Modules\Nucleo\Filament\Pages\MisAprobaciones;
use App\Modules\Nucleo\Mien\ComponenteTarjetaMien;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Services\AprobacionesService;
use Illuminate\Contracts\View\View;

/** «Mis aprobaciones» en «Mien» (plan 2.22, P6): cuántas firmas esperan a la persona y el enlace a la bandeja. */
final class TarjetaMisAprobaciones extends ComponenteTarjetaMien
{
    protected function contenido(): View
    {
        $usuario = auth()->user();
        $cuantas = $usuario instanceof User ? count(app(AprobacionesService::class)->porFirmar($usuario)) : 0;

        return view('nucleo::filament.mien.mis-aprobaciones', ['cuantas' => $cuantas, 'enlace' => MisAprobaciones::getUrl()]);
    }
}
