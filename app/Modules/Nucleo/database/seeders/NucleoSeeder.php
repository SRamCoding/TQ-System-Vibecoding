<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\database\seeders;

use Illuminate\Database\Seeder;

/**
 * Datos que el núcleo necesita en todo ambiente, producción incluida. Se puede repetir sin duplicar
 * ni pisar valores editados: va en cada despliegue después de migrar.
 */
final class NucleoSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([ParametrosSeeder::class, CatalogosSeeder::class, FeriadosSeeder::class, EstadosSeeder::class, FrasesSaludoSeeder::class, PermisosSeeder::class, SeguridadSeeder::class, AlertasSeeder::class, IaSeeder::class]);
    }
}
