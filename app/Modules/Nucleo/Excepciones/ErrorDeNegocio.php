<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Excepciones;

use RuntimeException;

/**
 * Una regla del negocio impidió la operación. El mensaje, en español y sin datos sensibles, se le
 * muestra tal cual al usuario: AccionFilament lo convierte en aviso. Toda excepción de dominio de
 * cualquier módulo extiende esta.
 */
abstract class ErrorDeNegocio extends RuntimeException {}
