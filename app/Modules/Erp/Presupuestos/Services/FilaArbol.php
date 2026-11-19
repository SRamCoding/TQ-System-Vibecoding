<?php

declare(strict_types=1);

namespace App\Modules\Erp\Presupuestos\Services;

/**
 * Una partida del árbol como la muestra la pestaña «Partidas de control» (plan 2.10, paso 3). El presupuestado de una
 * hoja es el suyo; el de un padre, la suma de sus hojas activas (v4, parte 6: «solo en hojas; los padres suman»).
 */
final readonly class FilaArbol
{
    public function __construct(
        public int $id,
        public ?int $padreId,
        public string $codigo,
        public string $nombre,
        /** 0 en la raíz, 1 bajo una partida de la raíz, y así. */
        public int $nivel,
        public bool $esHoja,
        public bool $esActiva,
        public bool $esGastosGenerales,
        public bool $esNoPresupuestado,
        public int $responsableUserId,
        public string $responsable,
        public string $presupuestado,
        public int $version,
        /** De dónde salió (plan 2.9b, D6): «título 01», «01.01 · Tendido de cable UTP», «gastos generales del presupuesto». */
        public ?string $origen = null,
    ) {}
}
