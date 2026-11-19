<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Filament\Resources\Organigrama\Pages;

use App\Modules\Nucleo\Filament\AccionFilament;
use App\Modules\Nucleo\Filament\Resources\Organigrama\UnidadOrganizativaResource;
use App\Modules\Nucleo\Filament\SelectoresSeguridad;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

final class ListarUnidades extends ListRecords
{
    protected static string $resource = UnidadOrganizativaResource::class;

    protected static ?string $title = 'Organigrama';

    /**
     * @return list<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            AccionFilament::desde('nucleo.organigrama.crear-unidad', alTerminar: function (): void {
                Notification::make()->success()->title('Unidad creada.')->send();
            }, controles: ['superior_id' => fn () => SelectoresSeguridad::unidad('superior_id')])
                ->label('Crear unidad')->icon('heroicon-o-plus')
                ->modalDescription('Sin «Depende de», queda en la raíz del organigrama. Sus puestos y su jefatura se definen después.'),
        ];
    }
}
