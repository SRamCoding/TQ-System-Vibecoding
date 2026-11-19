<?php

declare(strict_types=1);

namespace App\Modules\Crm\Oportunidades\Estados;

use App\Modules\Crm\Oportunidades\Models\Oportunidad;
use App\Modules\Nucleo\Estados\CondicionTransicion;
use App\Modules\Nucleo\Models\TransicionEstado;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Services\SeguridadService;
use Illuminate\Database\Eloquent\Model;

/**
 * «Propias» también en las transiciones (plan 2.21, D10.5): el núcleo revisa el permiso de una transición solo por
 * ámbitos. Aquí la misma regla que editar, con `puedeSobrePropio` (un solo lugar): editar todas, o editar las propias
 * y ser su ejecutivo. Así un ejecutivo mueve solo las suyas, también sin pantalla. La 2.0b la llevará al núcleo.
 */
final readonly class SobreLoPropio implements CondicionTransicion
{
    public const string EDITAR = 'crm.oportunidades.editar';

    public const string EDITAR_PROPIAS = 'crm.oportunidades.editar-propias';

    public function revisar(Model $documento, User $usuario, TransicionEstado $transicion): ?string
    {
        /** @var Oportunidad $documento */
        return app(SeguridadService::class)->puedeSobrePropio($usuario, self::EDITAR, self::EDITAR_PROPIAS, $documento->ejecutivo_user_id)
            ? null
            : 'Solo su ejecutivo, o quien puede editar todas las oportunidades, puede hacerlo.';
    }
}
