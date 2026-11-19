<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Estados;

use App\Modules\Nucleo\Services\BloqueoEdicion;
use Illuminate\Database\Eloquent\Model;

/**
 * Lo que todo documento con estados comparte (plan 1.5, D5, D6 y D8). En un estado no editable, guardar un cambio
 * falla, y la cabecera no se borra nunca: se anula (BloqueoEdicion); los campos derivados solo los cambia un efecto; el estado, al crear y
 * al cambiarlo, solo lo pone TransicionesService.
 *
 * @mixin Model
 */
trait TieneEstados
{
    public static function bootTieneEstados(): void
    {
        static::creating(fn (Model&DocumentoConEstados $documento) => app(BloqueoEdicion::class)->exigirTransicion($documento));
        static::updating(function (Model&DocumentoConEstados $documento): void {
            $bloqueo = app(BloqueoEdicion::class);
            $bloqueo->exigirTransicion($documento);
            $bloqueo->alCambiar($documento, $documento, $documento->camposDerivados());
        });
        static::deleting(fn (Model&DocumentoConEstados $documento) => app(BloqueoEdicion::class)->rechazarBorrado($documento));
    }

    /**
     * Sin ámbitos, solo cuenta el permiso global. Un documento de proyecto devolverá su proyecto y su unidad.
     *
     * @return list<Model>
     */
    public function ambitoDePermisos(): array
    {
        return [];
    }

    /**
     * Columnas que mantienen otros documentos (la cantidad recibida de una orden): solo cambian dentro de un efecto.
     *
     * @return list<string>
     */
    public function camposDerivados(): array
    {
        return [];
    }

    /** Si su estado actual admite cambios: para avisar antes de abrir un formulario o reemplazar un adjunto (1.8). */
    public function esEditable(): bool
    {
        return app(BloqueoEdicion::class)->esEditable($this);
    }
}
