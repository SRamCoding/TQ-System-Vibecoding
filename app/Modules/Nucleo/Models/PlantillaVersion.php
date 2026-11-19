<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Models;

use App\Modules\Nucleo\Models\Concerns\ConAuditoria;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Una versión de una plantilla (ADR 0022; plan 2.11, D3 y D4): su contenido, en el formato de su tipo, y de qué
 * documento se guardó. Inmutable: editar una plantilla crea la versión siguiente, y lo creado con una versión guarda
 * su id (`plantilla_version_id`). El contenido lo interpreta su tipo, nunca el núcleo.
 *
 * @property int $id
 * @property int $plantilla_id
 * @property int $numero
 * @property int $formato
 * @property array<string, mixed> $contenido
 * @property ?string $origen_documento
 * @property ?int $origen_id
 * @property CarbonInterface $created_at
 */
class PlantillaVersion extends Model
{
    use ConAuditoria;

    public const UPDATED_AT = null;

    protected $table = 'plantilla_versiones';

    protected $fillable = ['plantilla_id', 'numero', 'formato', 'contenido', 'origen_documento', 'origen_id'];

    /** D5: el contenido puede llevar montos sensibles; solo se envía al navegador por su tipo, que revisa el permiso. */
    protected $hidden = ['contenido'];

    protected static function booted(): void
    {
        static::updating(fn (): never => throw new LogicException('Una versión de plantilla no se modifica: se crea la siguiente.'));
        static::deleting(fn (): never => throw new LogicException('Una versión de plantilla no se borra.'));
    }

    /**
     * @return BelongsTo<Plantilla, $this>
     */
    public function plantilla(): BelongsTo
    {
        return $this->belongsTo(Plantilla::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['numero' => 'integer', 'formato' => 'integer', 'contenido' => 'array', 'origen_id' => 'integer'];
    }
}
