<?php

declare(strict_types=1);

namespace Tests\Modules\Nucleo\Fixtures;

final class EfectoB extends EfectoQueAnota
{
    protected function letra(): string
    {
        return 'B';
    }
}
