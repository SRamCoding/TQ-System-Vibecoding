<?php

declare(strict_types=1);

namespace Tests\Modules\Erp\Presupuestos\Fixtures;

use App\Modules\Nucleo\Estados\DocumentoConEstados;
use App\Modules\Nucleo\Estados\TieneEstados;
use App\Modules\Nucleo\Models\Concerns\ConAuditoria;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Un documento con costo inventado (plan 2.2, D8): hoy no hay ninguno real. Se usa como lo hará una orden de compra:
 * cada línea con su destino (selector de imputación), enviar con la revisión del exceso y aprobar con la aprobación de
 * la 1.7, que sube el comprometido. Sus tablas se crean dentro de la transacción de la prueba.
 *
 * @property int $id
 * @property int $estado_id
 * @property ?int $unidad_id
 * @property int $version
 */
class OrdenDePrueba extends Model implements DocumentoConEstados
{
    use ConAuditoria, TieneEstados;

    protected $table = 'ordenes_prueba';

    protected $fillable = ['nombre', 'unidad_id'];

    public static function crearMorphMap(): void
    {
        Relation::morphMap(['orden_prueba' => self::class, 'linea_orden_prueba' => LineaOrdenDePrueba::class]);
    }

    public static function crearTablas(): void
    {
        self::crearMorphMap();

        Schema::create('ordenes_prueba', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('estado_id')->index()->constrained('estados');
            $table->string('nombre', 60);
            $table->foreignId('unidad_id')->nullable()->index()->constrained('unidades_organizativas');
            $table->integer('version')->default(1);
            $table->auditoria();
        });

        Schema::create('lineas_orden_prueba', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('orden_prueba_id')->index()->constrained('ordenes_prueba');
            $table->string('descripcion', 60);
            $table->decimal('monto', 14, 2);
            $table->string('moneda', 3);
            $table->date('fecha');
            $table->auditoria();
        });
    }

    /** @return HasMany<LineaOrdenDePrueba, $this> */
    public function lineas(): HasMany
    {
        return $this->hasMany(LineaOrdenDePrueba::class, 'orden_prueba_id')->orderBy('id');
    }
}
