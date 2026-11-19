<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Estados;

use Illuminate\Database\Eloquent\Model;

/**
 * Los documentos vivos que dependen de uno e impiden anularlo: la recepción confirmada de una orden (plan 1.5, D4).
 * La declara la definición del documento o la registra el módulo del dependiente en RegistroDocumentos. Corre dentro
 * del bloqueo de la fila.
 */
interface RevisionAntesDeAnular
{
    /**
     * @return list<DocumentoQueImpide>
     */
    public function documentosQueImpiden(Model $documento): array;
}
