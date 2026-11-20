<?php

declare(strict_types=1);

namespace App\Modules\Erp\Postventa\Filament\Pages;

use App\Modules\Erp\Postventa\Services\PostventaService;
use App\Modules\Nucleo\Models\User;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Livewire\Attributes\Url;

/** MVP rápido (t6-postventa): la vida completa de un equipo por su serie. Es lo primero que abre Help Desk. */
final class Rastreo extends Page
{
    protected static ?string $slug = 'rastreo-serie';

    protected static ?string $title = 'Rastreo de serie';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-magnifying-glass';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'postventa::rastreo';

    #[Url]
    public string $serie = '';

    public static function canAccess(): bool
    {
        $u = Filament::auth()->user();

        return $u instanceof User && $u->can(PostventaService::PERMISO_VER);
    }

    public function equipo(): ?object
    {
        return app(PostventaService::class)->rastrear($this->serie);
    }

    public function garantia(?object $s): string
    {
        return app(PostventaService::class)->garantia($s);
    }
}
