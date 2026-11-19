<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * M10 · Completa `users` (plan 1.4, D4).
 * - Cada usuario es una persona: `party_id`, obligatorio salvo para el usuario de sistema.
 * - Una persona, una cuenta: el usuario copia la clave de persona de su ficha (como `party_cuentas_bancarias`) y la
 *   clave es única. Así Juan no tiene un usuario en su ficha DNI y otro en su ficha RUC 10. La llave compuesta
 *   impide que la copia se desvíe de la ficha. Que la ficha sea de DNI, CE o pasaporte (nunca un RUC) lo valida
 *   SeguridadService: un CHECK no lee el código del tipo en otra tabla.
 * - Sin `unidad_organizativa_id` del DBML: la unidad sale del puesto, que la exige. Dos lugares serían dos verdades.
 * - `version`: los usuarios se editan desde una pantalla.
 * Falla si hay usuarios (no de sistema) sin persona: no se inventa una. En una base nueva no los hay: el seeder
 * crea el usuario de desarrollo ya con su persona.
 */
return new class extends Migration
{
    public function up(): void
    {
        $sinPersona = DB::table('users')->where('es_sistema', false)->pluck('email');

        if ($sinPersona->isNotEmpty()) {
            throw new RuntimeException('Usuarios sin persona: '.$sinPersona->implode(', ').'. Reconstruye la base de desarrollo (migrate:fresh --seed) o asígnales una persona antes de migrar.');
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->foreignId('party_id')->nullable()->index()->constrained('parties');
            // Copia de la clave de persona de la ficha, por la llave compuesta de abajo.
            $table->unsignedBigInteger('persona_tipo_id')->nullable();
            $table->string('persona_numero', 20)->nullable();
            $table->foreignId('puesto_id')->nullable()->index()->constrained('puestos');
            $table->timestampTz('ultimo_acceso_at')->nullable();
            $table->integer('version')->default(1);

            $table->index(['party_id', 'persona_tipo_id', 'persona_numero']);
            $table->unique(['persona_tipo_id', 'persona_numero'], 'users_persona_unique');
        });

        DB::statement('ALTER TABLE users ADD CONSTRAINT users_persona_foreign FOREIGN KEY (party_id, persona_tipo_id, persona_numero) REFERENCES parties (id, persona_tipo_id, persona_numero) ON UPDATE CASCADE');
        DB::statement('ALTER TABLE users ADD CONSTRAINT users_persona_completa_check CHECK ((party_id IS NULL) = (persona_tipo_id IS NULL) AND (party_id IS NULL) = (persona_numero IS NULL))');
        DB::statement('ALTER TABLE users ADD CONSTRAINT users_party_check CHECK (es_sistema OR party_id IS NOT NULL)');
        DB::statement('ALTER TABLE users ADD CONSTRAINT users_version_check CHECK (version >= 1)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE users DROP CONSTRAINT users_version_check');
        DB::statement('ALTER TABLE users DROP CONSTRAINT users_party_check');
        DB::statement('ALTER TABLE users DROP CONSTRAINT users_persona_completa_check');
        DB::statement('ALTER TABLE users DROP CONSTRAINT users_persona_foreign');

        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique('users_persona_unique');
            $table->dropIndex(['party_id', 'persona_tipo_id', 'persona_numero']);
            $table->dropConstrainedForeignId('puesto_id');
            $table->dropConstrainedForeignId('party_id');
            $table->dropColumn(['persona_tipo_id', 'persona_numero', 'ultimo_acceso_at', 'version']);
        });
    }
};
