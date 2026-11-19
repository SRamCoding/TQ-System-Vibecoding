<?php

declare(strict_types=1);

namespace App\Modules\Crm\Oportunidades\Enums;

/**
 * Los lugares de la faja de vidrio (plan 2.21b, D8 y D9): las cuatro zonas, una por etapa abierta, y las dos salas de
 * las cerradas. Las anuladas no están en ningún lugar (D11.2 de la 2.21).
 */
enum LugarFaja: string
{
    case Prospecto = 'prospecto';
    case Calificada = 'calificada';
    case PropuestaEnviada = 'propuesta_enviada';
    case Negociacion = 'negociacion';
    case Ganadas = 'ganadas';
    case Perdidas = 'perdidas';

    public static function deEtapa(EtapaAbierta $etapa): self
    {
        return self::from($etapa->value);
    }

    /** La etapa de una zona; nula en una sala. */
    public function etapa(): ?EtapaAbierta
    {
        return EtapaAbierta::tryFrom($this->value);
    }

    public function esSala(): bool
    {
        return $this->etapa() === null;
    }

    /** El código del estado de la oportunidad en este lugar. */
    public function estado(): string
    {
        return match ($this) {
            self::Ganadas => 'ganada',
            self::Perdidas => 'perdida',
            default => $this->value,
        };
    }

    public function etiqueta(): string
    {
        return match ($this) {
            self::Ganadas => 'Ganadas',
            self::Perdidas => 'Perdidas',
            default => (string) $this->etapa()?->etiqueta(),
        };
    }

    /**
     * Qué cajas se dibujan cuando no caben todas (D8): en las zonas, las de mayor monto (en moneda local); en las salas,
     * las más recientes (fecha de cierre real). Es un orden de `OportunidadesService::tablaDeLaFaja`.
     *
     * @return array{0: string, 1: string} el orden y su dirección
     */
    public function ordenDeLasCajas(): array
    {
        return $this->esSala() ? ['cierre', 'desc'] : ['monto', 'desc'];
    }
}
