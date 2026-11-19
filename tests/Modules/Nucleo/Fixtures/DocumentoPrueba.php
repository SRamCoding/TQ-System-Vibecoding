<?php

declare(strict_types=1);

namespace Tests\Modules\Nucleo\Fixtures;

use App\Modules\Nucleo\Adjuntos\ConAdjuntos;
use App\Modules\Nucleo\Estados\DocumentoConEstados;
use App\Modules\Nucleo\Estados\TieneEstados;
use App\Modules\Nucleo\Models\Concerns\ConAuditoria;
use App\Modules\Nucleo\Models\UnidadOrganizativa;
use App\Modules\Nucleo\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Documento inventado para probar estados y transiciones sin depender de un módulo (plan 1.5). Tiene dos ámbitos de
 * permisos, como tendrá un documento de proyecto, líneas y un campo derivado (`total_recibido`). Sus tablas se crean dentro de la transacción de la
 * prueba (en PostgreSQL el DDL también se revierte).
 *
 * @property int $id
 * @property int $estado_id
 * @property ?string $numero
 * @property ?string $fecha
 * @property string $nombre
 * @property string $monto
 * @property string $moneda
 * @property ?int $unidad_id
 * @property ?int $otra_unidad_id
 * @property ?int $origen_id
 * @property string $total_recibido
 * @property int $version
 */
class DocumentoPrueba extends Model implements ConAdjuntos, DocumentoConEstados
{
    use ConAuditoria, TieneEstados;

    protected $table = 'documentos_prueba';

    protected $fillable = ['nombre', 'fecha', 'monto', 'moneda', 'unidad_id', 'otra_unidad_id', 'origen_id'];

    public static function crearMorphMap(): void
    {
        Relation::morphMap(['documento_prueba' => self::class, 'linea_documento_prueba' => LineaDocumentoPrueba::class]);
    }

    public static function crearTabla(): void
    {
        self::crearMorphMap();

        Schema::create('documentos_prueba', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('estado_id')->index()->constrained('estados');
            // Nulo solo cuando la prueba quita la numeración de la definición.
            $table->string('numero', 30)->nullable()->unique();
            $table->date('fecha')->nullable();
            $table->string('nombre', 60);
            $table->decimal('monto', 14, 2)->default(0);
            $table->string('moneda', 3)->default('PEN');
            $table->decimal('total_recibido', 14, 2)->default(0);
            $table->foreignId('unidad_id')->nullable()->index()->constrained('unidades_organizativas');
            $table->foreignId('otra_unidad_id')->nullable()->index()->constrained('unidades_organizativas');
            // El documento del que depende: lo que impide anular el origen (RevisionAntesDeAnular).
            $table->foreignId('origen_id')->nullable()->index()->constrained('documentos_prueba');
            $table->integer('version')->default(1);
            $table->auditoria();
        });

        // Lo que anotan los efectos de prueba: se revierte con la transacción, como lo haría un efecto real.
        Schema::create('anotaciones_prueba', function (Blueprint $table): void {
            $table->id();
            $table->string('texto', 120);
        });

        Schema::create('lineas_documento_prueba', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('documento_prueba_id')->index()->constrained('documentos_prueba');
            $table->string('descripcion', 60);
            $table->decimal('cantidad', 14, 2);
            $table->decimal('cantidad_recibida', 14, 2)->default(0);
            $table->auditoria();
        });
    }

    public function camposDerivados(): array
    {
        return ['total_recibido'];
    }

    /**
     * @return HasMany<LineaDocumentoPrueba, $this>
     */
    public function lineas(): HasMany
    {
        return $this->hasMany(LineaDocumentoPrueba::class);
    }

    /**
     * @return list<Model>
     */
    public function ambitoDePermisos(): array
    {
        return UnidadOrganizativa::query()
            ->whereIn('id', array_filter([$this->unidad_id, $this->otra_unidad_id]))
            ->orderBy('id')
            ->get()
            ->all();
    }

    /** Sus adjuntos los ve y los cambia cualquiera: las reglas que importan aquí son las de su estado (plan 1.8). */
    public function puedeVerAdjuntos(User $usuario): bool
    {
        return true;
    }

    public function puedeAdjuntar(User $usuario): bool
    {
        return true;
    }
}
