<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\database\seeders;

use App\Modules\Nucleo\Services\SincronizadorEstados;
use Illuminate\Database\Seeder;

/**
 * Estados, transiciones y listas de motivos que declaran los módulos (plan 1.5, D1). Corre en cada despliegue.
 */
final class EstadosSeeder extends Seeder
{
    public function run(SincronizadorEstados $sincronizador): void
    {
        $sincronizador->sincronizar();
    }
}
