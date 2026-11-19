<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Alertas;

use App\Modules\Nucleo\Notificaciones\DefinicionAviso;
use App\Modules\Nucleo\Notificaciones\RegistroAvisos;
use InvalidArgumentException;

/**
 * El catálogo único de alertas (plan 1.10, D1): los tipos que declaran los módulos desde su provider y las fuentes que
 * corre la revisión diaria. Cada tipo suma su aviso en RegistroAvisos, con la misma clave: así llega por
 * `NotificacionesService`, con la campana y el correo de la 1.8.
 */
final class RegistroAlertas
{
    /** @var array<string, DefinicionAlerta> */
    private array $alertas = [];

    /** @var list<class-string<FuenteAlertas>> */
    private array $fuentes = [];

    public function __construct(private readonly RegistroAvisos $avisos) {}

    public function registrar(DefinicionAlerta $alerta): void
    {
        if (isset($this->alertas[$alerta->clave])) {
            throw new InvalidArgumentException("La alerta «{$alerta->clave}» ya está registrada.");
        }

        $this->avisos->registrar(new DefinicionAviso($alerta->clave, $alerta->grupo, $alerta->obligatoria, $alerta->icono, ! $alerta->soloCampana));
        $this->alertas[$alerta->clave] = $alerta;
    }

    /** @param class-string<FuenteAlertas> $fuente */
    public function registrarFuente(string $fuente): void
    {
        if (in_array($fuente, $this->fuentes, true)) {
            throw new InvalidArgumentException("La fuente de alertas «{$fuente}» ya está registrada.");
        }

        $this->fuentes[] = $fuente;
    }

    public function alerta(string $clave): DefinicionAlerta
    {
        return $this->alertas[$clave] ?? throw new InvalidArgumentException("La alerta «{$clave}» no está registrada.");
    }

    /** @return array<string, DefinicionAlerta> */
    public function alertas(): array
    {
        return $this->alertas;
    }

    /** @return list<class-string<FuenteAlertas>> */
    public function fuentes(): array
    {
        return $this->fuentes;
    }
}
