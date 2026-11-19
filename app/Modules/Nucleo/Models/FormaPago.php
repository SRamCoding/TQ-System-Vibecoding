<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Models;

use App\Modules\Nucleo\database\factories\FormaPagoFactory;
use App\Modules\Nucleo\Models\Concerns\ConAuditoria;
use App\Modules\Nucleo\Models\Concerns\ElementoCatalogo;
use App\Modules\Nucleo\Models\Concerns\EsCatalogo;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * M5 · Forma de pago. Sin días es contado.
 *
 * @property int $id
 * @property string $codigo
 * @property string $nombre
 * @property ?int $dias
 * @property bool $es_activa
 * @property int $version
 * @property int $created_by
 * @property ?int $updated_by
 */
#[UseFactory(FormaPagoFactory::class)]
class FormaPago extends Model implements ElementoCatalogo
{
    /** @use HasFactory<FormaPagoFactory> */
    use ConAuditoria, EsCatalogo, HasFactory;

    protected $table = 'formas_pago';

    protected $fillable = ['codigo', 'nombre', 'dias', 'es_activa'];

    public static function columnaActivo(): string
    {
        return 'es_activa';
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'dias' => 'integer',
            'es_activa' => 'boolean',
        ];
    }
}
