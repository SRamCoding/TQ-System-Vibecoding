<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Ia;

/**
 * Lo que se le pide al modelo (plan 1.11, D8; ia.md «Cómo se arma una llamada»): el prompt fijo, el contexto variable
 * con las opciones válidas, los archivos y el esquema de la salida estructurada. Lo arma IaService; el cliente solo lo
 * traduce al formato de su proveedor.
 */
final readonly class PedidoModeloIa
{
    /**
     * @param  list<ArchivoParaIa>  $archivos
     * @param  array<string, mixed>  $esquema  esquema JSON de la salida
     */
    public function __construct(
        /** El código del modelo para su proveedor (`ia_modelos.codigo`). */
        public string $modelo,
        public int $maxTokensSalida,
        public string $prompt,
        public string $contexto,
        public array $archivos,
        public array $esquema,
        public int $tiempoMaximoSegundos = 120,
    ) {}
}
