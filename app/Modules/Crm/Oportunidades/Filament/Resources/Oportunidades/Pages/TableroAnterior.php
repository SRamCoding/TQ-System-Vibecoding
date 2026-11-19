<?php

declare(strict_types=1);

namespace App\Modules\Crm\Oportunidades\Filament\Resources\Oportunidades\Pages;

use App\Modules\Crm\Oportunidades\Filament\Resources\Oportunidades\OportunidadResource;
use App\Modules\Crm\Oportunidades\OportunidadesServiceProvider;
use Filament\Resources\Pages\Page;

/**
 * La dirección vieja `/oportunidades/tablero` (la faja de la 2.21b): desde la 2.21c lleva al Tablero de gemas, que es
 * la vista de entrada. Un enlace guardado sigue sirviendo; la faja está en `/oportunidades/faja`.
 */
final class TableroAnterior extends Page
{
    protected static string $resource = OportunidadResource::class;

    public static function canAccess(array $parameters = []): bool
    {
        return OportunidadResource::usuarioPuede(OportunidadesServiceProvider::PERMISO_VER);
    }

    public function mount(): void
    {
        // Con lo que traía la dirección (`?ficha=`, `?aviso=`…): el Tablero lo atiende igual.
        $this->redirect(TableroOportunidades::getUrl(request()->query()));
    }
}
