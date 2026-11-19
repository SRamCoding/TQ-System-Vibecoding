<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Oportunidades (plan 2.21, CRM-02): el negocio en curso, documento con estados del núcleo (D1), y contra quién se
 * compite (D4). Lo que la base no puede revisar lo exige el servicio: la cuenta usable (D6), el decisor de la ficha de
 * la cuenta (D9), `ganador_conocido` obligatorio en «perdida» (D4) y `en_etapa_desde` igual a la última entrada al
 * estado actual (D2, con su prueba de coherencia).
 * Desviaciones del DBML (ajustado en el paso 5): sin `probabilidad` (la da la etapa, D2), sin `motivo_perdida_id` (el
 * motivo queda en `historial_estados`), sin `campania_id` ni `ticket_id` (D12); `oportunidad_lineas` no se crea (D8).
 * La llave foránea que Cuentas dejó pendiente (`cuenta_marcas_cliente.oportunidad_id`) va en su propia migración.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('oportunidades', function (Blueprint $table): void {
            $table->id();
            $table->string('numero', 30)->unique();
            $table->foreignId('cuenta_id')->index()->constrained('cuentas');
            $table->string('nombre', 200);
            $table->text('descripcion')->nullable();
            $table->decimal('monto_estimado', 14, 2);
            $table->foreignId('moneda_id')->index()->constrained('monedas');
            $table->date('fecha_cierre_estimada');
            $table->date('fecha_cierre_real')->nullable();
            $table->foreignId('estado_id')->index()->constrained('estados');
            $table->date('en_etapa_desde');
            $table->foreignId('origen_comercial_id')->index()->constrained('origenes_comerciales');
            $table->foreignId('ejecutivo_user_id')->index()->constrained('users');
            $table->foreignId('linea_solucion_id')->index()->constrained('lineas_solucion');
            $table->boolean('necesidad_confirmada')->default(false);
            $table->boolean('presupuesto_confirmado')->default(false);
            $table->foreignId('decisor_contacto_id')->nullable()->index()->constrained('party_contactos');
            $table->string('ganador_conocido', 20)->nullable();
            $table->integer('version')->default(1);
            $table->auditoria();
        });

        DB::statement('ALTER TABLE oportunidades ADD CONSTRAINT oportunidades_monto_check CHECK (monto_estimado > 0)');
        DB::statement('ALTER TABLE oportunidades ADD CONSTRAINT oportunidades_version_check CHECK (version >= 1)');
        DB::statement("ALTER TABLE oportunidades ADD CONSTRAINT oportunidades_nombre_check CHECK (btrim(nombre) <> '' AND btrim(coalesce(descripcion, 'x')) <> '')");
        // D4: «¿Lo ganó otra empresa?»; nula mientras no esté perdida.
        DB::statement("ALTER TABLE oportunidades ADD CONSTRAINT oportunidades_ganador_conocido_check CHECK (ganador_conocido IN ('si', 'no', 'no_se_sabe'))");

        Schema::create('oportunidad_competidores', function (Blueprint $table): void {
            $table->id();
            // harness:sin-indice lo cubre el único (oportunidad_id, party_id), que empieza por esta columna.
            $table->foreignId('oportunidad_id')->constrained('oportunidades');
            $table->foreignId('party_id')->index()->constrained('parties');
            $table->decimal('precio', 14, 2)->nullable();
            $table->foreignId('moneda_id')->nullable()->index()->constrained('monedas');
            $table->boolean('precio_desconocido')->default(false);
            $table->boolean('es_ganador')->default(false);
            $table->text('fortalezas')->nullable();
            $table->text('debilidades')->nullable();
            $table->integer('version')->default(1);
            $table->auditoria();
            $table->unique(['oportunidad_id', 'party_id']);
        });

        DB::statement('ALTER TABLE oportunidad_competidores ADD CONSTRAINT oportunidad_competidores_version_check CHECK (version >= 1)');
        DB::statement('ALTER TABLE oportunidad_competidores ADD CONSTRAINT oportunidad_competidores_precio_check CHECK (precio IS NULL OR precio > 0)');
        // D4: precio y moneda van juntos; «precio desconocido» no lleva ninguno y solo lo marca el ganador, que sin precio
        // tiene que marcarlo.
        DB::statement('ALTER TABLE oportunidad_competidores ADD CONSTRAINT oportunidad_competidores_moneda_check CHECK ((precio IS NULL) = (moneda_id IS NULL))');
        DB::statement('ALTER TABLE oportunidad_competidores ADD CONSTRAINT oportunidad_competidores_desconocido_check CHECK (NOT precio_desconocido OR (precio IS NULL AND es_ganador))');
        DB::statement('ALTER TABLE oportunidad_competidores ADD CONSTRAINT oportunidad_competidores_ganador_precio_check CHECK (NOT es_ganador OR precio IS NOT NULL OR precio_desconocido)');
        DB::statement("ALTER TABLE oportunidad_competidores ADD CONSTRAINT oportunidad_competidores_textos_check CHECK (btrim(coalesce(fortalezas, 'x')) <> '' AND btrim(coalesce(debilidades, 'x')) <> '')");
        DB::statement('CREATE UNIQUE INDEX oportunidad_competidores_un_ganador_unique ON oportunidad_competidores (oportunidad_id) WHERE es_ganador');

    }

    public function down(): void
    {
        Schema::dropIfExists('oportunidad_competidores');
        Schema::dropIfExists('oportunidades');
    }
};
