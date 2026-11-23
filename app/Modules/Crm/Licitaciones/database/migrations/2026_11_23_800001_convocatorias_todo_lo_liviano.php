<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * MVP rápido (observación 7.4, ficha t1-licitaciones): de lo que publica el OECE se guarda todo, pero solo lo liviano;
 * la bandeja muestra lo que pasa el filtro (`en_bandeja`). Se agrega (sin reconstruir):
 * - convocatorias_seace: si entra a la bandeja y por qué (`coincide` pasa a opcional), si es prioritaria, la fecha del
 *   release más nuevo (hasta dónde se leyó), el fin de consultas, el estado en el OECE, el ganador y el monto
 *   adjudicado, y lo que cambió en la última versión.
 * - seace_lecturas: cada lectura con su resultado (para «Leído hace 3 min» y saber si el OECE no respondió).
 * - convocatoria_avisos: lo que la reacción «Avisar en Mien» deja para Mien (nueva convocatoria, cambió su cronograma).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('convocatorias_seace', function (Blueprint $table): void {
            $table->string('coincide', 200)->nullable()->change();
            $table->boolean('en_bandeja')->default(false)->index();
            // Título, descripción y descripción de los ítems (hasta 2000 letras): con qué se filtra y se busca.
            $table->text('texto')->nullable();
            $table->boolean('prioritaria')->default(false);
            $table->string('motivo_prioridad', 120)->nullable();
            $table->timestampTz('release_fecha')->nullable()->index();
            $table->date('consultas_hasta')->nullable();
            $table->string('estado_ocds', 30)->nullable();
            $table->string('ganador', 250)->nullable();
            $table->string('ganador_ruc', 11)->nullable();
            $table->decimal('monto_adjudicado', 16, 2)->nullable();
            $table->json('cambios')->nullable();
            $table->timestampTz('cambiada_at')->nullable();
        });

        // Lo que ya estaba entró por coincidir: queda en la bandeja.
        DB::table('convocatorias_seace')->update(['en_bandeja' => true, 'release_fecha' => DB::raw("(publicada::timestamp + interval '9 hours')")]);

        Schema::create('seace_lecturas', function (Blueprint $table): void {
            $table->id();
            $table->timestampTz('inicio');
            $table->timestampTz('fin')->nullable();
            $table->boolean('es_ok')->default(false);
            $table->string('error', 300)->nullable();
            $table->unsignedInteger('leidas')->default(0);
            $table->unsignedInteger('nuevas')->default(0);
            $table->unsignedInteger('actualizadas')->default(0);
            $table->unsignedInteger('a_bandeja')->default(0);
            $table->string('origen', 20)->default('programada'); // programada | al_entrar | a_mano
        });

        Schema::create('convocatoria_avisos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('convocatoria_id')->constrained('convocatorias_seace')->cascadeOnDelete();
            $table->string('tipo', 20); // nueva | cambio
            $table->string('texto', 400);
            $table->timestampTz('created_at')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('convocatoria_avisos');
        Schema::dropIfExists('seace_lecturas');
        Schema::table('convocatorias_seace', fn (Blueprint $t) => $t->dropColumn(['en_bandeja', 'prioritaria', 'motivo_prioridad', 'release_fecha', 'consultas_hasta', 'estado_ocds', 'ganador', 'ganador_ruc', 'monto_adjudicado', 'cambios', 'cambiada_at']));
    }
};
