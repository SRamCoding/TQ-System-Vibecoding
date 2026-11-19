<?php

declare(strict_types=1);

namespace Tests\Modules\Nucleo\Fixtures;

use App\Modules\Nucleo\Estados\DetalleDeOtro;
use App\Modules\Nucleo\Estados\FuenteDeMotivos;
use App\Modules\Nucleo\Models\TipoMotivo;
use Carbon\CarbonImmutable;

/** Un módulo inventado que guardó dos «Precio alto» en sus tablas para la familia de anular del documento de prueba. */
final class FuenteDeMotivosPrueba implements FuenteDeMotivos
{
    public function detallesDeOtro(TipoMotivo $familia, ?CarbonImmutable $desde): array
    {
        return $familia->codigo === 'nucleo.prueba.anular'
            ? [new DetalleDeOtro('precio alto', 'PRECIO ALTO', 2, CarbonImmutable::parse('2020-01-01'))]
            : [];
    }
}
