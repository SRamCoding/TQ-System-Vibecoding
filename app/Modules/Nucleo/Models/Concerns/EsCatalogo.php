<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Lo común a todo catálogo de M5: un `codigo` único e inmutable, una columna de activo (el DBML mezcla
 * `es_activo` y `es_activa`), una etiqueta para mostrar y `version` para el bloqueo optimista. Solo se
 * borra lo que nunca se usó; lo usado se desactiva (plan 1.2b, E1).
 *
 * Se edita desde la pantalla y mueve dinero (signo de un documento, decimales de una moneda): cada
 * cambio queda en la bitácora (E11). El modelo debe usar también ConAuditoria.
 *
 * @mixin Model
 */
trait EsCatalogo
{
    use BuscaSinAcentos, RegistraCambios;

    /**
     * `version` sube en cada cambio: anotarla en la bitácora solo agrega ruido.
     *
     * @return list<string>
     */
    public function camposSinBitacora(): array
    {
        return ['version'];
    }

    /** El DBML usa `es_activa` en los catálogos de nombre femenino; el modelo lo redefine. */
    public static function columnaActivo(): string
    {
        return 'es_activo';
    }

    /**
     * @param  Builder<static>  $consulta
     */
    public function scopeActivos(Builder $consulta): void
    {
        $consulta->where(static::columnaActivo(), true);
    }

    /** Cómo se muestra en listas y selectores. */
    public function etiqueta(): string
    {
        return (string) $this->getAttribute('nombre');
    }

    public function estaActivo(): bool
    {
        return (bool) $this->getAttribute(static::columnaActivo());
    }
}
