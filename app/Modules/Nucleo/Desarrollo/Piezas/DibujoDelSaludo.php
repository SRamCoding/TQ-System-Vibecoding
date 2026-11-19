<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Desarrollo\Piezas;

use App\Modules\Nucleo\Desarrollo\PiezaDecorativa;
use App\Modules\Nucleo\Enums\Ocasion;
use App\Modules\Nucleo\Enums\Temporada;
use App\Modules\Nucleo\Saludo\RegistroAmbientaciones;

/** El dibujo junto al saludo (y los colores de la franja): toda variante de cada fecha y estación. Valor `tema:clave`. */
final readonly class DibujoDelSaludo implements PiezaDecorativa
{
    public const string CLAVE = 'dibujo';

    public function __construct(private RegistroAmbientaciones $registro) {}

    public function clave(): string
    {
        return self::CLAVE;
    }

    public function nombre(): string
    {
        return 'Dibujo del saludo';
    }

    public function opciones(): array
    {
        $grupos = [];

        foreach ($this->registro->temas() as $tema) {
            $etiqueta = (Temporada::tryFrom($tema) ?? Ocasion::tryFrom($tema))?->etiqueta() ?? $tema;

            foreach ($this->registro->de($tema) as $variante) {
                $grupos[$etiqueta]["{$tema}:{$variante->clave}"] = $variante->nombre;
            }
        }

        return $grupos;
    }
}
