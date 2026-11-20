<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * MVP rápido (ficha t4-personal-tareo, flujo 3): categorías con su costo hora estándar (el mismo del APU),
 * colaboradores con su habilitación de campo (SCTR), asignaciones a proyectos con porcentaje y vigencia, y el tareo
 * semanal por persona, día y partida. Al cerrar la semana, las horas pasan a comprometido y ejecutado (origen: la línea).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categorias_personal', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo', 20)->unique();
            $table->string('nombre', 80);
            $table->decimal('costo_hora', 10, 2);
        });

        Schema::create('colaboradores', function (Blueprint $table): void {
            $table->id();
            $table->string('nombres', 120);
            $table->string('dni', 8)->unique();
            $table->foreignId('categoria_id')->constrained('categorias_personal');
            $table->string('vinculo', 20); // planilla | eventual
            $table->date('habilitado_hasta')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users');
            $table->boolean('es_activo')->default(true);
            $table->timestamps();
        });

        Schema::create('asignaciones', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('colaborador_id')->index()->constrained('colaboradores');
            $table->foreignId('proyecto_id')->index()->constrained('proyectos');
            $table->smallInteger('porcentaje');
            $table->date('desde');
            $table->date('hasta')->nullable();
            $table->timestamps();
        });

        Schema::create('tareos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('proyecto_id')->index()->constrained('proyectos');
            $table->date('semana');
            $table->string('estado', 20)->default('abierto');
            $table->foreignId('cerrado_por')->nullable()->constrained('users');
            $table->timestampTz('cerrado_at')->nullable();
            $table->timestamps();
            $table->unique(['proyecto_id', 'semana']);
        });

        Schema::create('tareo_lineas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tareo_id')->index()->constrained('tareos')->cascadeOnDelete();
            $table->foreignId('colaborador_id')->constrained('colaboradores');
            $table->foreignId('partida_control_id')->constrained('partidas_control');
            foreach (['lu', 'ma', 'mi', 'ju', 'vi', 'sa'] as $d) {
                $table->decimal($d, 4, 1)->default(0);
            }
            $table->decimal('costo_hora', 10, 2);
            $table->timestamps();
        });

        foreach ([['TEC', 'Técnico', '18.00'], ['AYU', 'Ayudante', '12.00'], ['ESP', 'Especialista', '35.00'], ['JEF', 'Jefe de proyecto', '55.00'], ['DES', 'Desarrollador', '45.00']] as [$c, $n, $h]) {
            DB::table('categorias_personal')->insert(['codigo' => $c, 'nombre' => $n, 'costo_hora' => $h]);
        }
    }

    public function down(): void
    {
        foreach (['tareo_lineas', 'tareos', 'asignaciones', 'colaboradores', 'categorias_personal'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
