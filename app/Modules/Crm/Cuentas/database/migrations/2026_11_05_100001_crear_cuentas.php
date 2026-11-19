<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Cuentas (plan 2.20, CRM-01): la ficha comercial de una persona o empresa (D1) y por qué es cliente (D8).
 * Lo que la base no puede revisar lo exige CuentasService: nivel de gobierno solo en públicas (D3, se deriva de
 * party_roles), el ejecutivo activo con crm.cuentas.atender (D7), el detalle si el motivo es «Otro» (D9) y
 * `cliente_desde` igual a la marca más antigua (D8, con su prueba de coherencia).
 * Desviaciones del DBML, a propósito:
 * - `cuenta_marcas_cliente` lleva `auditoria()` completa (convención). La de una oportunidad se borra al revertirla; la
 *   manual no se borra al quitarla: se anula con `quitada_en`, `quitada_por_user_id` y `motivo_quitada` (D8.6,
 *   desarrollador, 2026-11-06), porque la bitácora no guarda motivos. Solo cuentan las no anuladas.
 * - `cuenta_marcas_cliente.oportunidad_id` con índice y sin llave foránea: `oportunidades` es de CRM-02, que la agrega.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cuentas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('party_id')->unique()->constrained('parties');
            $table->foreignId('ejecutivo_user_id')->index()->constrained('users');
            $table->foreignId('origen_comercial_id')->index()->constrained('origenes_comerciales');
            $table->foreignId('nivel_gobierno_id')->nullable()->index()->constrained('niveles_gobierno');
            $table->date('cliente_desde')->nullable();
            $table->integer('version')->default(1);
            $table->text('notas')->nullable();
            $table->boolean('es_activa')->default(true);
            $table->foreignId('motivo_inactiva_id')->nullable()->index()->constrained('motivos');
            $table->text('motivo_inactiva_detalle')->nullable();
            $table->auditoria();
        });

        DB::statement('ALTER TABLE cuentas ADD CONSTRAINT cuentas_version_check CHECK (version >= 1)');
        // D9: inactiva si y solo si tiene motivo; sin motivo, tampoco detalle.
        DB::statement('ALTER TABLE cuentas ADD CONSTRAINT cuentas_motivo_inactiva_check CHECK (es_activa = (motivo_inactiva_id IS NULL) AND (motivo_inactiva_id IS NOT NULL OR motivo_inactiva_detalle IS NULL))');
        DB::statement("ALTER TABLE cuentas ADD CONSTRAINT cuentas_textos_check CHECK (btrim(coalesce(notas, 'x')) <> '' AND btrim(coalesce(motivo_inactiva_detalle, 'x')) <> '')");

        Schema::create('cuenta_marcas_cliente', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cuenta_id')->index()->constrained('cuentas');
            $table->string('origen', 20);
            // harness:sin-indice lo cubre el único parcial de abajo: una oportunidad marca una sola cuenta.
            $table->unsignedBigInteger('oportunidad_id')->nullable();
            $table->date('fecha');
            $table->text('comentario')->nullable();
            // D8.6 (desarrollador, 2026-11-06): quitar la marca manual la anula, con su motivo, en vez de borrarla.
            $table->timestampTz('quitada_en')->nullable();
            $table->foreignId('quitada_por_user_id')->nullable()->index()->constrained('users');
            $table->text('motivo_quitada')->nullable();
            $table->auditoria();
        });

        DB::statement("ALTER TABLE cuenta_marcas_cliente ADD CONSTRAINT cuenta_marcas_cliente_origen_check CHECK (origen IN ('oportunidad', 'manual'))");
        // La de una oportunidad lleva su id; la manual, su comentario (y no un id).
        DB::statement("ALTER TABLE cuenta_marcas_cliente ADD CONSTRAINT cuenta_marcas_cliente_oportunidad_check CHECK ((origen = 'oportunidad') = (oportunidad_id IS NOT NULL))");
        DB::statement("ALTER TABLE cuenta_marcas_cliente ADD CONSTRAINT cuenta_marcas_cliente_comentario_check CHECK (origen <> 'manual' OR btrim(coalesce(comentario, '')) <> '')");
        // Anulada: las tres juntas, con motivo escrito, y solo la manual (la de una oportunidad se borra al revertirla).
        DB::statement("ALTER TABLE cuenta_marcas_cliente ADD CONSTRAINT cuenta_marcas_cliente_quitada_check CHECK ((quitada_en IS NULL AND quitada_por_user_id IS NULL AND motivo_quitada IS NULL) OR (quitada_en IS NOT NULL AND quitada_por_user_id IS NOT NULL AND btrim(coalesce(motivo_quitada, '')) <> '' AND origen = 'manual'))");
        // Una oportunidad pertenece a una sola cuenta: una sola marca en toda la tabla (revisión de la 2.20).
        DB::statement('CREATE UNIQUE INDEX cuenta_marcas_cliente_oportunidad_unique ON cuenta_marcas_cliente (oportunidad_id) WHERE oportunidad_id IS NOT NULL');
        DB::statement("CREATE UNIQUE INDEX cuenta_marcas_cliente_una_manual_unique ON cuenta_marcas_cliente (cuenta_id) WHERE origen = 'manual' AND quitada_en IS NULL");
    }

    public function down(): void
    {
        Schema::dropIfExists('cuenta_marcas_cliente');
        Schema::dropIfExists('cuentas');
    }
};
