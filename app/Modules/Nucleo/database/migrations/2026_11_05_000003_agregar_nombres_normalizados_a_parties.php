<?php

declare(strict_types=1);

use App\Modules\Nucleo\Parties\PosiblesDuplicados;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Plan 2.20a, N1: la razón social y el nombre comercial normalizados (PosiblesDuplicados::normalizar), con índices de
 * trigramas (`pg_trgm`, viene con PostgreSQL, como `unaccent` y `btree_gist`), para avisar de nombres iguales o
 * parecidos al crear una cuenta del CRM. Los llena el modelo al guardar; aquí se completan las fichas que ya existen.
 * Este es el único lugar que escribe `parties` sin el modelo: una migración, una vez, con la misma función.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');

        Schema::table('parties', function (Blueprint $table): void {
            // `text` y no `varchar(200)` como la razón social: al quitar las tildes, `Str::ascii` puede alargar el texto
            // (ß → ss, Æ → AE) y una razón social larga no podría guardarse.
            $table->text('nombre_normalizado')->nullable();
            $table->text('nombre_comercial_normalizado')->nullable();
        });

        DB::table('parties')->select(['id', 'razon_social', 'nombre_comercial'])->orderBy('id')->each(function (object $fila): void {
            DB::table('parties')->where('id', $fila->id)->update([
                'nombre_normalizado' => PosiblesDuplicados::normalizar((string) $fila->razon_social),
                'nombre_comercial_normalizado' => PosiblesDuplicados::normalizarOpcional($fila->nombre_comercial === null ? null : (string) $fila->nombre_comercial),
            ]);
        });

        DB::statement('ALTER TABLE parties ALTER COLUMN nombre_normalizado SET NOT NULL');
        DB::statement('CREATE INDEX parties_nombre_normalizado_trgm ON parties USING gin (nombre_normalizado gin_trgm_ops)');
        DB::statement('CREATE INDEX parties_nombre_comercial_normalizado_trgm ON parties USING gin (nombre_comercial_normalizado gin_trgm_ops)');

        $regla = 'Lo llena el modelo Party al guardar (plan 2.20a, N1): dentro del núcleo, la razón social y el nombre comercial solo se escriben por el modelo, nunca con update masivo ni DB::table. Si cambia la normalización, se corre nucleo:recalcular-nombres en el mismo PR.';
        DB::statement("COMMENT ON COLUMN parties.nombre_normalizado IS 'PosiblesDuplicados::normalizar(razon_social). {$regla}'");
        DB::statement("COMMENT ON COLUMN parties.nombre_comercial_normalizado IS 'PosiblesDuplicados::normalizar(nombre_comercial); nula sin nombre comercial. {$regla}'");
    }

    public function down(): void
    {
        Schema::table('parties', function (Blueprint $table): void {
            $table->dropIndex('parties_nombre_comercial_normalizado_trgm');
            $table->dropIndex('parties_nombre_normalizado_trgm');
            $table->dropColumn(['nombre_normalizado', 'nombre_comercial_normalizado']);
        });
        // pg_trgm se queda: quitarla podría romper algo que la use después.
    }
};
