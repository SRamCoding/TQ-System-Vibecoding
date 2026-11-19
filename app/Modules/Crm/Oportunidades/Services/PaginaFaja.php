<?php

declare(strict_types=1);

namespace App\Modules\Crm\Oportunidades\Services;

/** Una página de la tabla de un lugar de la faja (plan 2.21b, D9). */
final readonly class PaginaFaja
{
    /**
     * @param  list<FilaFaja>  $filas
     */
    public function __construct(
        public array $filas,
        public int $total,
        public int $pagina,
        public int $porPagina,
    ) {}

    public function ultimaPagina(): int
    {
        return max(1, (int) ceil($this->total / $this->porPagina));
    }
}
