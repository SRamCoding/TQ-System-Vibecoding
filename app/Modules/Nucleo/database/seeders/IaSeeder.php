<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\database\seeders;

use App\Modules\Nucleo\Services\IaConfiguracionService;
use Illuminate\Database\Seeder;

/**
 * Cada proceso de IA declarado (plan 1.11, D1) con su fila editable: apagado, sin modelo y con tope 0. No pisa lo
 * configurado; si un proceso declara enviar otra cosa, su autorización cae y se apaga (D2).
 */
final class IaSeeder extends Seeder
{
    public function run(IaConfiguracionService $ia): void
    {
        $ia->sincronizar();
    }
}
