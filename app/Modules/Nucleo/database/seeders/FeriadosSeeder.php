<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\database\seeders;

use App\Modules\Nucleo\Enums\TipoFeriado;
use App\Modules\Nucleo\Models\Feriado;
use App\Modules\Nucleo\Saludo\CalendarioFestivo;
use Illuminate\Database\Seeder;

/**
 * Los feriados nacionales de los años de `database/data/feriados.php` (plan 1.4d, D2), con Jueves y Viernes Santo
 * calculados. Corre en todo ambiente, pero **carga un año solo si todavía no tiene ningún feriado**: desde entonces,
 * la lista de ese año es de quien la administra en Configuración → Catálogos, y lo que borre o cambie no vuelve
 * (pedido del desarrollador, 2026-10-22). Los días no laborables y los libres de la empresa se cargan ahí.
 */
final class FeriadosSeeder extends Seeder
{
    public function run(): void
    {
        /** @var array{anios: list<int>, fijos: list<array{int, int, string}>} $datos */
        $datos = require dirname(__DIR__).'/data/feriados.php';
        foreach ($datos['anios'] as $anio) {
            if (Feriado::query()->whereBetween('codigo', ["{$anio}-01-01", "{$anio}-12-31"])->exists()) {
                continue;
            }

            $resurreccion = CalendarioFestivo::domingoDeResurreccion($anio);
            $fechas = [
                ...array_map(fn (array $fijo): array => [sprintf('%04d-%02d-%02d', $anio, $fijo[0], $fijo[1]), $fijo[2]], $datos['fijos']),
                [$resurreccion->subDays(3)->toDateString(), 'Jueves Santo'],
                [$resurreccion->subDays(2)->toDateString(), 'Viernes Santo'],
            ];

            foreach ($fechas as [$codigo, $nombre]) {
                Feriado::query()->create(['codigo' => $codigo, 'nombre' => $nombre, 'tipo' => TipoFeriado::FeriadoNacional, 'techquk_no_trabaja' => true]);
            }
        }
    }
}
