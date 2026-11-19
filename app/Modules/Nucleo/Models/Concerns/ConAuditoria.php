<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Models\Concerns;

use App\Modules\Nucleo\Services\ContextoAuditoria;
use Illuminate\Database\Eloquent\Model;

/**
 * Llena created_by y updated_by (M4) en todo modelo de una tabla con auditoria(). El autor lo
 * decide ContextoAuditoria; el código de negocio nunca lo asigna a mano.
 *
 * @mixin Model
 */
trait ConAuditoria
{
    public static function bootConAuditoria(): void
    {
        // Siempre se sobrescribe: un autor puesto a mano (forceFill, factory) no se respeta.
        static::creating(function (Model $modelo): void {
            $modelo->setAttribute('created_by', self::idAutor($modelo));
        });

        static::updating(function (Model $modelo): void {
            $modelo->setAttribute('updated_by', self::idAutor($modelo));
        });
    }

    /**
     * Autor cuando no hay sesión ni consola. Por defecto ninguno: se rechaza el cambio.
     */
    public function autorSinContexto(): ?int
    {
        return null;
    }

    private static function idAutor(Model $modelo): int
    {
        $porDefecto = method_exists($modelo, 'autorSinContexto') ? $modelo->autorSinContexto() : null;

        return app(ContextoAuditoria::class)->idActor($porDefecto);
    }
}
