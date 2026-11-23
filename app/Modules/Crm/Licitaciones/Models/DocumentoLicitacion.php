<?php

declare(strict_types=1);

namespace App\Modules\Crm\Licitaciones\Models;

use App\Modules\Nucleo\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** MVP rápido (t1-licitaciones): parte de una licitación. */
class DocumentoLicitacion extends Model
{
    protected $table = 'licitacion_documentos';

    /** Observación 7.3: los estados de un documento del expediente, con su color. */
    public const array ESTADOS = ['firmado' => 'Firmado', 'listo' => 'Listo', 'en_revision' => 'En revisión', 'falta' => 'Falta', 'no_aplica' => 'No aplica'];

    /** Los que cuentan como listos para presentar. */
    public const array LISTOS = ['firmado', 'listo', 'no_aplica'];

    protected $fillable = ['licitacion_id', 'nombre', 'origen', 'es_confirmado', 'es_listo', 'nota', 'vence', 'orden', 'estado', 'responsable_user_id', 'archivo', 'requiere_firma'];

    protected function casts(): array
    {
        return ['es_confirmado' => 'boolean', 'es_listo' => 'boolean', 'vence' => 'date', 'orden' => 'integer', 'requiere_firma' => 'boolean'];
    }

    protected static function booted(): void
    {
        // `es_listo` (lo que ya usaban las otras pantallas) sigue al estado.
        static::saving(function (self $d): void {
            $d->es_listo = in_array($d->estado, self::LISTOS, true);
        });
    }

    /** @return BelongsTo<User, $this> */
    public function responsable(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsable_user_id');
    }

    /** @return BelongsTo<Licitacion, $this> */
    public function licitacion(): BelongsTo
    {
        return $this->belongsTo(Licitacion::class);
    }
}
