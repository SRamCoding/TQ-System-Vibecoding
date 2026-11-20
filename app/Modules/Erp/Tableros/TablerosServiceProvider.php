<?php

declare(strict_types=1);

namespace App\Modules\Erp\Tableros;

use App\Modules\Erp\Tableros\Filament\Pages\MiTablero;
use App\Modules\Nucleo\Mien\RegistroSeccionesMien;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Navegacion\RegistroPantallas;
use Illuminate\Support\HtmlString;
use Illuminate\Support\ServiceProvider;

/** MVP rápido (ficha t8-tableros): el tablero de inicio de cada rol (v4, parte 5). */
final class TablerosServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->make(RegistroPantallas::class)->registrar('app', paginas: [MiTablero::class]);
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/resources/views', 'tableros');
        // MV-017: el tablero por rol vive ahora dentro de Mien, debajo de sus tarjetas; /mi-tablero lleva allá.
        $this->app->make(RegistroSeccionesMien::class)->registrar('tablero-rol', fn (User $usuario, ?string $rol): ?HtmlString => MiTablero::seccionParaMien($usuario, $rol));
    }
}
