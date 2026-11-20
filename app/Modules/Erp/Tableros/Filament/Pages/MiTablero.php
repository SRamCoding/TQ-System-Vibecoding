<?php

declare(strict_types=1);

namespace App\Modules\Erp\Tableros\Filament\Pages;

use App\Modules\Erp\Tableros\Services\TablerosService;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Tiempo\Calendario;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Livewire\Attributes\Url;

/** MVP rápido (t8-tableros): el tablero de inicio de cada rol, con una pestaña por cada rol que la persona puede ver. */
final class MiTablero extends Page
{
    protected static ?string $slug = 'mi-tablero';

    protected static ?string $title = 'Mi tablero';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-presentation-chart-bar';

    #[Url]
    public ?string $rol = null;

    /** @return array<string, string> */
    public static function rolesDe(?User $u): array
    {
        return collect(TablerosService::ROLES)->filter(fn (array $r): bool => $u?->can($r[1]) ?? false)->map(fn (array $r): string => $r[0])->all();
    }

    public static function canAccess(): bool
    {
        $u = Filament::auth()->user();

        return $u instanceof User && self::rolesDe($u) !== [];
    }

    public function mount(): void
    {
        $roles = self::rolesDe(Filament::auth()->user());

        if (! isset($roles[$this->rol ?? ''])) {
            $this->rol = array_key_first($roles);
        }
    }

    public function getSubheading(): string
    {
        return TablerosService::ROLES[$this->rol][0].' · '.Calendario::ahora()->locale('es')->isoFormat('dddd D [de] MMMM');
    }

    public function content(Schema $schema): Schema
    {
        $t = app(TablerosService::class);
        $datos = match ($this->rol) {
            'almacen' => $t->almacen(),
            'tesoreria' => $t->tesoreria(),
            'comercial' => $t->comercial(),
            'gerencia' => $t->gerencia(),
            default => $t->jefatura(),
        };

        return $schema->components([View::make('tableros::tablero')->viewData(['t' => $datos, 'roles' => self::rolesDe(Filament::auth()->user()), 'actual' => $this->rol])]);
    }
}
