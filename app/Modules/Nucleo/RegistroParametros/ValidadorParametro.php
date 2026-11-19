<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\RegistroParametros;

/**
 * Regla de negocio de un parámetro, además de su tipo (plan 1.3b: el RUC del banco de detracciones debe ser un
 * banco registrado). La declara el parámetro (`DefinicionParametro::$validador`) y la llama ParametrosService
 * antes de guardar un cambio. El valor inicial no pasa por aquí: lo siembra el código.
 */
interface ValidadorParametro
{
    /**
     * El valor como se guarda (por ejemplo, un RUC sin guiones). Se llama antes de `rechazo()`.
     *
     * @param  mixed  $valor  en su tipo de PHP
     */
    public function normalizar(mixed $valor): mixed;

    /**
     * @param  mixed  $valor  en su tipo de PHP
     * @return ?string el motivo del rechazo, para mostrarlo tal cual; nulo si se acepta
     */
    public function rechazo(mixed $valor): ?string;
}
