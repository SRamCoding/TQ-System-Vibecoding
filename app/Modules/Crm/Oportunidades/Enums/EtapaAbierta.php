<?php

declare(strict_types=1);

namespace App\Modules\Crm\Oportunidades\Enums;

/**
 * Las etapas abiertas del embudo, en su orden (plan 2.21, D1): son los estados abiertos de la oportunidad. Cada una
 * tiene su probabilidad y su máximo de días en parámetros (D2).
 */
enum EtapaAbierta: string
{
    case Prospecto = 'prospecto';
    case Calificada = 'calificada';
    case PropuestaEnviada = 'propuesta_enviada';
    case Negociacion = 'negociacion';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Prospecto => 'Prospecto',
            self::Calificada => 'Calificada',
            self::PropuestaEnviada => 'Propuesta enviada',
            self::Negociacion => 'Negociación',
        };
    }

    /** Nombre completo del parámetro de su probabilidad («crm.probabilidad_calificada»). */
    public function parametroProbabilidad(): string
    {
        return "crm.probabilidad_{$this->value}";
    }

    /** Nombre completo del parámetro de sus días máximos («crm.dias_maximos_calificada»); 0 es «sin máximo». */
    public function parametroDiasMaximos(): string
    {
        return "crm.dias_maximos_{$this->value}";
    }

    public function anterior(): ?self
    {
        return self::cases()[array_search($this, self::cases(), true) - 1] ?? null;
    }

    public function siguiente(): ?self
    {
        return self::cases()[array_search($this, self::cases(), true) + 1] ?? null;
    }

    /** Los valores del diseño (D2). */
    public function probabilidadInicial(): int
    {
        return match ($this) {
            self::Prospecto => 10,
            self::Calificada => 25,
            self::PropuestaEnviada => 50,
            self::Negociacion => 75,
        };
    }

    /** Solo Calificada trae máximo en el diseño (14 días); las demás, sin máximo hasta que la empresa responda (D2). */
    public function diasMaximosIniciales(): int
    {
        // Plan 2.22 (propuesta provisional del desarrollador, P2): «Propuesta enviada» dura lo que vale la cotización.
        return match ($this) {
            self::Calificada => 14,
            self::PropuestaEnviada => 15,
            default => 0,
        };
    }
}
