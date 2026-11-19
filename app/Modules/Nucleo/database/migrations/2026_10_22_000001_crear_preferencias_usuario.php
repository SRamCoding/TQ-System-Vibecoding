<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Plan 1.4g, D2: las preferencias que cada persona cambió, como texto; las declara RegistroPreferencias, que las
 * convierte a su tipo y guarda el defecto. Sin fila, vale el defecto. Nunca datos personales ni estado de trabajo
 * (D7). Las listas no van aquí: viven en la tabla de su módulo (D3).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('preferencias_usuario', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->index()->constrained('users');
            $table->string('clave', 80);
            $table->text('valor');
            $table->auditoria();

            $table->unique(['user_id', 'clave'], 'preferencias_usuario_unique');
        });

        // `grupo.nombre`, en snake_case: el mismo formato que exige DefinicionPreferencia.
        DB::statement("ALTER TABLE preferencias_usuario ADD CONSTRAINT preferencias_usuario_clave_check CHECK (clave ~ '^[a-z][a-z0-9_]*\\.[a-z][a-z0-9_]*$')");
        // Un valor vacío no vale para ningún tipo: sin preferencia, no hay fila.
        DB::statement("ALTER TABLE preferencias_usuario ADD CONSTRAINT preferencias_usuario_valor_check CHECK (btrim(valor) <> '')");
    }

    public function down(): void
    {
        Schema::dropIfExists('preferencias_usuario');
    }
};
