<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * MVP rápido (ficha t1-campanias): campañas y leads (CRM-07). El lead convertido apunta a su cuenta (en vez de la ficha,
 * como dice el DBML) y a su oportunidad; tipo de campaña, estado y motivo de descarte son texto (atajo).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campanias', function (Blueprint $table): void {
            $table->id();
            $table->string('nombre', 150);
            $table->string('tipo', 40);
            $table->date('desde');
            $table->date('hasta')->nullable();
            $table->decimal('presupuesto', 14, 2)->nullable();
            $table->text('objetivo')->nullable();
            $table->boolean('es_activa')->default(true);
            $table->auditoria();
        });

        Schema::create('leads', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('campania_id')->nullable()->index()->constrained('campanias');
            $table->foreignId('origen_comercial_id')->constrained('origenes_comerciales');
            $table->string('nombre_contacto', 150);
            $table->string('cargo', 120)->nullable();
            $table->string('organizacion', 200)->nullable();
            $table->string('ruc', 11)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('telefono', 30)->nullable();
            $table->text('mensaje')->nullable();
            $table->foreignId('cuenta_id')->nullable()->constrained('cuentas');
            $table->foreignId('oportunidad_id')->nullable()->constrained('oportunidades');
            $table->string('estado', 20)->index();
            $table->string('motivo_descarte', 120)->nullable();
            $table->foreignId('asignado_user_id')->nullable()->constrained('users');
            $table->timestampTz('atendido_at')->nullable();
            $table->auditoria();
        });

        DB::statement("ALTER TABLE leads ADD CONSTRAINT leads_estado_check CHECK (estado IN ('nuevo', 'contactado', 'convertido', 'descartado'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
        Schema::dropIfExists('campanias');
    }
};
