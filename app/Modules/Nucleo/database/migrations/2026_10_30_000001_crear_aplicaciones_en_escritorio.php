<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Abrir en el escritorio (plan 1.4z, D6). Cada aplicación (de Techquk o propia) puede tener su programa de escritorio,
 * de una lista cerrada (ProgramaEscritorio, con su CHECK: nunca un enlace escrito a mano). Y cada persona elige, por
 * aplicación, abrirla en el escritorio: una fila en `aplicaciones_en_escritorio`; sin fila, en la web. No está en el
 * DBML: pedido del desarrollador (2026-10-29).
 */
return new class extends Migration
{
    private const array PROGRAMAS = ['word', 'excel', 'powerpoint', 'outlook', 'onenote', 'teams', 'whiteboard', 'whatsapp', 'claude', 'zoom'];

    public function up(): void
    {
        $lista = implode(', ', array_map(fn (string $programa): string => "'{$programa}'", self::PROGRAMAS));

        foreach (['aplicaciones', 'aplicaciones_propias'] as $tabla) {
            Schema::table($tabla, function (Blueprint $table): void {
                $table->string('escritorio', 20)->nullable();
            });
            DB::statement("ALTER TABLE {$tabla} ADD CONSTRAINT {$tabla}_escritorio_check CHECK (escritorio IS NULL OR escritorio IN ({$lista}))");
        }

        Schema::create('aplicaciones_en_escritorio', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->index()->constrained('users');
            $table->foreignId('aplicacion_id')->nullable()->index()->constrained('aplicaciones');
            // Su FK es la compuesta de abajo (con la persona), con su índice aparte.
            $table->unsignedBigInteger('aplicacion_propia_id')->nullable();
            $table->auditoria();

            $table->unique(['user_id', 'aplicacion_id'], 'aplicaciones_en_escritorio_techquk_unique');
            $table->unique(['user_id', 'aplicacion_propia_id'], 'aplicaciones_en_escritorio_propia_unique');
            $table->index('aplicacion_propia_id', 'aplicaciones_en_escritorio_aplicacion_propia_id_index');
        });

        DB::statement('ALTER TABLE aplicaciones_en_escritorio ADD CONSTRAINT aplicaciones_en_escritorio_propia_fk FOREIGN KEY (aplicacion_propia_id, user_id) REFERENCES aplicaciones_propias (id, user_id) ON DELETE CASCADE');
        DB::statement('ALTER TABLE aplicaciones_en_escritorio ADD CONSTRAINT aplicaciones_en_escritorio_una_check CHECK (num_nonnulls(aplicacion_id, aplicacion_propia_id) = 1)');

        // Una sola vez, en una base que ya tenía las de fábrica: su programa. En una nueva, lo pone la semilla al crearlas.
        // No va en la semilla (revisión de arquitectura): la semilla corre en cada despliegue y le devolvería el programa a
        // una que administración dejó a propósito solo en la web.
        foreach (['word' => 'word', 'excel' => 'excel', 'powerpoint' => 'powerpoint', 'outlook' => 'outlook', 'teams' => 'teams', 'whiteboard' => 'whiteboard', 'whatsapp' => 'whatsapp', 'claude' => 'claude'] as $codigo => $programa) {
            DB::table('aplicaciones')->where('codigo', $codigo)->whereNull('escritorio')->update(['escritorio' => $programa, 'version' => DB::raw('version + 1')]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('aplicaciones_en_escritorio');

        foreach (['aplicaciones', 'aplicaciones_propias'] as $tabla) {
            DB::statement("ALTER TABLE {$tabla} DROP CONSTRAINT {$tabla}_escritorio_check");
            Schema::table($tabla, function (Blueprint $table): void {
                $table->dropColumn('escritorio');
            });
        }
    }
};
