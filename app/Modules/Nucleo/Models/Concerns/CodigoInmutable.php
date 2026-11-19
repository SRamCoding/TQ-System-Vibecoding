<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Models\Concerns;

use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * Columnas que identifican la fila y no cambian nunca (plan 1.5, D1, ajuste 3): cambiar un código en la definición
 * no renombra, es quitar una fila y crear otra. El rechazo vive en el modelo para que valga venga de donde venga.
 *
 * @mixin Model
 */
trait CodigoInmutable
{
    public static function bootCodigoInmutable(): void
    {
        static::updating(function (self $modelo): void {
            foreach ($modelo->columnasInmutables() as $columna) {
                if ($modelo->isDirty($columna)) {
                    throw new LogicException("«{$columna}» de {$modelo->getTable()} no cambia: se crea otra fila.");
                }
            }
        });
    }

    /**
     * @return list<string>
     */
    abstract public function columnasInmutables(): array;
}
