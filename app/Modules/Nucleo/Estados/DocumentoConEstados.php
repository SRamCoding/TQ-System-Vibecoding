<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Estados;

use Illuminate\Database\Eloquent\Model;

/**
 * Un documento cuyo estado cambia solo por TransicionesService (plan 1.5). Su estado actual vive en `estado_id` y
 * su bloqueo optimista en `version`. Lo implementa el trait TieneEstados, que además impide editarlo en un estado no
 * editable.
 */
interface DocumentoConEstados
{
    /**
     * Dónde se revisa el permiso de una transición, además del global (D6): basta tenerlo en uno.
     *
     * @return list<Model>
     */
    public function ambitoDePermisos(): array;

    /**
     * Columnas que mantienen otros documentos: solo cambian dentro de un efecto (D8).
     *
     * @return list<string>
     */
    public function camposDerivados(): array;

    public function esEditable(): bool;
}
