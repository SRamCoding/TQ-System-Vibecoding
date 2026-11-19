<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * M1 · Clave de persona en `parties` (plan 1.3b, D5 · 5b): tipo y número del documento que identifica a la
 * persona. Para un RUC 10 es el DNI que lleva dentro (DNI + dígitos 3 a 10); para todo lo demás, su propio
 * documento. Sirve para que la misma cuenta bancaria pueda estar en la pareja DNI ↔ RUC 10 de una persona
 * (plan 1.3, D9) y en ninguna otra pareja de parties. La llena el modelo al crear; el CHECK solo admite esos
 * dos valores. Depende de que el documento no se edite (D5 de la 1.3): un «corregir documento» debe recalcularla.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parties', function (Blueprint $table): void {
            $table->foreignId('persona_tipo_id')->nullable()->index()->constrained('tipos_documento_identidad');
            $table->string('persona_numero', 20)->nullable();
        });

        DB::statement(<<<'SQL'
            WITH ruc10 AS (
                SELECT p.id, dni.id AS dni_id
                FROM parties p
                JOIN tipos_documento_identidad ruc ON ruc.id = p.tipo_documento_id AND ruc.codigo = '6'
                JOIN tipos_documento_identidad dni ON dni.codigo = '1'
                WHERE p.numero_documento ~ '^10[0-9]{9}$'
            )
            UPDATE parties p SET
                persona_tipo_id = COALESCE(r.dni_id, p.tipo_documento_id),
                persona_numero = CASE WHEN r.id IS NULL THEN p.numero_documento ELSE substring(p.numero_documento FROM 3 FOR 8) END
            FROM parties q LEFT JOIN ruc10 r ON r.id = q.id
            WHERE q.id = p.id
            SQL);

        DB::statement('ALTER TABLE parties ALTER COLUMN persona_tipo_id SET NOT NULL');
        DB::statement('ALTER TABLE parties ALTER COLUMN persona_numero SET NOT NULL');
        DB::statement(<<<'SQL'
            ALTER TABLE parties ADD CONSTRAINT parties_clave_persona_check CHECK (
                (persona_tipo_id = tipo_documento_id AND persona_numero = numero_documento)
                OR (numero_documento ~ '^10[0-9]{9}$' AND persona_numero = substring(numero_documento FROM 3 FOR 8))
            )
            SQL);
        // Destino de la llave compuesta de party_cuentas_bancarias: la cuenta copia la clave de su titular.
        DB::statement('ALTER TABLE parties ADD CONSTRAINT parties_id_clave_persona_unique UNIQUE (id, persona_tipo_id, persona_numero)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE parties DROP CONSTRAINT parties_id_clave_persona_unique');
        DB::statement('ALTER TABLE parties DROP CONSTRAINT parties_clave_persona_check');
        Schema::table('parties', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('persona_tipo_id');
            $table->dropColumn('persona_numero');
        });
    }
};
