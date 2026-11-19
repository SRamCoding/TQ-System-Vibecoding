<?php

declare(strict_types=1);

namespace App\Modules\Erp\Proyectos\Resumen;

/** Las cuatro preguntas del Resumen de la ficha (plan 2.61, D5), en su orden. */
enum SeccionResumen: string
{
    case Dinero = 'dinero';
    case Plazo = 'plazo';
    case Cobranza = 'cobranza';
    case Atencion = 'atencion';

    public function pregunta(): string
    {
        return match ($this) {
            self::Dinero => '¿Cuánto estamos ganando?',
            self::Plazo => '¿Vamos a tiempo?',
            self::Cobranza => '¿Cuánto nos han pagado?',
            self::Atencion => '¿Qué necesita atención hoy?',
        };
    }

    /** Lo que se dice cuando la sección no tiene ninguna tarjeta todavía. */
    public function sinTarjetas(): string
    {
        return match ($this) {
            self::Dinero => 'Aparece cuando el proyecto tenga su árbol de partidas.',
            self::Plazo => 'Sin fechas todavía.',
            self::Cobranza => 'Aparece con las valorizaciones y las garantías del contrato.',
            self::Atencion => 'Nada que atender hoy.',
        };
    }
}
