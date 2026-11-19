<?php

declare(strict_types=1);

namespace App\Modules\Crm\Licitaciones\Models;

use App\Modules\Crm\Cuentas\Models\Cuenta;
use App\Modules\Crm\Licitaciones\Enums\EstadoLicitacion;
use App\Modules\Crm\Licitaciones\Enums\TipoProcedimiento;
use App\Modules\Nucleo\Models\Concerns\ConAuditoria;
use App\Modules\Nucleo\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * MVP rápido (t1-licitaciones): una convocatoria y nuestra participación en ella.
 *
 * @property int $id
 * @property string $codigo
 * @property string $nomenclatura
 * @property int $cuenta_id
 * @property bool $es_publica
 * @property TipoProcedimiento $tipo_procedimiento
 * @property string $objeto
 * @property ?string $valor_referencial
 * @property EstadoLicitacion $estado
 * @property ?int $oportunidad_id
 * @property ?string $monto_ofertado
 * @property int $version
 */
class Licitacion extends Model
{
    use ConAuditoria;

    protected $table = 'licitaciones';

    protected $fillable = [
        'codigo', 'nomenclatura', 'cuenta_id', 'es_publica', 'tipo_procedimiento', 'objeto', 'linea_solucion_id', 'valor_referencial',
        'fecha_convocatoria', 'url_bases', 'estado', 'responsable_user_id', 'motivo_no_participa', 'monto_ofertado',
        'fecha_presentacion', 'fecha_resultado', 'oportunidad_id',
    ];

    protected function casts(): array
    {
        return [
            'es_publica' => 'boolean',
            'tipo_procedimiento' => TipoProcedimiento::class,
            'estado' => EstadoLicitacion::class,
            'valor_referencial' => 'decimal:2',
            'monto_ofertado' => 'decimal:2',
            'fecha_convocatoria' => 'date',
            'fecha_presentacion' => 'date',
            'fecha_resultado' => 'date',
            'version' => 'integer',
        ];
    }

    /** @return BelongsTo<Cuenta, $this> */
    public function cuenta(): BelongsTo
    {
        return $this->belongsTo(Cuenta::class);
    }

    /** @return BelongsTo<User, $this> */
    public function responsable(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsable_user_id');
    }

    /** @return HasMany<HitoLicitacion, $this> */
    public function hitos(): HasMany
    {
        return $this->hasMany(HitoLicitacion::class)->orderBy('orden');
    }

    /** @return HasMany<DocumentoLicitacion, $this> */
    public function documentos(): HasMany
    {
        return $this->hasMany(DocumentoLicitacion::class)->orderBy('orden');
    }

    /** @return HasMany<ConsultaLicitacion, $this> */
    public function consultas(): HasMany
    {
        return $this->hasMany(ConsultaLicitacion::class)->orderBy('fecha');
    }

    /** @return HasMany<ParticipanteLicitacion, $this> */
    public function participantes(): HasMany
    {
        return $this->hasMany(ParticipanteLicitacion::class)->orderByRaw('puesto IS NULL, puesto');
    }
}
