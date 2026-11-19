<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\database\seeders;

use App\Modules\Nucleo\Excepciones\ParametroDuplicado;
use App\Modules\Nucleo\Models\Parametro;
use App\Modules\Nucleo\RegistroParametros\RegistroParametros;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Crea los parámetros que declararon los módulos y todavía no existen. De uno que ya existe no se tocan
 * el valor ni la descripción: se editan desde la pantalla (plan 1.2b, E2 y E9). Si es editable sí sigue
 * al código: decide si la aplicación puede cambiarlo.
 */
final class ParametrosSeeder extends Seeder
{
    public function run(RegistroParametros $registro): void
    {
        DB::transaction(function () use ($registro): void {
            foreach ($registro->todas() as $definicion) {
                $parametro = Parametro::query()
                    ->where('grupo', $definicion->grupo)
                    ->where('clave', $definicion->clave)
                    ->first();

                if ($parametro === null) {
                    Parametro::query()->create([
                        'grupo' => $definicion->grupo,
                        'clave' => $definicion->clave,
                        'tipo' => $definicion->tipo,
                        'valor' => $definicion->valorInicial,
                        'descripcion' => $definicion->descripcion,
                        'es_editable' => $definicion->esEditable,
                    ]);

                    continue;
                }

                if ($parametro->tipo !== $definicion->tipo) {
                    throw ParametroDuplicado::tipoDistinto($definicion->nombre(), $parametro->tipo->value, $definicion->tipo->value);
                }

                $parametro->fill(['es_editable' => $definicion->esEditable]);

                if ($parametro->isDirty()) {
                    $parametro->save();
                }
            }
        });
    }
}
