<?php

declare(strict_types=1);

namespace Tests\Modules\Nucleo\Fixtures;

use App\Modules\Nucleo\Models\Concerns\RegistraCambios;

/** Tabla sensible inventada: misma tabla que ModeloAuditado, con bitácora campo por campo. */
class ModeloSensible extends ModeloAuditado
{
    use RegistraCambios;

    /**
     * @return list<string>
     */
    public function camposSinBitacora(): array
    {
        return ['nota_interna'];
    }
}
