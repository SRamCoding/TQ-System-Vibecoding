<?php

declare(strict_types=1);

namespace App\Modules\Crm\Oportunidades\Enums;

use InvalidArgumentException;

/**
 * Cómo está tallada la gema de una oportunidad (plan 2.21c, D3): sale del código de su etapa. Por calificar (lead o
 * licitación por decidir; observación 7.1) es la roca con la veta, antes de sacar la piedra; Prospecto en bruto,
 * Calificada desbastada, Propuesta enviada tallada, Negociación pulida, Ganada brillante y Perdida fisurada. Una anulada
 * no tiene gema: no está en el tablero (D11.2 de la 2.21).
 */
enum TallaDeGema: string
{
    /** Observación 7.1: «Por calificar», la roca gris con la veta turquesa a la vista. */
    case EnRoca = 'en_roca';

    case EnBruto = 'en_bruto';
    case Desbastada = 'desbastada';
    case Tallada = 'tallada';
    case Pulida = 'pulida';
    case Brillante = 'brillante';
    case Fisurada = 'fisurada';

    /** La etapa previa al embudo (leads y licitaciones por decidir): no es un estado de la oportunidad. */
    public const string POR_CALIFICAR = 'por_calificar';

    /** De un código de estado de la oportunidad («prospecto», «ganada»…). */
    public static function desdeEtapa(string $codigoEtapa): self
    {
        return match ($codigoEtapa) {
            self::POR_CALIFICAR => self::EnRoca,
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
            self::EnRoca => 'En la roca',
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
