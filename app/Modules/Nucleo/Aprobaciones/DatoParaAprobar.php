<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Aprobaciones;

use App\Modules\Nucleo\Seguridad\GrupoSensible;

/**
 * Un dato del resumen que un documento muestra en «Mis aprobaciones» (plan 2.22, P6): «Cliente», «Total», «Margen».
 * El que lleva grupo sensible solo llega a quien tiene su permiso; lo filtra el servidor (`AprobacionesService::bandeja`).
 */
final readonly class DatoParaAprobar
{
    public function __construct(
        public string $etiqueta,
        public string $valor,
        public ?GrupoSensible $grupo = null,
    ) {}
}
