<?php

declare(strict_types=1);

namespace App\Modules\Crm\Oportunidades\Enums;

use InvalidArgumentException;

/**
 * Cómo está tallada la gema de una oportunidad (plan 2.21c, D3): sale del código de su etapa. Prospecto en bruto,
 * Calificada desbastada, Propuesta enviada tallada, Negociación pulida, Ganada brillante y Perdida fisurada. Una anulada
 * no tiene gema: no está en el tablero (D11.2 de la 2.21).
 */
enum TallaDeGema: string
{
    case EnBruto = 'en_bruto';
    case Desbastada = 'desbastada';
    case Tallada = 'tallada';
    case Pulida = 'pulida';
    case Brillante = 'brillante';
    case Fisurada = 'fisurada';

    /** De un código de estado de la oportunidad («prospecto», «ganada»…). */
    public static function desdeEtapa(string $codigoEtapa): self
    {
        return match ($codigoEtapa) {
            EtapaAbierta::Prospecto->value => self::EnBruto,
            EtapaAbierta::Calificada->value => self::Desbastada,
            EtapaAbierta::PropuestaEnviada->value => self::Tallada,
            EtapaAbierta::Negociacion->value => self::Pulida,
            LugarFaja::Ganadas->estado() => self::Brillante,
            LugarFaja::Perdidas->estado() => self::Fisurada,
            default => throw new InvalidArgumentException("La etapa «{$codigoEtapa}» no tiene gema."),
        };
    }

    public static function desdeLugar(LugarFaja $lugar): self
    {
        return self::desdeEtapa($lugar->estado());
    }

    public function etiqueta(): string
    {
        return match ($this) {
            self::EnBruto => 'En bruto',
            self::Desbastada => 'Desbastada',
            self::Tallada => 'Tallada',
            self::Pulida => 'Pulida',
            self::Brillante => 'Brillante',
            self::Fisurada => 'Fisurada',
        };
    }

    /** Lo que dice un lector de pantalla: «Gema en bruto», «Gema tallada»… */
    public function textoAccesible(): string
    {
        return 'Gema '.mb_strtolower($this->etiqueta());
    }
}
