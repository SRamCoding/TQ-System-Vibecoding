<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * MV-027: la «cotización del proveedor» pasa a ser «oferta de proveedor», con su numeración (OFP-001).
 * MV-028: las herramientas propias y dónde están (en el almacén o en una obra, quién la tiene y cuándo vuelve).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cuadro_cotizaciones', function (Blueprint $table): void {
            $table->string('codigo', 20)->nullable()->unique();
        });

        foreach (DB::table('cuadro_cotizaciones')->orderBy('id')->pluck('id') as $i => $id) {
            DB::table('cuadro_cotizaciones')->where('id', $id)->update(['codigo' => sprintf('OFP-%03d', $i + 1)]);
        }

        Schema::create('herramientas', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo', 20)->unique();
            $table->string('nombre', 150);
            $table->string('tipo', 30); // electrica | altura | vehiculo | medicion | manual
            $table->string('marca_modelo', 120)->nullable();
            $table->string('serie', 60)->nullable();
            $table->string('estado', 20)->default('en_almacen'); // en_almacen | en_obra | en_mantenimiento | baja
            $table->foreignId('proyecto_id')->nullable()->constrained('proyectos');
            $table->foreignId('colaborador_id')->nullable()->constrained('colaboradores');
            $table->date('salio')->nullable();
            $table->date('debe_volver')->nullable();
            $table->timestamps();
        });

        Schema::create('herramienta_movimientos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('herramienta_id')->constrained('herramientas')->cascadeOnDelete();
            $table->string('tipo', 20); // salida | devolucion | mantenimiento
            $table->date('fecha');
            $table->foreignId('proyecto_id')->nullable()->constrained('proyectos');
            $table->foreignId('colaborador_id')->nullable()->constrained('colaboradores');
            $table->date('debe_volver')->nullable();
            $table->string('nota', 250)->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('herramienta_movimientos');
        Schema::dropIfExists('herramientas');
        Schema::table('cuadro_cotizaciones', fn (Blueprint $table) => $table->dropColumn('codigo'));
    }
};
