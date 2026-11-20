<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Filament;

use App\Modules\Nucleo\Mien\RegistroPendientesMien;
use App\Modules\Nucleo\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * MV-052: el globo de Mien en la barra lateral principal: Mien resumido, con todo lo pendiente de la persona agrupado
 * (ninguno se omite) y cada pendiente como enlace al lugar donde se resuelve. Desde aquí solo se mira y se salta: no se
 * aprueba ni se rechaza. Se dibuja al abrirse (se pide de nuevo cada vez).
 */
final class GloboMien extends Component
{
    public bool $cargado = false;

    public function cargar(): void
    {
        $this->cargado = true;
    }

    public function render(): View
    {
        $u = auth()->user();
        $registro = app(RegistroPendientesMien::class);

        return view('nucleo::filament.globo-mien', [
            'grupos' => $this->cargado && $u instanceof User ? $registro->detalle($u) : [],
            'resumen' => $this->cargado && $u instanceof User ? $registro->de($u) : null,
        ]);
    }
}
