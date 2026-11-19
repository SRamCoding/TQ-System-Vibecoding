<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Tus 3 aplicaciones principales (plan 1.4x, D1): las que cada persona deja arriba en el panel y en el muelle. No está en
 * el DBML: pedido del desarrollador (2026-10-29). Sin filas, son las 3 primeras del orden de administración.
 *
 * Como en `usos_aplicacion`: una fila es de una aplicación de Techquk o de una propia, nunca de las dos; la propia lleva la
 * misma persona (FK compuesta) y se borra con ella. Una posición y una aplicación no se repiten por persona.
 *
 * También suma los íconos nuevos del catálogo (D5) a los CHECK de `aplicaciones` y `aplicaciones_propias`.
 */
return new class extends Migration
{
    private const array ICONOS_ANTES = ['word', 'excel', 'powerpoint', 'outlook', 'onedrive', 'teams', 'onenote', 'generica'];

    private const array ICONOS = [...self::ICONOS_ANTES, 'claude', 'whatsapp', 'gmail', 'google_drive', 'linkedin'];

    public function up(): void
    {
        Schema::create('aplicaciones_principales', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->index()->constrained('users');
            $table->smallInteger('posicion');
            $table->foreignId('aplicacion_id')->nullable()->index()->constrained('aplicaciones');
            // Su FK es la compuesta de abajo (con la persona), con su índice aparte.
            $table->unsignedBigInteger('aplicacion_propia_id')->nullable();
            $table->auditoria();

            $table->unique(['user_id', 'posicion'], 'aplicaciones_principales_posicion_unique');
            $table->unique(['user_id', 'aplicacion_id'], 'aplicaciones_principales_techquk_unique');
            $table->unique(['user_id', 'aplicacion_propia_id'], 'aplicaciones_principales_propia_unique');
            $table->index('aplicacion_propia_id', 'aplicaciones_principales_aplicacion_propia_id_index');
        });

        DB::statement('ALTER TABLE aplicaciones_principales ADD CONSTRAINT aplicaciones_principales_propia_fk FOREIGN KEY (aplicacion_propia_id, user_id) REFERENCES aplicaciones_propias (id, user_id) ON DELETE CASCADE');
        DB::statement('ALTER TABLE aplicaciones_principales ADD CONSTRAINT aplicaciones_principales_una_check CHECK (num_nonnulls(aplicacion_id, aplicacion_propia_id) = 1)');
        DB::statement('ALTER TABLE aplicaciones_principales ADD CONSTRAINT aplicaciones_principales_posicion_check CHECK (posicion BETWEEN 1 AND 3)');

        $this->iconos(self::ICONOS);
    }

    public function down(): void
    {
        Schema::dropIfExists('aplicaciones_principales');
        $this->iconos(self::ICONOS_ANTES);
    }

    /** @param list<string> $iconos */
    private function iconos(array $iconos): void
    {
        $lista = implode(', ', array_map(fn (string $icono): string => "'{$icono}'", $iconos));

        foreach (['aplicaciones', 'aplicaciones_propias'] as $tabla) {
            DB::statement("ALTER TABLE {$tabla} DROP CONSTRAINT {$tabla}_icono_check");
            DB::statement("ALTER TABLE {$tabla} ADD CONSTRAINT {$tabla}_icono_check CHECK (icono IN ({$lista}))");
        }
    }
};
