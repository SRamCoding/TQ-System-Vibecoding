<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Models;

use App\Modules\Nucleo\database\factories\FraseSaludoFactory;
use App\Modules\Nucleo\Enums\FueraDeHorario;
use App\Modules\Nucleo\Enums\IdiomaSaludo;
use App\Modules\Nucleo\Enums\MomentoDia;
use App\Modules\Nucleo\Enums\Ocasion;
use App\Modules\Nucleo\Enums\SesionSaludo;
use App\Modules\Nucleo\Enums\TipoFraseSaludo;
use App\Modules\Nucleo\Models\Concerns\ConAuditoria;
use App\Modules\Nucleo\Models\Concerns\ElementoCatalogo;
use App\Modules\Nucleo\Models\Concerns\EsCatalogo;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un saludo («Buenos días, {nombre}») o una frase de debajo, con sus condiciones opcionales (plan 1.4b, D1). Una
 * condición nula vale siempre. Las citas llevan su fuente; los versículos, referencia y versión.
 *
 * @property int $id
 * @property string $codigo
 * @property TipoFraseSaludo $tipo
 * @property ?int $categoria_frase_id
 * @property IdiomaSaludo $idioma
 * @property string $texto
 * @property ?string $fuente
 * @property ?MomentoDia $momento
 * @property ?FueraDeHorario $fuera_de_horario
 * @property ?int $dia_semana
 * @property ?string $area
 * @property ?Ocasion $ocasion
 * @property ?SesionSaludo $sesion
 * @property bool $es_activa
 * @property int $version
 * @property int $created_by
 * @property ?int $updated_by
 * @property-read ?CategoriaFrase $categoria
 */
#[UseFactory(FraseSaludoFactory::class)]
class FraseSaludo extends Model implements ElementoCatalogo
{
    /** @use HasFactory<FraseSaludoFactory> */
    use ConAuditoria, EsCatalogo, HasFactory;

    protected $table = 'frases_saludo';

    protected $fillable = ['codigo', 'tipo', 'categoria_frase_id', 'idioma', 'texto', 'fuente', 'momento', 'fuera_de_horario', 'dia_semana', 'area', 'ocasion', 'sesion', 'es_activa'];

    public static function columnaActivo(): string
    {
        return 'es_activa';
    }

    public function etiqueta(): string
    {
        return $this->fuente === null ? $this->texto : "{$this->texto} — {$this->fuente}";
    }

    /**
     * @return BelongsTo<CategoriaFrase, $this>
     */
    public function categoria(): BelongsTo
    {
        return $this->belongsTo(CategoriaFrase::class, 'categoria_frase_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tipo' => TipoFraseSaludo::class,
            'idioma' => IdiomaSaludo::class,
            'momento' => MomentoDia::class,
            'fuera_de_horario' => FueraDeHorario::class,
            'dia_semana' => 'integer',
            'ocasion' => Ocasion::class,
            'sesion' => SesionSaludo::class,
            'es_activa' => 'boolean',
        ];
    }
}
