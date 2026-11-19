<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Models;

use App\Modules\Nucleo\Enums\CondicionRegla;
use App\Modules\Nucleo\Models\Concerns\ConAuditoria;
use App\Modules\Nucleo\Models\Concerns\RegistraCambios;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * M2 · Una regla de aprobación (plan 1.7): para un tipo de documento y el paso que aprueba, en un rango de montos en
 * soles, qué jefaturas firman y en qué orden. Se cambia por pantalla (Configuración → Aprobaciones). Las que calzan se
 * acumulan (D2).
 *
 * @property int $id
 * @property string $nombre
 * @property string $documento_type
 * @property string $transicion_codigo
 * @property ?string $monto_mas_de
 * @property CondicionRegla $condicion
 * @property ?string $monto_hasta
 * @property ?string $tope_autoaprobacion
 * @property bool $es_activa
 * @property int $version
 */
class ReglaAprobacion extends Model
{
    use ConAuditoria, RegistraCambios;

    protected $table = 'reglas_aprobacion';

    protected $fillable = ['nombre', 'documento_type', 'transicion_codigo', 'monto_mas_de', 'monto_hasta', 'tope_autoaprobacion', 'es_activa', 'condicion'];

    protected function casts(): array
    {
        return ['es_activa' => 'boolean', 'version' => 'integer', 'monto_mas_de' => 'decimal:2', 'monto_hasta' => 'decimal:2', 'tope_autoaprobacion' => 'decimal:2', 'condicion' => CondicionRegla::class];
    }

    /**
     * @return list<string>
     */
    public function camposSinBitacora(): array
    {
        return ['version'];
    }

    /**
     * @return HasMany<PasoReglaAprobacion, $this>
     */
    public function pasos(): HasMany
    {
        return $this->hasMany(PasoReglaAprobacion::class)->orderBy('orden');
    }
}
