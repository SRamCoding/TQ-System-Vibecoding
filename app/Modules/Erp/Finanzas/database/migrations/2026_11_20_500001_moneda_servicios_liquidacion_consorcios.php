<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * MVP rápido (ficha t5-finanzas v3, MV-014): moneda extranjera y diferencia de cambio en los comprobantes; la
 * validación SUNAT con su detalle; servicios con vigencia; liquidación del contrato y consorcios.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comprobantes', function (Blueprint $table): void {
            $table->char('moneda', 3)->default('PEN');
            $table->decimal('tipo_cambio', 8, 4)->default(1);
            $table->decimal('tipo_cambio_pago', 8, 4)->nullable();
            $table->decimal('diferencia_cambio', 14, 2)->default(0);
            $table->string('sunat_detalle', 200)->nullable();
        });

        Schema::table('contratos', function (Blueprint $table): void {
            $table->string('consorcio', 150)->nullable();
            $table->decimal('participacion_pct', 5, 2)->default(100);
            $table->string('modo_consorcio', 20)->nullable(); // participacion | todo
            $table->decimal('saldo_liquidacion', 14, 2)->nullable();
            $table->date('liquidado_el')->nullable();
        });
        DB::statement('ALTER TABLE contratos DROP CONSTRAINT contratos_estado_check');
        DB::statement("ALTER TABLE contratos ADD CONSTRAINT contratos_estado_check CHECK (estado IN ('borrador', 'vigente', 'culminado', 'liquidado', 'resuelto', 'anulado'))");

        Schema::create('servicios_vigencia', function (Blueprint $table): void {
            $table->id();
            $table->string('tipo', 30); // sctr | poliza | alquiler | licencia | suscripcion
            $table->string('proveedor', 200);
            $table->string('descripcion', 200);
            $table->foreignId('proyecto_id')->nullable()->constrained('proyectos');
            $table->foreignId('centro_costo_id')->nullable()->constrained('centros_costo');
            $table->string('periodicidad', 20); // mensual | anual | unico
            $table->decimal('monto_periodo', 14, 2);
            $table->date('desde');
            $table->date('hasta');
            $table->date('ultima_orden')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('servicios_vigencia');
        Schema::table('contratos', fn (Blueprint $t) => $t->dropColumn(['consorcio', 'participacion_pct', 'modo_consorcio', 'saldo_liquidacion', 'liquidado_el']));
        Schema::table('comprobantes', fn (Blueprint $t) => $t->dropColumn(['moneda', 'tipo_cambio', 'tipo_cambio_pago', 'diferencia_cambio', 'sunat_detalle']));
    }
};
