<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * MVP rápido (ficha t1-agenda): actividades (CRM-05). Cada gestión con el cliente es una actividad realizada; la
 * próxima acción es una actividad pendiente (`es_realizada` falso) con su fecha. Siempre de una cuenta; a veces de una
 * oportunidad. El DBML usa `actividable_type`; aquí, cuenta y oportunidad como columnas (atajo).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('actividades', function (Blueprint $table): void {
            $table->id();
            $table->string('tipo', 20);
            $table->foreignId('cuenta_id')->index()->constrained('cuentas');
            $table->foreignId('oportunidad_id')->nullable()->index()->constrained('oportunidades');
            $table->string('asunto', 200);
            $table->timestampTz('fecha')->index();
            $table->text('resultado')->nullable();
            $table->boolean('es_realizada')->default(false)->index();
            $table->foreignId('responsable_user_id')->index()->constrained('users');
            $table->integer('version')->default(1);
            $table->auditoria();
        });

        DB::statement("ALTER TABLE actividades ADD CONSTRAINT actividades_tipo_check CHECK (tipo IN ('visita', 'llamada', 'correo', 'reunion'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('actividades');
    }
};
