<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Catalogos;

use App\Modules\Nucleo\Enums\Catalogo;
use App\Modules\Nucleo\Estados\DetalleDeOtro;
use Carbon\CarbonImmutable;

/**
 * Un módulo que guarda el detalle escrito al elegir «Otro» de un catálogo (plan 2.0, D5): por ejemplo Cuentas, con el
 * origen comercial. Lo informa así, sin que el núcleo toque sus tablas (regla 2), para que «Lo más escrito en Otro»
 * (D3) sume lo de todos. Es el equivalente de `FuenteDeMotivos` para los catálogos.
 */
interface FuenteDeOtros
{
    /**
     * Sus detalles de «Otro» de ese catálogo desde esa fecha, agrupados con `MotivosService::normalizacionSql()` para
     * que las claves coincidan con las de los demás.
     *
     * @return list<DetalleDeOtro>
     */
    public function detallesDeOtro(Catalogo $catalogo, ?CarbonImmutable $desde): array;
}
