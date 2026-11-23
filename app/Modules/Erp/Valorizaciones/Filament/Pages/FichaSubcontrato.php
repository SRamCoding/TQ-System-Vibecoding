<?php

declare(strict_types=1);

namespace App\Modules\Erp\Valorizaciones\Filament\Pages;

use App\Modules\Erp\Valorizaciones\Services\SubcontratosService;
use App\Modules\Erp\Valorizaciones\Services\ValorizacionesService;
use App\Modules\Nucleo\Models\User;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Livewire\Attributes\Url;

/**
 * Observaciones 6, punto 12: la ficha de un subcontrato (doble clic en su tarjeta o «Abrir ficha» del panel): su
 * resumen, sus valorizaciones como línea de pasos y su alcance por partida con lo ejecutado contra lo comprometido.
 */
final class FichaSubcontrato extends Page
{
    use AccionesDeSubcontrato;

    protected static ?string $slug = 'subcontratos/ficha';

    protected static ?string $title = 'Subcontrato';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'valorizaciones::subcontratos.ficha';

    #[Url]
    public ?int $id = null;

    public static function canAccess(): bool
    {
        $u = Filament::auth()->user();

        return $u instanceof User && $u->can(ValorizacionesService::PERMISO_VER);
    }

    public function mount(): void
    {
        abort_if($this->id === null || $this->estado() === null, 404);
    }

    /** @return array<string, mixed>|null */
    public function estado(): ?array
    {
        return $this->id === null ? null : app(SubcontratosService::class)->estado($this->id);
    }

    public function getTitle(): string
    {
        $e = $this->estado();

        return $e === null ? 'Subcontrato' : "{$e['codigo']} · {$e['subcontratista']}";
    }

    public function getBreadcrumbs(): array
    {
        return [Subcontratos::getUrl() => 'Subcontratos', '#' => (string) ($this->estado()['codigo'] ?? 'Ficha')];
    }

    protected function getHeaderActions(): array
    {
        $e = $this->estado();

        return array_values(array_filter([
            $e !== null && ! $e['terminado'] ? $this->valorizarAction()->arguments(['sc' => $e['id']])->color('gray') : null,
            $e !== null && $e['pendientes'] !== [] ? $this->aprobarAction()->arguments(['id' => $e['pendientes'][0]['id']])->label('Aprobar val. '.$e['pendientes'][0]['numero']) : null,
            $e !== null && $e['listo_liberar'] ? $this->erpValorizacionesLiberarRetencionAction()->arguments(['id' => $e['id']]) : null,
        ]));
    }
}
