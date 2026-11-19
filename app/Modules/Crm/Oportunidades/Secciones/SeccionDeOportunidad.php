<?php

declare(strict_types=1);

namespace App\Modules\Crm\Oportunidades\Secciones;

use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\Campo;

/**
 * Una sección que otro módulo suma a los formularios de la oportunidad (plan 2.21, D12.4): «Próxima acción» de
 * Actividades (CRM-05) en Nueva oportunidad y en Avanzar. Trae sus campos (los del núcleo: se validan y se dibujan
 * como los demás) y los guarda con su propio servicio, dentro de la misma transacción que crea o avanza: si falla, no
 * se crea ni se avanza.
 */
interface SeccionDeOportunidad
{
    public const string AL_CREAR = 'crear';

    public const string AL_AVANZAR = 'avanzar';

    /** Minúsculas y guion bajo: va delante del nombre de cada campo (`proxima_accion__fecha`). */
    public function clave(): string;

    public function titulo(): string;

    /** @return list<string> en qué formularios aparece: AL_CREAR, AL_AVANZAR */
    public function formularios(): array;

    /** @return list<Campo> con su nombre corto («fecha»); el registro le pone la clave delante */
    public function campos(): array;

    /**
     * Guarda lo suyo para la oportunidad, ya validado y con los nombres cortos. Corre dentro de la transacción de
     * Oportunidades; para rechazar, lanza un ErrorDeNegocio.
     *
     * @param  array<string, mixed>  $datos
     */
    public function guardar(int $oportunidadId, string $formulario, array $datos, User $usuario): void;
}
