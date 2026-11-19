<?php

declare(strict_types=1);

namespace Tests\Modules\Nucleo\Fixtures;

use App\Modules\Nucleo\Estados\DocumentoConEstados;
use App\Modules\Nucleo\Estados\TieneEstados;
use App\Modules\Nucleo\Models\Concerns\ConAuditoria;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Documento inventado cuyas versiones comparten número (planes 2.9 y 2.22, D2), para probar el núcleo sin depender
 * del presupuesto ni de la cotización. Su tabla se crea dentro de la transacción de la prueba.
 *
 * @property int $id
 * @property int $estado_id
 * @property ?string $numero
 * @property ?int $numero_version
 * @property string $nombre
 * @property int $version
 */
class DocumentoVersionadoPrueba extends Model implements DocumentoConEstados
{
    use ConAuditoria, TieneEstados;

    protected $table = 'documentos_versionados_prueba';

    protected $fillable = ['numero', 'numero_version', 'nombre'];

    public static function crearTabla(): void
    {
        Relation::morphMap(['documento_versionado_prueba' => self::class]);

        Schema::create('documentos_versionados_prueba', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('estado_id')->index()->constrained('estados');
            $table->string('numero', 30);
            $table->smallInteger('numero_version');
            $table->string('nombre', 60);
            $table->integer('version')->default(1);
            $table->auditoria();
            $table->unique(['numero', 'numero_version']);
        });
    }

    /**
     * @return list<Model>
     */
    public function ambitoDePermisos(): array
    {
        return [];
    }
}
