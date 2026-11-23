<?php

declare(strict_types=1);

namespace App\Modules\Crm\Licitaciones\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Observación 7.4: llegó una versión más nueva de una convocatoria guardada (enmienda, cambio de cronograma, resultado).
 * `cambios`: campo => [antes, después].
 */
final readonly class ConvocatoriaCambio
{
    use Dispatchable;

    /** @param  array<string, array{0: mixed, 1: mixed}>  $cambios */
    public function __construct(public int $convocatoriaId, public array $cambios) {}
}
