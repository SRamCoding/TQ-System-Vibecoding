<?php

declare(strict_types=1);

namespace Tests\Modules\Nucleo\Fixtures;

use App\Modules\Nucleo\Estados\AprobacionDeTransicion;
use App\Modules\Nucleo\Estados\DocumentoConEstados;
use App\Modules\Nucleo\Models\TransicionEstado;
use App\Modules\Nucleo\Models\User;
use Illuminate\Database\Eloquent\Model;

/** Hace de mecanismo de aprobaciones (1.7) con todas las firmas dadas. */
final readonly class AprobacionesCompletas implements AprobacionDeTransicion
{
    public function pendiente(Model $documento, TransicionEstado $transicion): null
    {
        return null;
    }

    public function alCambiarEstado(Model&DocumentoConEstados $documento, ?TransicionEstado $hecha, User $quien): void {}
}
