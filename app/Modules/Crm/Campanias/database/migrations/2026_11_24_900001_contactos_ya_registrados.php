<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Observación 9.9: un contacto que llega por una campaña o canal es nuevo o ya registrado (mismo teléfono, correo o RUC
 * que un cliente, un contacto o un lead anterior). El ya registrado no se duplica: si ya tenía un lead abierto o una
 * oportunidad abierta, queda solo anotado que también llegó por esta campaña (`lead_llegadas`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table): void {
            $table->boolean('ya_registrado')->default(false);
            $table->string('registrado_como', 12)->nullable();
        });

        Schema::create('lead_llegadas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('lead_id')->nullable()->constrained('leads')->cascadeOnDelete();
            $table->foreignId('oportunidad_id')->nullable()->constrained('oportunidades')->cascadeOnDelete();
            $table->foreignId('cuenta_id')->nullable()->constrained('cuentas');
            $table->foreignId('campania_id')->nullable()->index()->constrained('campanias')->cascadeOnDelete();
            $table->foreignId('canal_lead_id')->nullable()->index()->constrained('canales_lead');
            $table->string('nombre', 150);
            $table->string('organizacion', 200)->nullable();
            $table->string('registrado_como', 12);
            $table->timestampTz('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_llegadas');
        Schema::table('leads', fn (Blueprint $t) => $t->dropColumn(['ya_registrado', 'registrado_como']));
    }
};
