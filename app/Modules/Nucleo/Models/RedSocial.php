<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Models;

use App\Modules\Nucleo\database\factories\RedSocialFactory;
use App\Modules\Nucleo\Enums\IconoRed;
use App\Modules\Nucleo\Models\Concerns\ConAuditoria;
use App\Modules\Nucleo\Models\Concerns\ElementoCatalogo;
use App\Modules\Nucleo\Models\Concerns\EsCatalogo;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Una red social del catálogo (plan 1.4o, D1): LinkedIn, Facebook, sitio web… Con su ícono y los dominios de los que
 * puede ser un enlace suyo; sin dominios, cualquiera (el sitio web).
 *
 * @property int $id
 * @property string $codigo
 * @property string $nombre
 * @property ?string $dominios
 * @property IconoRed $icono
 * @property bool $es_activo
 * @property int $version
 */
#[UseFactory(RedSocialFactory::class)]
class RedSocial extends Model implements ElementoCatalogo
{
    /** @use HasFactory<RedSocialFactory> */
    use ConAuditoria, EsCatalogo, HasFactory;

    protected $table = 'redes_sociales';

    protected $fillable = ['codigo', 'nombre', 'dominios', 'icono', 'es_activo'];

    protected function casts(): array
    {
        return [
            'icono' => IconoRed::class,
            'es_activo' => 'boolean',
        ];
    }
}
