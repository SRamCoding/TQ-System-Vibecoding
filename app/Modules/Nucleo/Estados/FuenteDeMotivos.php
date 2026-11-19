<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Estados;

use App\Modules\Nucleo\Models\TipoMotivo;
use Carbon\CarbonImmutable;

/**
 * Un módulo que guarda motivos fuera del historial de estados (la oportunidad perdida del CRM) suma sus «Otro» a la
 * consulta de MotivosService, sin que el núcleo lea sus tablas (plan 1.5, D10, agregado 2). Se registra en
 * RegistroDocumentos.
 */
interface FuenteDeMotivos
{
    /**
     * Sus «Otro» de esa familia desde esa fecha, agrupados con `MotivosService::normalizacionSql()` para que las
     * claves coincidan. Una familia que no es suya da una lista vacía.
     *
     * @return list<DetalleDeOtro>
     */
    public function detallesDeOtro(TipoMotivo $familia, ?CarbonImmutable $desde): array;
}
