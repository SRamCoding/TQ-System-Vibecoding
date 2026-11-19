<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\database\seeders;

use App\Modules\Nucleo\Services\AlertasService;
use Illuminate\Database\Seeder;

/**
 * Cada tipo de alerta declarado (plan 1.10) con su fila editable y sus roles por defecto. No toca los que ya existen:
 * lo editado en Configuración → Alertas se queda. Va después de SeguridadSeeder (los roles por defecto ya existen).
 */
final class AlertasSeeder extends Seeder
{
    public function run(AlertasService $alertas): void
    {
        $alertas->sincronizar();
    }
}
