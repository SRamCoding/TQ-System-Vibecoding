<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Filament\Resources\Usuarios\Pages;

use App\Modules\Nucleo\Filament\AccionFilament;
use App\Modules\Nucleo\Filament\Resources\Usuarios\UsuarioResource;
use App\Modules\Nucleo\Filament\SelectoresSeguridad;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

final class ListarUsuarios extends ListRecords
{
    protected static string $resource = UsuarioResource::class;

    protected static ?string $title = 'Usuarios';

    /**
     * @return list<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            AccionFilament::desde('nucleo.seguridad.registrar-usuario', alTerminar: function (mixed $id): void {
                Notification::make()->success()->title('Cuenta creada. La persona pone su clave con «¿Olvidaste tu contraseña?».')->send();
                $this->redirect(UsuarioResource::getUrl('view', ['record' => $id]));
            }, controles: [
                'party_id' => fn () => SelectoresSeguridad::personaSinCuenta('party_id'),
                'puesto_id' => fn () => SelectoresSeguridad::puesto('puesto_id'),
            ])
                ->label('Registrar usuario')->icon('heroicon-o-user-plus')
                ->modalDescription('Busca a la persona por nombre o documento (DNI, carné de extranjería o pasaporte). Los roles se dan en su ficha.'),
        ];
    }
}
