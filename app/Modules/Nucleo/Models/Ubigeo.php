<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Models;

use App\Modules\Nucleo\database\factories\UbigeoFactory;
use App\Modules\Nucleo\Enums\OrigenUbigeo;
use App\Modules\Nucleo\Models\Concerns\ConAuditoria;
use App\Modules\Nucleo\Models\Concerns\ElementoCatalogo;
use App\Modules\Nucleo\Models\Concerns\EsCatalogo;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * M5 · Distrito del Perú con su código INEI de seis dígitos (el que usa SUNAT).
 *
 * @property int $id
 * @property string $codigo
 * @property string $departamento
 * @property string $provincia
 * @property string $distrito
 * @property OrigenUbigeo $origen
 * @property bool $es_activo
 * @property int $version
 * @property int $created_by
 * @property ?int $updated_by
 */
#[UseFactory(UbigeoFactory::class)]
class Ubigeo extends Model implements ElementoCatalogo
{
    /** @use HasFactory<UbigeoFactory> */
    use ConAuditoria, EsCatalogo, HasFactory;

    protected $table = 'ubigeos';

    protected $fillable = ['codigo', 'departamento', 'provincia', 'distrito', 'origen', 'es_activo'];

    public function etiqueta(): string
    {
        return "{$this->distrito}, {$this->provincia} ({$this->departamento})";
    }

    /** En un selector (plan 2.0, D1): el distrito con su ruta, porque hay distritos con el mismo nombre. */
    public function ruta(): string
    {
        return "{$this->distrito} — {$this->provincia}, {$this->departamento}";
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'origen' => OrigenUbigeo::class,
            'es_activo' => 'boolean',
        ];
    }
}
