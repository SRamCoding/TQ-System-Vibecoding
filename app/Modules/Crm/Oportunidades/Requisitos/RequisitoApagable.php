<?php

declare(strict_types=1);

namespace App\Modules\Crm\Oportunidades\Requisitos;

/**
 * Un requisito que se enciende y se apaga como dato (plan 2.22, D11: un parámetro). Apagado no se exige ni aparece en
 * el panel «Avanzar».
 */
interface RequisitoApagable extends RequisitoDeEtapa
{
    public function activo(): bool;
}
