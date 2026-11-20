<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * MVP rápido (ficha t5-finanzas, flujos 4 a 7): comprobantes de venta y de compra (con su validación SUNAT simulada),
 * cuentas por cobrar y por pagar, programación de pagos de Tesorería, movimientos de caja y banco (conciliables), y
 * fondos por proyecto con su rendición. Estados como texto (atajo).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comprobantes', function (Blueprint $table): void {
            $table->id();
            $table->string('clase', 10)->index(); // venta | compra
            $table->string('tipo', 20)->default('factura'); // factura | boleta | recibo_honorarios
            $table->string('serie_numero', 30);
            $table->string('ruc', 11)->nullable();
            $table->string('razon_social', 200);
            $table->date('emision');
            $table->date('vencimiento');
            $table->decimal('subtotal', 14, 2);
            $table->decimal('igv', 14, 2);
            $table->decimal('total', 14, 2);
            $table->decimal('detraccion', 14, 2)->default(0);
            $table->foreignId('proyecto_id')->nullable()->index()->constrained('proyectos');
            $table->foreignId('contrato_id')->nullable()->constrained('contratos');
            $table->foreignId('valorizacion_id')->nullable()->constrained('valorizaciones');
            $table->foreignId('orden_id')->nullable()->constrained('ordenes');
            $table->string('origen', 20)->default('manual'); // xml | manual | emitido
            $table->string('sunat', 20)->default('valido'); // valido | observado
            $table->string('cruce', 20)->nullable(); // ok | diferencia (compras: orden, recepción y factura)
            $table->string('estado', 20)->index(); // por_cobrar | cobrado | por_programar | programado | pagado | anulado
            $table->date('pago_programado')->nullable();
            $table->decimal('saldo', 14, 2);
            $table->timestamps();
            $table->unique(['clase', 'ruc', 'serie_numero']);
        });

        Schema::create('movimientos_caja', function (Blueprint $table): void {
            $table->id();
            $table->date('fecha')->index();
            $table->string('tipo', 10); // ingreso | egreso
            $table->string('cuenta', 60);
            $table->decimal('monto', 14, 2);
            $table->string('concepto', 250);
            $table->foreignId('comprobante_id')->nullable()->constrained('comprobantes');
            $table->unsignedBigInteger('fondo_id')->nullable();
            $table->foreignId('proyecto_id')->nullable()->constrained('proyectos');
            $table->boolean('conciliado')->default(false);
            $table->timestamps();
        });

        Schema::create('fondos', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo', 30)->unique();
            $table->string('tipo', 20); // caja_chica | rendicion
            $table->foreignId('proyecto_id')->constrained('proyectos');
            $table->string('responsable', 150);
            $table->decimal('monto', 14, 2);
            $table->date('entregado');
            $table->string('estado', 20)->default('abierto'); // abierto | rendido
            $table->timestamps();
        });

        Schema::create('fondo_gastos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('fondo_id')->index()->constrained('fondos')->cascadeOnDelete();
            $table->date('fecha');
            $table->string('descripcion', 200);
            $table->string('comprobante', 60)->nullable();
            $table->decimal('monto', 14, 2);
            $table->foreignId('partida_control_id')->constrained('partidas_control');
            $table->boolean('leido_por_ia')->default(false);
            $table->string('estado', 20)->default('por_aprobar'); // por_aprobar | aprobado | observado
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['fondo_gastos', 'fondos', 'movimientos_caja', 'comprobantes'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
