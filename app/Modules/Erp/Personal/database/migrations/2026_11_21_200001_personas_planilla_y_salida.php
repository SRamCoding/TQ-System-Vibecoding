<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * MVP rápido (ficha t7-personas, flujo 9, MV-015): contrato laboral (remuneración, régimen, ingreso y salida),
 * documentos de habilitación y certificaciones con vencimiento, vacantes y candidatos, la planilla del mes, y la
 * autorización con motivo para tarear a quien tiene el SCTR vencido.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('colaboradores', function (Blueprint $table): void {
            $table->decimal('remuneracion', 12, 2)->nullable();
            $table->string('regimen', 10)->default('onp'); // onp | afp
            $table->date('ingreso')->nullable();
            $table->date('salida')->nullable();
            $table->string('motivo_salida', 200)->nullable();
            $table->decimal('liquidacion', 12, 2)->nullable();
        });

        Schema::table('tareo_lineas', function (Blueprint $table): void {
            $table->string('autorizacion', 300)->nullable();
        });

        Schema::create('colaborador_documentos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('colaborador_id')->index()->constrained('colaboradores')->cascadeOnDelete();
            $table->string('tipo', 20); // sctr | emo | induccion | certificacion
            $table->string('nombre', 150);
            $table->string('emisor', 150)->nullable();
            $table->date('vence')->nullable();
            $table->timestamps();
        });

        Schema::create('vacantes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('categoria_id')->constrained('categorias_personal');
            $table->foreignId('proyecto_id')->nullable()->constrained('proyectos');
            $table->smallInteger('cantidad');
            $table->date('desde');
            $table->string('perfil', 300)->nullable();
            $table->string('estado', 20)->default('abierta'); // abierta | cubierta
            $table->timestamps();
        });

        Schema::create('candidatos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('vacante_id')->index()->constrained('vacantes')->cascadeOnDelete();
            $table->string('nombres', 150);
            $table->string('dni', 8);
            $table->string('nota', 300)->nullable();
            $table->string('estado', 20)->default('postulante'); // postulante | entrevistado | contratado | descartado
            $table->timestamps();
        });

        Schema::create('planillas', function (Blueprint $table): void {
            $table->id();
            $table->char('periodo', 7)->unique();
            $table->string('estado', 20)->default('borrador'); // borrador | cerrada
            $table->timestampTz('cerrada_at')->nullable();
            $table->timestamps();
        });

        Schema::create('planilla_lineas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('planilla_id')->index()->constrained('planillas')->cascadeOnDelete();
            $table->foreignId('colaborador_id')->constrained('colaboradores');
            $table->string('regimen', 10);
            $table->decimal('remuneracion', 12, 2);
            $table->decimal('aporte', 12, 2);
            $table->decimal('neto', 12, 2);
            $table->decimal('essalud', 12, 2);
            $table->decimal('costo_empresa', 12, 2);
            $table->decimal('horas_tareadas', 7, 1)->default(0);
        });
    }

    public function down(): void
    {
        foreach (['planilla_lineas', 'planillas', 'candidatos', 'vacantes', 'colaborador_documentos'] as $t) {
            Schema::dropIfExists($t);
        }
        Schema::table('tareo_lineas', fn (Blueprint $t) => $t->dropColumn('autorizacion'));
        Schema::table('colaboradores', fn (Blueprint $t) => $t->dropColumn(['remuneracion', 'regimen', 'ingreso', 'salida', 'motivo_salida', 'liquidacion']));
    }
};
