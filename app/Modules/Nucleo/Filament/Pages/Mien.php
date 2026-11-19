<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Filament\Pages;

use App\Modules\Nucleo\Mien\RegistroTarjetasMien;
use App\Modules\Nucleo\Mien\TarjetaMien;
use App\Modules\Nucleo\Models\User;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;

/**
 * «Mien», el tablero personal (plan 1.4l): lo relevante del negocio para cada persona, en tarjetas que aporta cada
 * módulo (RegistroTarjetasMien). Se abre desde su caja del área «Personal».
 */
final class Mien extends Page
{
    protected static ?string $slug = 'mien';

    protected static ?string $title = 'Mien';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-star';

    /** La barra de módulos se arma con las cajas: la de «Mien» la pone ahí. */
    protected static bool $shouldRegisterNavigation = false;

    public static function canAccess(): bool
    {
        return Filament::auth()->user() instanceof User;
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            View::make('nucleo::filament.mien.tablero')->viewData(fn (): array => ['tarjetas' => $this->tarjetas()]),
        ]);
    }

    /** @return list<TarjetaMien> */
    private function tarjetas(): array
    {
        $usuario = Filament::auth()->user();

        return $usuario instanceof User ? app(RegistroTarjetasMien::class)->para($usuario) : [];
    }
}
