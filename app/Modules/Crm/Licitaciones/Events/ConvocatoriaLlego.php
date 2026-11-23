<?php

declare(strict_types=1);

namespace App\Modules\Crm\Licitaciones\Events;

use Illuminate\Foundation\Events\Dispatchable;

/** Observación 7.4: el OECE publicó una convocatoria que no estaba guardada. Reaccionan: filtrar, priorizar y avisar. */
final readonly class ConvocatoriaLlego
{
    use Dispatchable;

    public function __construct(public int $convocatoriaId) {}
}
