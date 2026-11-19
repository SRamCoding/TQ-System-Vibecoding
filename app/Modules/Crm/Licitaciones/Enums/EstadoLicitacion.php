<?php

declare(strict_types=1);

namespace App\Modules\Crm\Licitaciones\Enums;

/** MVP rápido (t1-licitaciones): los estados de una licitación, como texto (atajo: no usa el motor de estados). */
enum EstadoLicitacion: string
{
    case Registrada = 'registrada';
    case EnPreparacion = 'en_preparacion';
    case Presentada = 'presentada';
    case BuenaPro = 'buena_pro';
    case Perdida = 'perdida';
    case Desierta = 'desierta';
    case NoParticipa = 'no_participa';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Registrada => 'Por decidir',
            self::EnPreparacion => 'En preparación',
            self::Presentada => 'Propuesta presentada',
            self::BuenaPro => 'Buena pro',
            self::Perdida => 'Perdida',
            self::Desierta => 'Desierta',
            self::NoParticipa => 'No participamos',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Registrada => 'gray',
            self::EnPreparacion => 'warning',
            self::Presentada => 'info',
            self::BuenaPro => 'success',
            self::Perdida, self::Desierta => 'danger',
            self::NoParticipa => 'gray',
        };
    }

    public function esAbierta(): bool
    {
        return in_array($this, [self::Registrada, self::EnPreparacion, self::Presentada], true);
    }

    /** @return array<string, string> */
    public static function opciones(): array
    {
        return array_combine(array_map(fn (self $e): string => $e->value, self::cases()), array_map(fn (self $e): string => $e->etiqueta(), self::cases()));
    }
}
