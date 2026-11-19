<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * M1 · Contactos, sedes y cuentas bancarias de una party (DBML v2), con lo aprobado en el plan 1.3b:
 * - D1: cada contacto es un vínculo (esta persona en esta organización) con `desde` y `hasta` opcionales; sin
 *   `es_activo` (manda `hasta`). Un traslado abre un vínculo nuevo que apunta al anterior (`contacto_anterior_id`).
 * - D2: un contacto principal por party y vigente; una cuenta principal por party y moneda, activa y nunca la de
 *   detracciones; una sola de detracciones activa por party.
 * - D3: sedes sin `es_fiscal` (la dirección fiscal es de la party) ni `es_entrega`; con el anexo SUNAT.
 * - D4: la sede apunta solo a un contacto de su misma party; nombre único entre las activas sin mayúsculas ni
 *   acentos, por `nombre_comparable` (la convención prohíbe funciones en la base: el modelo le pide el valor
 *   a la base al guardar, `PartySede::comparable()`, y un CHECK lo verifica); coordenadas `decimal(10,7)`, las dos o ninguna.
 * - D5: número de cuenta y CCI solo con dígitos; la misma cuenta en el mismo banco solo se repite con la misma
 *   clave de persona (la pareja DNI ↔ RUC 10), también entre desactivadas. La cuenta copia la clave de su
 *   titular por una llave compuesta (si un día se corrige un documento, la cascada la actualiza).
 * - D6: auditoría y `version` en las tres.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('party_contactos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('party_id')->index()->constrained('parties');
            $table->string('nombres', 100);
            $table->string('apellidos', 100);
            $table->string('cargo', 120)->nullable();
            $table->string('area', 120)->nullable();
            $table->string('telefono', 30)->nullable();
            $table->string('email', 150)->nullable();
            $table->boolean('es_decisor')->default(false);
            $table->boolean('es_principal')->default(false);
            $table->date('desde')->nullable();
            $table->date('hasta')->nullable();
            // Único: un vínculo cerrado continúa en un solo lugar.
            $table->foreignId('contacto_anterior_id')->nullable()->unique()->constrained('party_contactos');
            $table->integer('version')->default(1);
            $table->auditoria();

            // Destino de la llave compuesta de party_sedes: la sede apunta a un contacto de su misma party.
            $table->unique(['party_id', 'id']);
        });

        DB::statement("ALTER TABLE party_contactos ADD CONSTRAINT party_contactos_nombres_check CHECK (btrim(nombres) <> '' AND btrim(apellidos) <> '')");
        DB::statement('ALTER TABLE party_contactos ADD CONSTRAINT party_contactos_hasta_check CHECK (hasta IS NULL OR desde IS NULL OR hasta >= desde)');
        DB::statement('ALTER TABLE party_contactos ADD CONSTRAINT party_contactos_principal_vigente_check CHECK (NOT es_principal OR hasta IS NULL)');
        DB::statement('ALTER TABLE party_contactos ADD CONSTRAINT party_contactos_anterior_check CHECK (contacto_anterior_id <> id)');
        DB::statement('ALTER TABLE party_contactos ADD CONSTRAINT party_contactos_version_check CHECK (version >= 1)');
        DB::statement('CREATE UNIQUE INDEX party_contactos_un_principal_unique ON party_contactos (party_id) WHERE es_principal');

        Schema::create('party_sedes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('party_id')->index()->constrained('parties');
            $table->string('nombre', 150);
            $table->string('nombre_comparable', 150);
            $table->string('direccion', 255);
            $table->foreignId('ubigeo_id')->nullable()->index()->constrained('ubigeos');
            $table->decimal('latitud', 10, 7)->nullable();
            $table->decimal('longitud', 10, 7)->nullable();
            // Llave compuesta (party_id, contacto_id) más abajo; este índice la sirve.
            $table->unsignedBigInteger('contacto_id')->nullable();
            $table->string('codigo_establecimiento', 4)->nullable();
            $table->boolean('es_activa')->default(true);
            $table->integer('version')->default(1);
            $table->auditoria();

            $table->index(['contacto_id', 'party_id']);
        });

        DB::statement('ALTER TABLE party_sedes ADD CONSTRAINT party_sedes_contacto_foreign FOREIGN KEY (party_id, contacto_id) REFERENCES party_contactos (party_id, id)');
        DB::statement("ALTER TABLE party_sedes ADD CONSTRAINT party_sedes_nombre_check CHECK (btrim(nombre) <> '' AND btrim(direccion) <> '')");
        DB::statement('ALTER TABLE party_sedes ADD CONSTRAINT party_sedes_nombre_comparable_check CHECK (nombre_comparable = lower(unaccent(nombre)))');
        DB::statement('ALTER TABLE party_sedes ADD CONSTRAINT party_sedes_coordenadas_check CHECK ((latitud IS NULL) = (longitud IS NULL) AND (latitud IS NULL OR (latitud BETWEEN -90 AND 90 AND longitud BETWEEN -180 AND 180)))');
        DB::statement("ALTER TABLE party_sedes ADD CONSTRAINT party_sedes_codigo_establecimiento_check CHECK (codigo_establecimiento ~ '^[0-9]{4}$')");
        DB::statement('ALTER TABLE party_sedes ADD CONSTRAINT party_sedes_version_check CHECK (version >= 1)');
        DB::statement('CREATE UNIQUE INDEX party_sedes_nombre_activa_unique ON party_sedes (party_id, nombre_comparable) WHERE es_activa');
        DB::statement('CREATE UNIQUE INDEX party_sedes_codigo_establecimiento_unique ON party_sedes (party_id, codigo_establecimiento) WHERE es_activa AND codigo_establecimiento IS NOT NULL');

        Schema::create('party_cuentas_bancarias', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('party_id')->index()->constrained('parties');
            // Copia de la clave de persona del titular, por la llave compuesta de abajo (5b).
            $table->unsignedBigInteger('persona_tipo_id');
            $table->string('persona_numero', 20);
            $table->foreignId('banco_party_id')->index()->constrained('parties');
            $table->string('numero_cuenta', 30);
            $table->string('cci', 20)->nullable();
            $table->foreignId('moneda_id')->index()->constrained('monedas');
            $table->boolean('es_detraccion')->default(false);
            $table->boolean('es_principal')->default(false);
            $table->boolean('es_activa')->default(true);
            $table->integer('version')->default(1);
            $table->auditoria();

            $table->unique(['party_id', 'banco_party_id', 'numero_cuenta'], 'party_cuentas_bancarias_cuenta_unique');
            $table->index(['party_id', 'persona_tipo_id', 'persona_numero']);
        });

        DB::statement('ALTER TABLE party_cuentas_bancarias ADD CONSTRAINT party_cuentas_bancarias_titular_foreign FOREIGN KEY (party_id, persona_tipo_id, persona_numero) REFERENCES parties (id, persona_tipo_id, persona_numero) ON UPDATE CASCADE');
        DB::statement("ALTER TABLE party_cuentas_bancarias ADD CONSTRAINT party_cuentas_bancarias_numero_check CHECK (numero_cuenta ~ '^[0-9]{1,30}$')");
        DB::statement("ALTER TABLE party_cuentas_bancarias ADD CONSTRAINT party_cuentas_bancarias_cci_check CHECK (cci ~ '^[0-9]{20}$')");
        DB::statement('ALTER TABLE party_cuentas_bancarias ADD CONSTRAINT party_cuentas_bancarias_principal_check CHECK (NOT es_principal OR (es_activa AND NOT es_detraccion))');
        DB::statement('ALTER TABLE party_cuentas_bancarias ADD CONSTRAINT party_cuentas_bancarias_version_check CHECK (version >= 1)');
        DB::statement('CREATE UNIQUE INDEX party_cuentas_bancarias_principal_unique ON party_cuentas_bancarias (party_id, moneda_id) WHERE es_principal');
        DB::statement('CREATE UNIQUE INDEX party_cuentas_bancarias_detraccion_unique ON party_cuentas_bancarias (party_id) WHERE es_detraccion AND es_activa');
        DB::statement('CREATE UNIQUE INDEX party_cuentas_bancarias_cci_unique ON party_cuentas_bancarias (party_id, cci) WHERE cci IS NOT NULL');
        // La misma cuenta (o el mismo CCI) solo se repite con la misma clave de persona: tipo y número, por separado.
        DB::statement('ALTER TABLE party_cuentas_bancarias ADD CONSTRAINT party_cuentas_bancarias_numero_persona_excl EXCLUDE USING gist (banco_party_id WITH =, numero_cuenta WITH =, persona_tipo_id WITH <>)');
        DB::statement('ALTER TABLE party_cuentas_bancarias ADD CONSTRAINT party_cuentas_bancarias_numero_persona_num_excl EXCLUDE USING gist (banco_party_id WITH =, numero_cuenta WITH =, persona_numero WITH <>)');
        DB::statement('ALTER TABLE party_cuentas_bancarias ADD CONSTRAINT party_cuentas_bancarias_cci_persona_excl EXCLUDE USING gist (cci WITH =, persona_tipo_id WITH <>)');
        DB::statement('ALTER TABLE party_cuentas_bancarias ADD CONSTRAINT party_cuentas_bancarias_cci_persona_num_excl EXCLUDE USING gist (cci WITH =, persona_numero WITH <>)');
    }

    public function down(): void
    {
        Schema::dropIfExists('party_cuentas_bancarias');
        Schema::dropIfExists('party_sedes');
        Schema::dropIfExists('party_contactos');
    }
};
