<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Models;

use App\Modules\Nucleo\database\factories\CategoriaFraseFactory;
use App\Modules\Nucleo\Models\Concerns\ConAuditoria;
use App\Modules\Nucleo\Models\Concerns\ElementoCatalogo;
use App\Modules\Nucleo\Models\Concerns\EsCatalogo;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Categoría de las frases del saludo: propias, autores, refranes, versículos (plan 1.4b, D1a). Desactivarla la
 * apaga para toda la empresa; cada persona puede apagarla además para sí.
 *
 * @property int $id
 * @property string $codigo
 * @property string $nombre
 * @property bool $es_activa
 * @property int $version
 * @property int $created_by
 * @property ?int $updated_by
 */
#[UseFactory(CategoriaFraseFactory::class)]
class CategoriaFrase extends Model implements ElementoCatalogo
{
    /** @use HasFactory<CategoriaFraseFactory> */
    use ConAuditoria, EsCatalogo, HasFactory;

    protected $table = 'categorias_frase';

    protected $fillable = ['codigo', 'nombre', 'es_activa'];

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
            'es_activa' => 'boolean',
        ];
    }
}
