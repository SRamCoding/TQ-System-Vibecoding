<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Navegacion;

use Carbon\CarbonImmutable;

/**
 * La fase real de la luna para la luz de la barra de noche (plan 1.4ac, D4), calculada con la fecha, sin consultar a
 * nadie: los días desde una luna nueva conocida, módulo el mes sinódico. Basta para un adorno (error de horas).
 */
final readonly class FaseLunar
{
    /** Duración media de un ciclo de la luna, en días. */
    public const float MES_SINODICO = 29.530588853;

    /** Una luna nueva conocida: 6 de enero de 2000, 18:14 UTC. */
    private const string LUNA_NUEVA = '2000-01-06 18:14:00';

    /**
     * @param  float  $edad  días desde la última luna nueva (0 a 29,53)
     * @param  float  $iluminada  fracción del disco iluminada (0 nueva, 1 llena)
     * @param  bool  $creciente  si va de nueva a llena
     */
    private function __construct(public float $edad, public float $iluminada, public bool $creciente) {}

    public static function de(CarbonImmutable $momento): self
    {
        $dias = (float) CarbonImmutable::parse(self::LUNA_NUEVA, 'UTC')->diffInSeconds($momento->utc(), false) / 86400;
        $edad = fmod(fmod($dias, self::MES_SINODICO) + self::MES_SINODICO, self::MES_SINODICO);

        return new self($edad, (1 - cos(2 * M_PI * $edad / self::MES_SINODICO)) / 2, $edad < self::MES_SINODICO / 2);
    }

    public function esLlena(): bool
    {
        return $this->iluminada >= 0.95;
    }
}
