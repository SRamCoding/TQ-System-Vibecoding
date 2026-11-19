<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Plan 1.4d, D4 y D7. Tablas nuevas: no están en el DBML de agosto.
 * - `grandes_momentos`: lo que se eligió cada año para cada fecha (la variante y el versículo), una sola vez y para
 *   todas las personas. La variante es la clave de su VarianteGranMomento (código, sin FK).
 * - `momentos_vistos`: quién vio cuál. Su `created_at` es el momento de la marca.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grandes_momentos', function (Blueprint $table): void {
            $table->id();
            $table->string('ocasion', 20);
            $table->smallInteger('anio');
            $table->string('variante', 40);
            $table->foreignId('frase_saludo_id')->nullable()->index()->constrained('frases_saludo');
            $table->auditoria();

            $table->unique(['ocasion', 'anio'], 'grandes_momentos_unique');
        });

        DB::statement("ALTER TABLE grandes_momentos ADD CONSTRAINT grandes_momentos_ocasion_check CHECK (ocasion IN ('navidad', 'anio_nuevo', 'semana_santa', 'fiestas_patrias', 'aniversario'))");
        DB::statement('ALTER TABLE grandes_momentos ADD CONSTRAINT grandes_momentos_anio_check CHECK (anio BETWEEN 2000 AND 2200)');
        DB::statement("ALTER TABLE grandes_momentos ADD CONSTRAINT grandes_momentos_variante_check CHECK (variante ~ '^[a-z][a-z0-9-]{0,39}$')");

        Schema::create('momentos_vistos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->index()->constrained('users');
            $table->foreignId('gran_momento_id')->index()->constrained('grandes_momentos');
            $table->auditoria();

            $table->unique(['user_id', 'gran_momento_id'], 'momentos_vistos_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('momentos_vistos');
        Schema::dropIfExists('grandes_momentos');
    }
};
