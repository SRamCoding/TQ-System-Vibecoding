<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Ia;

use InvalidArgumentException;

/** Un archivo que va al modelo junto con el pedido (plan 1.11, D4): un PDF o una imagen. Nunca uno sensible. */
final readonly class ArchivoParaIa
{
    public const array TIPOS = ['application/pdf', 'image/jpeg', 'image/png', 'image/gif', 'image/webp'];

    public function __construct(
        public string $tipo,
        public string $contenido,
    ) {
        if (! in_array($tipo, self::TIPOS, true)) {
            throw new InvalidArgumentException("El modelo no lee archivos «{$tipo}»: solo PDF e imágenes.");
        }
    }

    public function esPdf(): bool
    {
        return $this->tipo === 'application/pdf';
    }
}
