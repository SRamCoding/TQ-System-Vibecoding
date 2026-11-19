<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Imagenes;

use App\Modules\Nucleo\Models\User;
use Closure;
use InvalidArgumentException;

/**
 * Las imágenes que guardan los módulos en el disco privado de adjuntos sin fila en `adjuntos` (plan 2.61, paso 3): la
 * foto de un proyecto y las que vengan. Cada módulo registra, con una clave, cómo encontrar la ruta de la imagen de un
 * registro y si la persona puede verla; `MostrarImagen` la sirve con sesión. Así ningún módulo agrega rutas al panel ni
 * el núcleo lee sus tablas.
 */
final class RegistroImagenes
{
    /** @var array<string, Closure(int, User): ?string> */
    private array $buscadores = [];

    /**
     * @param  Closure(int, User): ?string  $ruta  la ruta en el disco, o null si no hay imagen o la persona no puede verla
     */
    public function registrar(string $clave, Closure $ruta): void
    {
        if (preg_match('/^[a-z][a-z0-9-]*$/', $clave) !== 1) {
            throw new InvalidArgumentException("La clave de imagen «{$clave}» va en minúsculas y guiones.");
        }

        $this->buscadores[$clave] = $ruta;
    }

    public function ruta(string $clave, int $id, User $usuario): ?string
    {
        $buscador = $this->buscadores[$clave] ?? null;

        return $buscador === null ? null : $buscador($id, $usuario);
    }

    /** La dirección de la imagen; cambia con cada imagen nueva (`v`), así el navegador puede guardarla. */
    public static function url(string $clave, int $id, string $ruta): string
    {
        return route('filament.app.imagen', ['clave' => $clave, 'id' => $id, 'v' => substr(hash('sha256', $ruta), 0, 12)]);
    }
}
