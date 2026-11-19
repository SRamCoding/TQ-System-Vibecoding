<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Seguridad;

use InvalidArgumentException;

/**
 * Los permisos que declaran los módulos (plan 2.20a, N8). Los de las acciones y las transiciones salen solos de su
 * clase; aquí van los que no son acciones (ver una pantalla, poder ser el responsable de algo), que PermisosSeeder
 * crea, y el nombre legible de cualquier permiso del módulo, que muestra Roles y permisos. Así el núcleo no tiene una
 * lista a mano con los permisos de cada módulo.
 *
 * Los de un módulo de negocio llevan el prefijo de su área: `crm.cuentas.ver` (docs/convenciones/codigo.md).
 */
final class RegistroPermisos
{
    private const string FORMATO = '/^[a-z][a-z0-9_]*(\.[a-z0-9][a-z0-9_-]*)+$/';

    /** @var array<string, true> */
    private array $declarados = [];

    /** @var array<string, string> clave => nombre legible */
    private array $nombres = [];

    /** @var array<string, string> `crm.cuentas` => «CRM · Cuentas» */
    private array $grupos = [];

    /** Un permiso que no sale de una acción: se crea en cada despliegue, con su nombre legible. */
    public function declarar(string $clave, string $nombre): void
    {
        $this->nombrar($clave, $nombre);
        $this->declarados[$clave] = true;
    }

    /** Solo el nombre legible de un permiso que ya crea otra pieza (una acción, una transición). */
    public function nombrar(string $clave, string $nombre): void
    {
        if (preg_match(self::FORMATO, $clave) !== 1) {
            throw new InvalidArgumentException("El permiso [{$clave}] debe ser minúsculas separadas por puntos («crm.cuentas.ver»).");
        }

        if (trim($nombre) === '') {
            throw new InvalidArgumentException("El permiso [{$clave}] necesita un nombre legible.");
        }

        if (isset($this->nombres[$clave]) && $this->nombres[$clave] !== $nombre) {
            throw new InvalidArgumentException("El permiso [{$clave}] ya tiene otro nombre: «{$this->nombres[$clave]}».");
        }

        $this->nombres[$clave] = $nombre;
    }

    /** El nombre del grupo de un módulo de negocio, si el que se deriva de la clave no sirve («CRM · Cuentas»). */
    public function agrupar(string $prefijo, string $nombre): void
    {
        $this->grupos[$prefijo] = $nombre;
    }

    /** @return list<string> */
    public function declarados(): array
    {
        return array_keys($this->declarados);
    }

    public function nombre(string $clave): ?string
    {
        return $this->nombres[$clave] ?? null;
    }

    public function grupo(string $prefijo): ?string
    {
        return $this->grupos[$prefijo] ?? null;
    }
}
