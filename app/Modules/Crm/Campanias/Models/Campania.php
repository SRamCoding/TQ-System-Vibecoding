<?php

declare(strict_types=1);

namespace App\Modules\Crm\Campanias\Models;

use App\Modules\Nucleo\Models\Concerns\ConAuditoria;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** MVP rápido (t1-campanias): una acción de prospección con su periodo y presupuesto. */
class Campania extends Model
{
    use ConAuditoria;

    public const array TIPOS = ['feria' => 'Feria o evento', 'web' => 'Sitio web y formularios', 'correo' => 'Correo masivo', 'referidos' => 'Programa de referidos', 'redes' => 'Redes sociales', 'clientes' => 'Campaña a clientes', 'acercamiento' => 'Acercamiento'];

    protected $table = 'campanias';

    protected $fillable = ['nombre', 'tipo', 'canal_lead_id', 'desde', 'hasta', 'presupuesto', 'objetivo', 'es_activa', 'token', 'responsable_user_id', 'grupo', 'seguimiento'];

    protected function casts(): array
    {
        return ['desde' => 'date', 'hasta' => 'date', 'presupuesto' => 'decimal:2', 'es_activa' => 'boolean', 'seguimiento' => 'array'];
    }

    /** @return HasMany<Lead, $this> */
    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }
}
