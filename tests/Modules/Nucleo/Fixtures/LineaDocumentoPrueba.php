<?php

declare(strict_types=1);

namespace Tests\Modules\Nucleo\Fixtures;

use App\Modules\Nucleo\Estados\DocumentoConEstados;
use App\Modules\Nucleo\Estados\ParteDeDocumento;
use App\Modules\Nucleo\Models\Concerns\ConAuditoria;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Línea del documento de prueba (plan 1.5). `cantidad_recibida` hace de campo derivado: solo lo cambia un efecto.
 *
 * @property int $id
 * @property int $documento_prueba_id
 * @property string $descripcion
 * @property string $cantidad
 * @property string $cantidad_recibida
 */
class LineaDocumentoPrueba extends Model
{
    use ConAuditoria, ParteDeDocumento;

    protected $table = 'lineas_documento_prueba';

    protected $fillable = ['documento_prueba_id', 'descripcion', 'cantidad', 'cantidad_recibida'];

    /**
     * @return BelongsTo<DocumentoPrueba, $this>
     */
    public function documento(): BelongsTo
    {
        return $this->belongsTo(DocumentoPrueba::class, 'documento_prueba_id');
    }

    public function documentoConEstados(): Model&DocumentoConEstados
    {
        return DocumentoPrueba::query()->findOrFail($this->documento_prueba_id);
    }

    /**
     * @return list<string>
     */
    public function camposDerivados(): array
    {
        return ['cantidad_recibida'];
    }
}
