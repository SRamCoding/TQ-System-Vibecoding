<?php

declare(strict_types=1);

namespace App\Modules\Crm\Oportunidades\Filament;

use App\Modules\Crm\Oportunidades\Requisitos\RequisitosDeEtapa;
use App\Modules\Nucleo\Excepciones\ErrorDeNegocio;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\EjecutorAcciones;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

/**
 * Cumplir un requisito desde la compuerta del avance (plan 2.21d, D8): el botón de un requisito que falta ejecuta la
 * acción registrada que da su módulo (`SeCumpleEnLaCompuerta::formaDeCumplir`), con los datos que esa forma trae. El
 * diálogo sigue abierto y se redibuja con el requisito ya cumplido. Lo usan las pantallas que abren el avance.
 */
trait CumpleRequisitos
{
    public function cumplirRequisito(string $requisito, int $oportunidadId): void
    {
        $forma = app(RequisitosDeEtapa::class)->paraCumplir($requisito)?->formaDeCumplir($oportunidadId);
        $usuario = Filament::auth()->user();

        if ($forma?->accion === null || ! $usuario instanceof User) {
            Notification::make()->warning()->title('Ese requisito no se cumple desde aquí.')->send();

            return;
        }

        try {
            app(EjecutorAcciones::class)->ejecutar($forma->accion, $forma->datos, $usuario);
            Notification::make()->success()->title('Listo: '.mb_lcfirst($forma->etiqueta).'.')->send();
        } catch (ErrorDeNegocio $e) {
            Notification::make()->danger()->title($e->getMessage())->send();
        } catch (AuthorizationException) {
            Notification::make()->danger()->title('No tienes permiso para hacerlo.')->send();
        } catch (ValidationException $e) {
            Notification::make()->danger()->title((string) (collect($e->errors())->flatten()->first() ?? 'Revisa los datos.'))->send();
        }
    }
}
