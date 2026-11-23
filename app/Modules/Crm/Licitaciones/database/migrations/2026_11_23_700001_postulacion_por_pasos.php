<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * MVP rápido (observación 7.3, ficha t1-licitaciones): la postulación se gestiona entera desde el sistema y en el SEACE
 * solo se envía. Agrega lo que piden los 8 pasos sin tocar lo que ya había (se corre con `migrate`, sin reconstruir):
 * - licitaciones: registro como participante (fecha y constancia), bases integradas, presupuesto del que sale la
 *   oferta, paquete armado y presentación con su hora y constancia.
 * - documentos del expediente: estado (falta, en_revision, listo, firmado, no_aplica), responsable, archivo y si
 *   necesita firma. `es_listo` sigue y se iguala al estado (listo, firmado o no aplica).
 * - consultas: tipo (consulta u observación), numeral de las bases, estado (borrador, enviada, absuelta), origen (ia o
 *   manual), fecha de envío y constancia.
 * - subsanaciones: lo que pide la entidad, su plazo y la respuesta con su constancia.
 * Atajo: estados como texto con su lista cerrada en el modelo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('licitaciones', function (Blueprint $table): void {
            $table->date('registrado_seace_el')->nullable();
            $table->string('constancia_registro', 300)->nullable();
            $table->date('bases_integradas_el')->nullable();
            $table->date('consultas_absueltas_el')->nullable();
            $table->foreignId('presupuesto_id')->nullable()->constrained('presupuestos');
            $table->timestampTz('paquete_armado_at')->nullable();
            $table->string('paquete_archivo', 300)->nullable();
            $table->timestampTz('presentada_at')->nullable();
            $table->string('constancia_presentacion', 300)->nullable();
        });

        Schema::table('licitacion_documentos', function (Blueprint $table): void {
            $table->string('estado', 20)->default('falta');
            $table->foreignId('responsable_user_id')->nullable()->constrained('users');
            $table->string('archivo', 300)->nullable();
            $table->boolean('requiere_firma')->default(false);
        });

        Schema::table('licitacion_consultas', function (Blueprint $table): void {
            $table->string('tipo', 20)->default('consulta');
            $table->string('numeral', 60)->nullable();
            $table->string('estado', 20)->default('enviada');
            $table->string('origen', 20)->default('manual');
            $table->date('enviada_el')->nullable();
            $table->string('constancia', 300)->nullable();
        });

        Schema::create('licitacion_subsanaciones', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('licitacion_id')->index()->constrained('licitaciones')->cascadeOnDelete();
            $table->text('pedido');
            $table->date('pedida_el');
            $table->date('plazo');
            $table->text('respuesta')->nullable();
            $table->string('constancia', 300)->nullable();
            $table->date('respondida_el')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('licitacion_subsanaciones');
        Schema::table('licitacion_consultas', fn (Blueprint $t) => $t->dropColumn(['tipo', 'numeral', 'estado', 'origen', 'enviada_el', 'constancia']));
        Schema::table('licitacion_documentos', fn (Blueprint $t) => $t->dropConstrainedForeignId('responsable_user_id'));
        Schema::table('licitacion_documentos', fn (Blueprint $t) => $t->dropColumn(['estado', 'archivo', 'requiere_firma']));
        Schema::table('licitaciones', fn (Blueprint $t) => $t->dropConstrainedForeignId('presupuesto_id'));
        Schema::table('licitaciones', fn (Blueprint $t) => $t->dropColumn(['registrado_seace_el', 'constancia_registro', 'bases_integradas_el', 'consultas_absueltas_el', 'paquete_armado_at', 'paquete_archivo', 'presentada_at', 'constancia_presentacion']));
    }
};
