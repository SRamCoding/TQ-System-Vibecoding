<?php

declare(strict_types=1);

namespace App\Modules\Erp\Presupuestos\Services;

use App\Modules\Erp\Presupuestos\Excepciones\ArbolRechazado;

/**
 * Lo que la persona cambia en la propuesta antes de confirmarla (plan 2.9b, D5): el nombre y el responsable de cada
 * partida, y las que quita. Reagrupar, mover, agregar y fijar montos van después, en el borrador. El origen de cada
 * partida no se cambia (D6).
 */
final readonly class AjustesPropuesta
{
    /**
     * @param  array<string, string>  $nombres  por código
     * @param  array<string, int>  $responsables  por código
     * @param  list<string>  $quitar  códigos; quitar un título quita sus hijas
     */
    public function __construct(
        public array $nombres = [],
        public array $responsables = [],
        public array $quitar = [],
        /** El responsable para las que no tienen uno propio; si no, el de la propuesta (D4). */
        public ?int $responsablePorOmision = null,
    ) {}

    /**
     * Desde los campos de la acción, como `CÓDIGO=valor` («PC-01.01=Cámaras del parque», «PC-02=12»): así una acción
     * registrada lleva los ajustes por partida con los tipos de campo que ya existen (listas de textos).
     *
     * @param  list<string>  $nombres
     * @param  list<string>  $responsables
     * @param  list<string>  $quitar
     */
    public static function desdeCampos(array $nombres, array $responsables, array $quitar, ?int $responsablePorOmision): self
    {
        $ids = [];

        foreach (self::pares($responsables, 'responsable') as $codigo => $valor) {
            if (preg_match('/^\d+$/', $valor) !== 1) {
                throw ArbolRechazado::conMotivo("El responsable de {$codigo} va como el número del usuario; «{$valor}» no sirve.");
            }

            $ids[$codigo] = (int) $valor;
        }

        return new self(self::pares($nombres, 'nombre'), $ids, array_map(fn (string $c): string => strtoupper(trim($c)), $quitar), $responsablePorOmision);
    }

    /**
     * @param  list<string>  $lineas
     * @return array<string, string>
     */
    private static function pares(array $lineas, string $que): array
    {
        $pares = [];

        foreach ($lineas as $linea) {
            $partes = explode('=', $linea, 2);

            if (count($partes) !== 2 || trim($partes[0]) === '') {
                throw ArbolRechazado::conMotivo("Cada {$que} va como «PC-01=…»; «{$linea}» no sirve.");
            }

            $pares[strtoupper(trim($partes[0]))] = trim($partes[1]);
        }

        return $pares;
    }
}
