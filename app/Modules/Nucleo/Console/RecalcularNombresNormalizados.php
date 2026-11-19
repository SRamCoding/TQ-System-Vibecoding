<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Console;

use App\Modules\Nucleo\Models\Party;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Vuelve a calcular `nombre_normalizado` y `nombre_comercial_normalizado` de todas las fichas (plan 2.20a, N1). Se corre
 * en el mismo PR que cambie `PosiblesDuplicados::normalizar`; `--simular` dice cuántas cambiarían sin escribir. Guarda
 * por el modelo, una ficha a la vez: es él quien llena las columnas.
 */
final class RecalcularNombresNormalizados extends Command
{
    protected $signature = 'nucleo:recalcular-nombres {--simular : Solo cuenta las fichas que cambiarían, sin escribir}';

    protected $description = 'Recalcula los nombres normalizados de las personas y empresas (aviso de nombres parecidos)';

    public function handle(): int
    {
        $simular = (bool) $this->option('simular');
        $cambian = 0;

        foreach (Party::query()->orderBy('id')->pluck('id') as $id) {
            // Una transacción por ficha, con su fila bloqueada: si alguien edita la razón social a la vez, el nombre
            // normalizado se calcula del que queda guardado, nunca de uno viejo (revisión de arquitectura de la 2.20a).
            $cambio = DB::transaction(function () use ($id, $simular): bool {
                $party = Party::query()->lockForUpdate()->find($id);

                if ($party === null) {
                    return false;
                }

                $party->normalizarNombres();

                if (! $party->isDirty(['nombre_normalizado', 'nombre_comercial_normalizado'])) {
                    return false;
                }

                if (! $simular) {
                    // Sin versión, bitácora ni fecha de modificación: no cambió ningún dato, solo cómo se compara.
                    $party->timestamps = false;
                    $party->saveQuietly();
                }

                return true;
            });

            $cambian += $cambio ? 1 : 0;
        }

        $this->info(($simular ? 'Cambiarían: ' : 'Recalculadas: ').$cambian);

        return self::SUCCESS;
    }
}
