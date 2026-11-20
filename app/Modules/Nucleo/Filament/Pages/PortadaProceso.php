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
 * La entrada de un proceso (MV-035, reemplaza MV-020): lleva a su caja del medio; la barra del proceso (frase y cajas)
 * la pone el gancho PAGE_START en cada pantalla del proceso.
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

    /** MV-035: entrar a un proceso abre su caja del medio, en el orden de la persona. */
    public function mount(string $proceso): void
    {
        $this->proceso = $proceso;
        $datos = $this->datos();
        abort_if($datos === null, 404);
        $this->redirect(NavegacionService::cajaDelMedio($datos['internas'])->url, navigate: true);
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
