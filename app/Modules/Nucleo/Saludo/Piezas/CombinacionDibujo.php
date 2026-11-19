<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Saludo\Piezas;

/** Un dibujo armado (plan 1.4e2): un fondo, un motivo y, si hay, un acompañante. */
final readonly class CombinacionDibujo
{
    public function __construct(
        public PiezaDibujo $fondo,
        public PiezaDibujo $motivo,
        public ?PiezaDibujo $acompanante,
    ) {}

    /** Estable entre despliegues: identifica la combinación (`fondo:motivo:acompañante`). */
    public function clave(): string
    {
        return "{$this->fondo->clave}:{$this->motivo->clave}:".($this->acompanante->clave ?? '-');
    }

    public function nombre(): string
    {
        return $this->motivo->nombre.($this->acompanante !== null ? ' con '.mb_strtolower($this->acompanante->nombre) : '').' · '.mb_strtolower($this->fondo->nombre);
    }
}
