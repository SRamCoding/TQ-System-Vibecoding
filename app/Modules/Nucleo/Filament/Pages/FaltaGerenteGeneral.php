<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Filament\Pages;

use App\Modules\Nucleo\Services\SeguridadService;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;

/**
 * «Falta registrar al gerente general» (plan 1.10, D2): lo único que se ve mientras no hay un gerente general activo
 * (ExigirGerenteGeneral). Lo registra quien instala el servidor, con `php artisan seguridad:gerente-general`.
 */
final class FaltaGerenteGeneral extends Page
{
    protected static ?string $slug = 'falta-gerente-general';

    protected static ?string $title = 'Falta registrar al gerente general';

    protected static bool $shouldRegisterNavigation = false;

    public static function canAccess(): bool
    {
        return ! app(SeguridadService::class)->hayGerenteGeneralActivo();
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('El sistema todavía no puede arrancar')
                ->description('Las alertas de control (cruces de funciones, autoaprobaciones) le llegan al gerente general. Sin una cuenta activa con ese rol, nadie las recibiría.')
                ->schema([
                    Text::make('Lo registra quien instaló el servidor, con el comando «php artisan seguridad:gerente-general». Después, esta pantalla desaparece sola.'),
                ]),
        ]);
    }
}
