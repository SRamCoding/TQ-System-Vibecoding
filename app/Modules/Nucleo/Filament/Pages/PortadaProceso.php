<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Filament\Pages;

use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Navegacion\CajaModulo;
use App\Modules\Nucleo\Navegacion\Proceso;
use App\Modules\Nucleo\Services\NavegacionService;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;

/**
 * La portada de un proceso (MV-020): al abrir su caja del inicio, sus cajas internas (las pantallas que agrupa), del
 * tamaño de las de una subparte. Cada una abre su pantalla en la misma pestaña; allá, pestañas con sus hermanas y la
 * miga para volver aquí.
 */
final class PortadaProceso extends Page
{
    protected static ?string $slug = 'procesos/{proceso}';

    protected static bool $shouldRegisterNavigation = false;

    public string $proceso = '';

    public static function canAccess(): bool
    {
        return Filament::auth()->user() instanceof User;
    }

    public function mount(string $proceso): void
    {
        $this->proceso = $proceso;
        abort_if($this->datos() === null, 404);
    }

    public function getTitle(): string
    {
        return Proceso::from($this->proceso)->nombre();
    }

    public function getSubheading(): string
    {
        return Proceso::from($this->proceso)->descripcion().'.';
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            View::make('nucleo::filament.procesos.portada')->viewData(fn (): array => ['datos' => $this->datos()]),
        ]);
    }

    /** @return array{proceso: Proceso, caja: CajaModulo, internas: list<CajaModulo>}|null */
    private function datos(): ?array
    {
        $usuario = Filament::auth()->user();

        if (! $usuario instanceof User || Proceso::tryFrom($this->proceso) === null) {
            return null;
        }

        foreach (app(NavegacionService::class)->procesos($usuario) as $datos) {
            if ($datos['proceso']->value === $this->proceso) {
                return $datos;
            }
        }

        return null;
    }
}
