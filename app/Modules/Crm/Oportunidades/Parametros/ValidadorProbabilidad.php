<?php

declare(strict_types=1);

namespace App\Modules\Crm\Oportunidades\Parametros;

use App\Modules\Crm\Oportunidades\Enums\EtapaAbierta;
use App\Modules\Nucleo\RegistroParametros\ValidadorParametro;
use App\Modules\Nucleo\Services\ParametrosService;

/**
 * La probabilidad de una etapa (plan 2.21, D2): entre 1 y 99, y sin bajar de una etapa abierta a la siguiente. Se
 * compara con lo vigente de las vecinas: para subir dos a la vez se cambia primero la más avanzada. El validador no
 * sabe qué parámetro valida, así que hay uno por etapa.
 */
abstract readonly class ValidadorProbabilidad implements ValidadorParametro
{
    abstract protected function etapa(): EtapaAbierta;

    public function normalizar(mixed $valor): int
    {
        return (int) $valor;
    }

    public function rechazo(mixed $valor): ?string
    {
        if ($valor < 1 || $valor > 99) {
            return 'debe estar entre 1 y 99 %: ganada es 100 y perdida es 0.';
        }

        $parametros = app(ParametrosService::class);
        $anterior = $this->etapa()->anterior();
        $siguiente = $this->etapa()->siguiente();

        if ($anterior !== null && $valor < $parametros->entero($anterior->parametroProbabilidad())) {
            return "no puede ser menor que la de {$anterior->etiqueta()} ({$parametros->entero($anterior->parametroProbabilidad())} %). Si quieres bajar las dos, cambia primero la de {$anterior->etiqueta()}.";
        }

        if ($siguiente !== null && $valor > $parametros->entero($siguiente->parametroProbabilidad())) {
            return "no puede ser mayor que la de {$siguiente->etiqueta()} ({$parametros->entero($siguiente->parametroProbabilidad())} %). Si quieres subir las dos, cambia primero la de {$siguiente->etiqueta()}.";
        }

        return null;
    }
}
