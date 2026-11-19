<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Filament;

use App\Modules\Nucleo\Excepciones\ErrorDeNegocio;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\EjecutorAcciones;
use Filament\Actions\Action;
use Filament\Livewire\DatabaseNotifications;
use Filament\Notifications\Notification;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\On;

/**
 * La campana de avisos (plan 1.4y, D1), sobre la de Filament. Muestra solo los no leídos; cada uno tiene «Marcar como
 * leído» y arriba «Marcar todos como leídos»; al pie, «Ver todos mis avisos». Nadie borra un aviso: sin «Borrar» ni la
 * ✕, y sus métodos no hacen nada aunque se los llame desde el navegador. Todo pasa por las acciones registradas.
 *
 * Además arregla lo de Filament con nuestros ids enteros: sus métodos de un solo aviso solo aceptan UUID, así que la ✕
 * y «Abrir» (que lo marca leído) no hacían nada.
 */
final class CampanaAvisos extends DatabaseNotifications
{
    /**
     * Solo los no leídos: los leídos están en «Mis avisos».
     *
     * @return Relation<DatabaseNotification, User, mixed>
     */
    public function getNotificationsQuery(): Relation
    {
        /** @var Relation<DatabaseNotification, User, mixed> $consulta */
        $consulta = parent::getNotificationsQuery();

        return $consulta->whereNull('read_at');
    }

    /** Nadie borra un aviso (D1): la ✕ no se ve y, si se la llamara, no hace nada. */
    #[On('notificationClosed')]
    public function removeNotification(string $id): void {}

    public function clearNotifications(): void {}

    public function clearNotificationsAction(): Action
    {
        return parent::clearNotificationsAction()->hidden();
    }

    #[On('markedNotificationAsRead')]
    public function markNotificationAsRead(string $id): void
    {
        if (ctype_digit($id)) {
            $this->ejecutar('marcar-aviso-leido', ['aviso_id' => (int) $id]);
        }
    }

    #[On('markedNotificationAsUnread')]
    public function markNotificationAsUnread(string $id): void
    {
        if (ctype_digit($id)) {
            $this->ejecutar('marcar-aviso-no-leido', ['aviso_id' => (int) $id]);
        }
    }

    public function markAllNotificationsAsRead(): void
    {
        $this->ejecutar('marcar-avisos-leidos', []);
    }

    public function markAllNotificationsAsReadAction(): Action
    {
        return parent::markAllNotificationsAsReadAction()->label('Marcar todos como leídos');
    }

    /** Cada aviso, con «Marcar como leído» además de lo que ya traía («Abrir»). */
    public function getNotification(DatabaseNotification $notification): Notification
    {
        $aviso = parent::getNotification($notification);

        return $aviso->actions([
            ...$aviso->getActions(),
            Action::make('marcarLeido')->label('Marcar como leído')->link()->color('gray')->markAsRead(),
        ]);
    }

    public function render(): View
    {
        return view('nucleo::filament.campana-avisos');
    }

    /** @param array<string, mixed> $datos */
    private function ejecutar(string $accion, array $datos): void
    {
        $usuario = $this->getUser();

        if (! $usuario instanceof User) {
            return;
        }

        try {
            app(EjecutorAcciones::class)->ejecutar("nucleo.{$accion}", $datos, $usuario);
        } catch (ErrorDeNegocio|ValidationException $e) {
            Notification::make()->title($e instanceof ErrorDeNegocio ? $e->getMessage() : 'No se pudo marcar el aviso.')->danger()->send();
        }
    }
}
