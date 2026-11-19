<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * M1 · Parties y sus roles (DBML v2), con los ajustes aprobados en el plan 1.3:
 * - D2: auditoría y `version`; la base garantiza el tipo de persona, el número ya normalizado (así la unicidad
 *   no se salta con un espacio) y los nombres según el tipo; un rol no tiene dos periodos que se crucen
 *   (`hasta` es el último día vigente y vacío es sin fin).
 * - D4: una persona jurídica no lleva nombres ni apellidos.
 * - D6: índice por prefijo del número para el buscador de los selectores.
 * - D8: `party_roles` sin `es_activo`: manda `hasta`.
 * `party_roles.version`: lo pide la convención para toda tabla que se edite desde una pantalla (cerrar un rol).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parties', function (Blueprint $table): void {
            $table->id();
            $table->string('tipo', 20);
            // El índice único (tipo, número) también sirve de índice de esta llave foránea.
            $table->foreignId('tipo_documento_id')->constrained('tipos_documento_identidad');
            $table->string('numero_documento', 20);
            $table->string('razon_social', 200);
            $table->string('nombre_comercial', 200)->nullable();
            $table->string('nombres', 100)->nullable();
            $table->string('apellidos', 100)->nullable();
            $table->string('direccion_fiscal', 255)->nullable();
            $table->foreignId('ubigeo_id')->nullable()->index()->constrained('ubigeos');
            $table->string('telefono', 30)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('condicion_sunat', 30)->nullable();
            $table->string('estado_sunat', 30)->nullable();
            $table->boolean('es_agente_retencion')->default(false);
            $table->boolean('es_agente_percepcion')->default(false);
            $table->boolean('es_buen_contribuyente')->default(false);
            $table->boolean('es_activo')->default(true);
            $table->integer('version')->default(1);
            $table->auditoria();

            $table->unique(['tipo_documento_id', 'numero_documento']);
        });

        DB::statement("ALTER TABLE parties ADD CONSTRAINT parties_tipo_check CHECK (tipo IN ('natural', 'juridica'))");
        DB::statement("ALTER TABLE parties ADD CONSTRAINT parties_numero_documento_check CHECK (numero_documento ~ '^[0-9A-Z]{1,20}$')");
        DB::statement("ALTER TABLE parties ADD CONSTRAINT parties_razon_social_check CHECK (btrim(razon_social) <> '')");
        DB::statement("ALTER TABLE parties ADD CONSTRAINT parties_natural_nombres_check CHECK (tipo <> 'natural' OR (nombres IS NOT NULL AND apellidos IS NOT NULL))");
        DB::statement("ALTER TABLE parties ADD CONSTRAINT parties_juridica_nombres_check CHECK (tipo <> 'juridica' OR (nombres IS NULL AND apellidos IS NULL))");
        DB::statement('ALTER TABLE parties ADD CONSTRAINT parties_version_check CHECK (version >= 1)');
        DB::statement('CREATE INDEX parties_numero_documento_prefijo_index ON parties (numero_documento varchar_pattern_ops)');

        Schema::create('party_roles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('party_id')->index()->constrained('parties');
            $table->foreignId('rol_party_id')->index()->constrained('roles_party');
            $table->date('desde');
            $table->date('hasta')->nullable();
            $table->integer('version')->default(1);
            $table->auditoria();
        });

        DB::statement('ALTER TABLE party_roles ADD CONSTRAINT party_roles_hasta_check CHECK (hasta IS NULL OR hasta >= desde)');
        DB::statement('ALTER TABLE party_roles ADD CONSTRAINT party_roles_version_check CHECK (version >= 1)');
        DB::statement("ALTER TABLE party_roles ADD CONSTRAINT party_roles_sin_cruce EXCLUDE USING gist (party_id WITH =, rol_party_id WITH =, daterange(desde, hasta, '[]') WITH &&)");
    }

    public function down(): void
    {
        Schema::dropIfExists('party_roles');
        Schema::dropIfExists('parties');
    }
};
