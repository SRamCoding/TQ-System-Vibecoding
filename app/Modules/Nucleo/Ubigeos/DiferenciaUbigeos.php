<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Ubigeos;

/** Qué cambia entre la lista guardada en el repositorio y la recién descargada. */
final readonly class DiferenciaUbigeos
{
    /**
     * @param  list<DistritoUbigeo>  $nuevos
     * @param  list<array{antes: DistritoUbigeo, ahora: DistritoUbigeo}>  $renombrados
     * @param  list<DistritoUbigeo>  $retirados
     * @param  list<DistritoUbigeo>  $adoptados  agregados a mano que el INEI ya publica: pasan a ser suyos (plan 1.2b, E7)
     */
    public function __construct(
        public array $nuevos,
        public array $renombrados,
        public array $retirados,
        public array $adoptados = [],
    ) {}

    public function hayCambios(): bool
    {
        return $this->nuevos !== [] || $this->renombrados !== [] || $this->retirados !== [] || $this->adoptados !== [];
    }
}
