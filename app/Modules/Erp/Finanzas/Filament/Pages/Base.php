<?php

declare(strict_types=1);

namespace App\Modules\Erp\Finanzas\Filament\Pages;

use App\Modules\Erp\Finanzas\Services\FinanzasService;
use App\Modules\Nucleo\Models\User;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use RuntimeException;

/** MVP rápido (t5-finanzas): lo común de las pantallas de finanzas. */
abstract class Base extends Page
{
    public static function canAccess(): bool
    {
        $u = Filament::auth()->user();

        return $u instanceof User && $u->can(FinanzasService::PERMISO_VER);
    }

    public static function puede(string $permiso): bool
    {
        return auth()->user()?->can($permiso) ?? false;
    }

    public static function s(mixed $m): string
    {
        return number_format((float) $m, 2);
    }

    protected function hacer(\Closure $paso, string $exito): void
    {
        try {
            $paso();
            Notification::make()->success()->title($exito)->send();
        } catch (RuntimeException $e) {
            Notification::make()->danger()->title($e->getMessage())->send();
        }
    }
}
