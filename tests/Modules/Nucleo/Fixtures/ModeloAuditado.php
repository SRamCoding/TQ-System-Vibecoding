<?php

declare(strict_types=1);

namespace Tests\Modules\Nucleo\Fixtures;

use App\Modules\Nucleo\Models\Concerns\ConAuditoria;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla de negocio inventada para probar los mecanismos de auditoría sin depender de un módulo.
 * Se crea dentro de la transacción de la prueba (en PostgreSQL el DDL también se revierte).
 */
class ModeloAuditado extends Model
{
    use ConAuditoria;

    protected $table = 'pruebas_auditadas';

    protected $fillable = ['nombre', 'monto', 'secreto', 'nota_interna'];

    protected $hidden = ['secreto'];

    public static function crearTabla(): void
    {
        // El núcleo exige alias para todo modelo que se guarda (morph map, plan 1.4).
        Relation::morphMap(['prueba_auditada' => self::class, 'prueba_sensible' => ModeloSensible::class]);

        Schema::create('pruebas_auditadas', function (Blueprint $table): void {
            $table->id();
            $table->string('nombre', 60);
            $table->decimal('monto', 14, 2)->default(0);
            $table->string('secreto', 60)->nullable();
            $table->string('nota_interna', 60)->nullable();
            $table->auditoria();
        });
    }
}
