<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Notificaciones;

use App\Modules\Nucleo\Preferencias\DefinicionPreferencia;
use App\Modules\Nucleo\Preferencias\GrupoPreferencia;
use App\Modules\Nucleo\Preferencias\RegistroPreferencias;
use InvalidArgumentException;

/**
 * Los grupos y avisos que declaran los módulos (plan 1.8, D7). Cada grupo suma su preferencia «recibir por correo» en
 * el grupo Avisos de «Mis preferencias» (D8), encendida por defecto.
 */
final class RegistroAvisos
{
    /** @var array<string, GrupoAvisos> */
    private array $grupos = [];

    /** @var array<string, DefinicionAviso> */
    private array $avisos = [];

    public function __construct(private readonly RegistroPreferencias $preferencias) {}

    public function registrarGrupo(GrupoAvisos $grupo): void
    {
        if (isset($this->grupos[$grupo->clave])) {
            throw new InvalidArgumentException("El grupo de avisos «{$grupo->clave}» ya está registrado.");
        }

        $this->grupos[$grupo->clave] = $grupo;
        $this->preferencias->declarar(DefinicionPreferencia::siNo(
            GrupoPreferencia::Avisos,
            $grupo->preferenciaCorreo(),
            "{$grupo->nombre}: también por correo",
            // Lo de la campana y los obligatorios va una vez, en la descripción de la sección (plan 1.4aa, D4).
            '',
            true,
        ));
    }

    public function registrar(DefinicionAviso $aviso): void
    {
        if (! isset($this->grupos[$aviso->grupo])) {
            throw new InvalidArgumentException("El aviso «{$aviso->clave}» es de un grupo que no existe: «{$aviso->grupo}».");
        }

        if (isset($this->avisos[$aviso->clave])) {
            throw new InvalidArgumentException("El aviso «{$aviso->clave}» ya está registrado.");
        }

        $this->avisos[$aviso->clave] = $aviso;
    }

    public function aviso(string $clave): DefinicionAviso
    {
        return $this->avisos[$clave] ?? throw new InvalidArgumentException("El aviso «{$clave}» no está registrado.");
    }

    public function grupo(string $clave): GrupoAvisos
    {
        return $this->grupos[$clave] ?? throw new InvalidArgumentException("El grupo de avisos «{$clave}» no está registrado.");
    }
}
