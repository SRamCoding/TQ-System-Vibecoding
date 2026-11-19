<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Estados;

use App\Modules\Nucleo\Services\BloqueoEdicion;
use Illuminate\Database\Eloquent\Model;

/**
 * Una tabla hija de un documento con estados, como sus líneas (plan 1.5, D8): sigue el estado de su documento. En
 * uno no editable no se agrega, no se cambia ni se borra; sus campos derivados solo los cambia un efecto.
 *
 * @mixin Model
 */
trait ParteDeDocumento
{
    public static function bootParteDeDocumento(): void
    {
        $bloqueo = fn (): BloqueoEdicion => app(BloqueoEdicion::class);

        static::creating(fn (self $parte) => $bloqueo()->exigirEditable($parte->documentoConEstados()));
        static::updating(fn (self $parte) => $bloqueo()->alCambiar($parte, $parte->documentoConEstados(), $parte->camposDerivados()));
        static::deleting(fn (self $parte) => $bloqueo()->exigirEditable($parte->documentoConEstados()));
    }

    /** El documento al que pertenece. */
    abstract public function documentoConEstados(): Model&DocumentoConEstados;

    /**
     * @return list<string>
     */
    public function camposDerivados(): array
    {
        return [];
    }
}
