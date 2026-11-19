<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Desarrollo\Piezas;

use App\Modules\Nucleo\Desarrollo\PiezaDecorativa;
use App\Modules\Nucleo\Enums\Ocasion;
use App\Modules\Nucleo\Saludo\RegistroGranMomento;

/**
 * El gran momento: cada variante de cada fecha. No se guarda en la sesión: elegirlo lo abre en la «Vista previa de
 * fechas» (se mira, no se marca como visto). Valor `ocasion:clave`.
 */
final readonly class GranMomentoDeFecha implements PiezaDecorativa
{
    public const string CLAVE = 'gran-momento';

    public function __construct(private RegistroGranMomento $registro) {}

    public function clave(): string
    {
        return self::CLAVE;
    }

    public function nombre(): string
    {
        return 'Gran momento';
    }

    public function opciones(): array
    {
        $grupos = [];

        foreach (Ocasion::cases() as $ocasion) {
            foreach ($this->registro->de($ocasion) as $variante) {
                $grupos[$ocasion->etiqueta()]["{$ocasion->value}:{$variante->clave}"] = $variante->nombre;
            }
        }

        return $grupos;
    }
}
