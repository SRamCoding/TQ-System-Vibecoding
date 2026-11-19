<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\database\seeders;

use App\Modules\Nucleo\Models\CategoriaFrase;
use App\Modules\Nucleo\Models\FraseSaludo;
use App\Modules\Nucleo\Services\UsuarioSistema;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Los saludos y frases de inicio (plan 1.4b), del archivo `database/data/frases-saludo.php`, que dice las reglas del
 * contenido. Corre en todo ambiente y solo crea lo que falta, por `codigo`: nunca pisa lo editado en la pantalla. Una
 * frase borrada desde la pantalla vuelve en el siguiente despliegue; para quitarla, se desactiva. Versículos cotejados
 * en BibleGateway (RVR1960, KJV y LSG), con sus mayúsculas: la poesía empieza cada verso con mayúscula.
 */
final class FrasesSaludoSeeder extends Seeder
{
    public function run(UsuarioSistema $sistema): void
    {
        /** @var array{saludos: list<array<int|string, mixed>>, frases: array<string, list<array<int|string, mixed>>>, agradecimientos: list<array<int|string, mixed>>} $datos */
        $datos = require dirname(__DIR__).'/data/frases-saludo.php';
        $categorias = CategoriaFrase::query()->pluck('id', 'codigo');
        $filas = array_map(fn (array $fila): array => self::fila($fila, ['tipo' => 'saludo', 'categoria_frase_id' => null]), $datos['saludos']);

        foreach ($datos['frases'] as $categoria => $frases) {
            foreach ($frases as $fila) {
                $filas[] = self::fila($fila, ['tipo' => 'frase', 'categoria_frase_id' => $categorias[$categoria]]);
            }
        }

        foreach ($datos['agradecimientos'] as $fila) {
            $filas[] = self::fila($fila, ['tipo' => 'agradecimiento', 'categoria_frase_id' => null]);
        }

        // En bloque y sin bitácora de creación: son 300 filas y el seeder corre en cada prueba que carga el núcleo
        // (con una por una, cada carga tomaba casi un segundo). Lo que cambie alguien después sí queda en la bitácora.
        DB::transaction(function () use ($filas, $sistema): void {
            $existentes = FraseSaludo::query()->pluck('codigo')->flip();
            $ahora = now();
            $nuevas = array_values(array_filter($filas, fn (array $fila): bool => ! $existentes->has($fila['codigo'])));

            foreach (array_chunk($nuevas, 100) as $tanda) {
                FraseSaludo::query()->insert(array_map(fn (array $fila): array => [...$fila, 'created_by' => $sistema->id(), 'created_at' => $ahora], $tanda));
            }
        });
    }

    /**
     * Una fila del archivo, con todas las columnas (el insert en bloque exige las mismas en cada fila).
     *
     * @param  array<int|string, mixed>  $fila  [código, texto] y las condiciones con nombre (ver el archivo de datos)
     * @param  array<string, mixed>  $fijos
     * @return array<string, mixed>
     */
    private static function fila(array $fila, array $fijos): array
    {
        return [
            'codigo' => $fila[0],
            'texto' => $fila[1],
            'idioma' => $fila['idioma'] ?? 'es',
            'fuente' => $fila['fuente'] ?? null,
            'momento' => $fila['momento'] ?? null,
            'dia_semana' => $fila['dia_semana'] ?? null,
            'area' => $fila['area'] ?? null,
            'ocasion' => $fila['ocasion'] ?? null,
            'fuera_de_horario' => $fila['fuera'] ?? null,
            'sesion' => $fila['sesion'] ?? null,
            ...$fijos,
        ];
    }
}
