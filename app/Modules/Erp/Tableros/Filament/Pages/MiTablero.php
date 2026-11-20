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
use Illuminate\Support\HtmlString;
use Livewire\Attributes\Url;

/** MVP rápido (t8-tableros): el tablero de inicio de cada rol, con una pestaña por cada rol que la persona puede ver. */
final class MiTablero extends Page
{
    protected static bool $shouldRegisterNavigation = false;

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

    /** MV-017: el tablero ahora está dentro de Mien; esta dirección queda para los enlaces viejos. */
    public function mount(): void
    {
        $this->redirect('/mien'.($this->rol !== null ? '?rol='.urlencode($this->rol) : ''));
    }

    /**
     * MV-052: las listas del tablero del primer rol de la persona, como grupos del globo de Mien.
     *
     * @return list<array{titulo: string, items: list<array{texto: string, nota: ?string, url: string, tono: ?string}>}>
     */
    public static function gruposParaMien(User $usuario): array
    {
        $roles = self::rolesDe($usuario);

        if ($roles === []) {
            return [];
        }

        $rol = array_key_first($roles);
        $t = app(TablerosService::class);
        $datos = match ($rol) {
            'almacen' => $t->almacen(), 'tesoreria' => $t->tesoreria(), 'comercial' => $t->comercial(), 'gerencia' => $t->gerencia(), default => $t->jefatura()
        };

        return array_map(fn (array $l): array => ['titulo' => $l['titulo'].' · '.$roles[$rol], 'items' => array_map(fn (array $i): array => [
            'texto' => (string) $i['texto'], 'nota' => $i['nota'] ?? null, 'url' => (string) ($i['url'] ?? '/mien'), 'tono' => $i['tono'] ?? null,
        ], $l['items'])], $datos['listas']);
    }

    /** El tablero del rol, con sus pestañas, para la sección ancha de Mien; null si la persona no tiene ningún rol con tablero. */
    public static function seccionParaMien(User $usuario, ?string $rol): ?HtmlString
    {
        $roles = self::rolesDe($usuario);

        if ($roles === []) {
            return null;
        }

        $rol = isset($roles[$rol ?? '']) ? $rol : array_key_first($roles);
        $t = app(TablerosService::class);
        $datos = match ($rol) {
            'almacen' => $t->almacen(),
            'tesoreria' => $t->tesoreria(),
            'comercial' => $t->comercial(),
            'gerencia' => $t->gerencia(),
            default => $t->jefatura(),
        };

        return new HtmlString(view('tableros::tablero', ['t' => $datos, 'roles' => $roles, 'actual' => $rol, 'enMien' => true])->render());
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
