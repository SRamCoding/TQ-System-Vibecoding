<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Models;

use App\Modules\Nucleo\database\factories\UnidadOrganizativaFactory;
use App\Modules\Nucleo\Enums\TipoUnidadOrganizativa;
use App\Modules\Nucleo\Historial\DescribeBitacora;
use App\Modules\Nucleo\Historial\DescribibleEnBitacora;
use App\Modules\Nucleo\Models\Concerns\ConAuditoria;
use App\Modules\Nucleo\Models\Concerns\RegistraCambios;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * M10 · Una unidad del organigrama (plan 1.4, D5). Su jefe es un puesto de la misma unidad, no una persona: si
 * cambia el ocupante, la jefatura lo sigue sola. La escribe solo OrganigramaService, que impide los ciclos.
 *
 * @property int $id
 * @property ?int $parent_id
 * @property string $codigo
 * @property string $nombre
 * @property TipoUnidadOrganizativa $tipo
 * @property ?int $jefe_puesto_id
 * @property int $orden
 * @property bool $es_activa
 * @property int $version
 * @property int $created_by
 * @property ?int $updated_by
 * @property-read ?UnidadOrganizativa $padre
 * @property-read ?Puesto $jefePuesto
 */
#[UseFactory(UnidadOrganizativaFactory::class)]
class UnidadOrganizativa extends Model implements DescribibleEnBitacora
{
    /** @use HasFactory<UnidadOrganizativaFactory> */
    use ConAuditoria, DescribeBitacora, HasFactory, RegistraCambios;

    protected $table = 'unidades_organizativas';

    protected $fillable = ['parent_id', 'codigo', 'nombre', 'tipo', 'jefe_puesto_id', 'orden', 'es_activa'];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = ['orden' => 0, 'es_activa' => true];

    /**
     * Ordenadas como árbol: cada unidad después de la que depende, hermanas por `orden`. Agrega `nivel` (0 en la
     * raíz) para la sangría de la pantalla.
     *
     * @param  Builder<static>  $consulta
     */
    public function scopeEnArbol(Builder $consulta): void
    {
        $consulta->join(DB::raw(<<<'SQL'
            (WITH RECURSIVE arbol(id, ruta, nivel) AS (
                SELECT id, ARRAY[orden::bigint, id], 0 FROM unidades_organizativas WHERE parent_id IS NULL
                UNION ALL
                SELECT u.id, a.ruta || ARRAY[u.orden::bigint, u.id], a.nivel + 1
                FROM unidades_organizativas u JOIN arbol a ON u.parent_id = a.id
            ) SELECT id, ruta, nivel FROM arbol) AS arbol
            SQL), 'arbol.id', '=', 'unidades_organizativas.id')
            ->select('unidades_organizativas.*', 'arbol.nivel')
            ->orderBy('arbol.ruta');
    }

    /**
     * @return list<string>
     */
    public function camposSinBitacora(): array
    {
        return ['version'];
    }

    public function etiquetasBitacora(): array
    {
        return [
            'parent_id' => 'Depende de', 'codigo' => 'Código', 'nombre' => 'Nombre', 'tipo' => 'Tipo',
            'jefe_puesto_id' => 'Puesto de jefatura', 'orden' => 'Orden', 'es_activa' => 'Activa',
        ];
    }

    public function valorLegibleBitacora(string $campo, mixed $valor): string
    {
        return match ($campo) {
            'parent_id' => self::query()->whereKey($valor)->value('nombre') ?? self::valorComun($valor),
            'jefe_puesto_id' => Puesto::query()->whereKey($valor)->value('nombre') ?? 'Sin definir',
            'tipo' => TipoUnidadOrganizativa::tryFrom((string) $valor)?->etiqueta() ?? self::valorComun($valor),
            default => self::valorComun($valor),
        };
    }

    /**
     * @return BelongsTo<UnidadOrganizativa, $this>
     */
    public function padre(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<UnidadOrganizativa, $this>
     */
    public function hijas(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * @return HasMany<Puesto, $this>
     */
    public function puestos(): HasMany
    {
        return $this->hasMany(Puesto::class);
    }

    /**
     * @return BelongsTo<Puesto, $this>
     */
    public function jefePuesto(): BelongsTo
    {
        return $this->belongsTo(Puesto::class, 'jefe_puesto_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'parent_id' => 'integer',
            'tipo' => TipoUnidadOrganizativa::class,
            'jefe_puesto_id' => 'integer',
            'orden' => 'integer',
            'es_activa' => 'boolean',
            'version' => 'integer',
        ];
    }
}
