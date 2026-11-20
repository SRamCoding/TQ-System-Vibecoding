<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * MVP rápido (ficha t6-postventa, flujo 8, MV-015): los números de serie del almacén (la vida completa de un equipo:
 * compra, ingreso, despacho, instalación y garantía), los tickets de postventa con su nivel de servicio, sus visitas
 * técnicas y las suscripciones que Techquk vende.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('series', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('producto_id')->constrained('productos');
            $table->string('serie', 60)->unique();
            $table->foreignId('orden_id')->nullable()->constrained('ordenes');
            $table->date('ingreso')->nullable();
            $table->string('ubicacion', 60)->nullable();
            $table->foreignId('proyecto_id')->nullable()->constrained('proyectos');
            $table->date('despacho')->nullable();
            $table->string('instalada_en', 120)->nullable();
            $table->date('garantia_hasta')->nullable();
            $table->date('garantia_fabricante_hasta')->nullable();
            $table->string('estado', 20)->default('en_almacen'); // en_almacen | despachada | instalada | en_reparacion | baja
            $table->timestamps();
        });

        Schema::create('tickets_postventa', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo', 30)->unique();
            $table->foreignId('cuenta_id')->constrained('cuentas');
            $table->foreignId('serie_id')->nullable()->constrained('series');
            $table->string('equipo', 200);
            $table->foreignId('proyecto_id')->nullable()->constrained('proyectos');
            $table->text('reclamo');
            $table->string('prioridad', 10); // alta | media | baja
            $table->timestampTz('abierto_at');
            $table->timestampTz('vence_at');
            $table->string('garantia', 20); // vigente | fabricante | vencida
            $table->string('estado', 20)->default('abierto'); // abierto | en_atencion | con_fabricante | por_cotizar | resuelto | cerrado
            $table->foreignId('responsable_user_id')->nullable()->constrained('users');
            $table->text('solucion')->nullable();
            $table->foreignId('oportunidad_id')->nullable()->constrained('oportunidades');
            $table->timestampTz('resuelto_at')->nullable();
            $table->timestamps();
        });

        Schema::create('visitas_tecnicas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ticket_id')->index()->constrained('tickets_postventa')->cascadeOnDelete();
            $table->date('fecha');
            $table->string('tecnico', 150);
            $table->decimal('horas', 5, 1);
            $table->decimal('materiales', 14, 2)->default(0);
            $table->string('nota', 300)->nullable();
            $table->timestamps();
        });

        Schema::create('suscripciones', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cuenta_id')->constrained('cuentas');
            $table->string('servicio', 150);
            $table->decimal('cobro_mensual', 14, 2);
            $table->decimal('costo_mensual', 14, 2);
            $table->decimal('disponibilidad_meta', 5, 2);
            $table->decimal('disponibilidad_real', 5, 2)->nullable();
            $table->date('desde');
            $table->date('renovacion');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['suscripciones', 'visitas_tecnicas', 'tickets_postventa', 'series'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
