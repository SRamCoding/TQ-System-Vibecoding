<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Ubigeos;

/** Un distrito de la lista oficial del INEI. */
final readonly class DistritoUbigeo
{
    public function __construct(
        public string $codigo,
        public string $departamento,
        public string $provincia,
        public string $distrito,
    ) {}

    public function mismoNombre(self $otro): bool
    {
        return [$this->departamento, $this->provincia, $this->distrito]
            === [$otro->departamento, $otro->provincia, $otro->distrito];
    }

    public function descripcion(): string
    {
        return "{$this->codigo} · {$this->departamento} / {$this->provincia} / {$this->distrito}";
    }
}
