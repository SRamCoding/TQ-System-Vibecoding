<?php

declare(strict_types=1);

namespace App\Modules\Crm\Oportunidades\Parametros;

use App\Modules\Crm\Oportunidades\Enums\EtapaAbierta;

final readonly class ValidadorProbabilidadPropuestaEnviada extends ValidadorProbabilidad
{
    protected function etapa(): EtapaAbierta
    {
        return EtapaAbierta::PropuestaEnviada;
    }
}
