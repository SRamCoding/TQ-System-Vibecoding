<?php

declare(strict_types=1);

namespace App\Modules\Crm\Oportunidades\Filament\Resources\Oportunidades;

use App\Modules\Crm\Oportunidades\OportunidadesServiceProvider;
use App\Modules\Crm\Oportunidades\Services\FichaComercialService;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Throwable;

/**
 * Observación 9.5 (8): las personas del cliente en este negocio, con su cargo, su papel (Decide, Influye, Usa, Bloquea)
 * y su postura (a favor, neutral, en contra, con nota). Se eligen de los contactos del cliente o se escribe una nueva.
 */
final class QuienDecideDeOportunidad extends Component
{
    #[Locked]
    public int $oportunidadId;

    public bool $agregando = false;

    public ?string $contactoId = null;

    public string $nombre = '';

    public string $cargo = '';

    public string $papel = 'influye';

    public string $postura = 'neutral';

    public string $nota = '';

    public function mount(): void
    {
        abort_unless(Filament::auth()->user()?->can(OportunidadesServiceProvider::PERMISO_VER) ?? false, 403);
    }

    public function guardar(): void
    {
        try {
            app(FichaComercialService::class)->guardarDecisor($this->oportunidadId, is_numeric($this->contactoId) ? (int) $this->contactoId : null, $this->nombre, $this->cargo, $this->papel, $this->postura, $this->nota);
            $this->reset('agregando', 'contactoId', 'nombre', 'cargo', 'papel', 'postura', 'nota');
            Notification::make()->success()->title('Persona agregada.')->send();
        } catch (Throwable $e) {
            Notification::make()->danger()->title($e->getMessage())->send();
        }
    }

    public function quitar(int $id): void
    {
        app(FichaComercialService::class)->quitarDecisor($id);
    }

    public function render(): View
    {
        $s = app(FichaComercialService::class);

        return view('oportunidades::ficha.quien-decide', [
            'personas' => $s->decisores($this->oportunidadId), 'contactos' => $this->agregando ? $s->contactosDelCliente($this->oportunidadId) : [],
            'papeles' => FichaComercialService::PAPELES, 'posturas' => FichaComercialService::POSTURAS,
        ]);
    }
}
