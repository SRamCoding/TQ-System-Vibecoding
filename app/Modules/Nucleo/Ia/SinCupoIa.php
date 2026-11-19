<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Ia;

use RuntimeException;

/** El proveedor ya tiene todas sus llamadas en paralelo ocupadas (plan 1.11, D5): el job vuelve a la cola con espera. */
final class SinCupoIa extends RuntimeException {}
