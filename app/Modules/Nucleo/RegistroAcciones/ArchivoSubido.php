<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\RegistroAcciones;

use InvalidArgumentException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Un archivo que llega a una acción (plan 1.8): dónde está ahora, en el servidor, y cómo se llamaba. Desde la
 * pantalla es el archivo que subió la persona; sin interfaz, una ruta y un nombre. Lo guarda el servicio, nunca la
 * acción.
 */
final readonly class ArchivoSubido
{
    public function __construct(
        public string $ruta,
        public string $nombreOriginal,
    ) {}

    /**
     * Lo que llega del formulario o de quien llama sin interfaz: un UploadedFile, `['ruta' => …, 'nombre' => …]` o una
     * ruta. Cualquier otra cosa no es un archivo.
     */
    public static function desde(mixed $valor): self
    {
        return match (true) {
            $valor instanceof UploadedFile => new self((string) $valor->getRealPath(), $valor->getClientOriginalName()),
            is_array($valor) && isset($valor['ruta']) => new self((string) $valor['ruta'], (string) ($valor['nombre'] ?? basename((string) $valor['ruta']))),
            is_string($valor) && $valor !== '' => new self($valor, basename($valor)),
            default => throw new InvalidArgumentException('El archivo no llegó.'),
        };
    }
}
