<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * MVP rápido (observación 7.5, ficha t1-licitaciones): la bandeja con detalle. Se agrega (sin reconstruir):
 * - seace_lineas: las líneas de negocio de la búsqueda, cada una con sus palabras clave, sus palabras negativas (si
 *   aparece una, no entra aunque coincida otra) y su valor mínimo. Reemplazan el parámetro único de palabras clave: sus
 *   palabras se reparten en tres líneas.
 * - convocatorias_seace: su línea, responsable, etiquetas, si se trajo igual a la bandeja y por qué quedó fuera.
 * - convocatoria_vistas: quién ya abrió cada una («Nueva» es por persona).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seace_lineas', function (Blueprint $table): void {
            $table->id();
            $table->string('nombre', 80);
            $table->json('palabras');
            $table->json('negativas');
            $table->decimal('valor_minimo', 14, 2)->default(0);
            $table->smallInteger('orden')->default(0);
            $table->timestamps();
        });

        $lineas = [
            ['Videovigilancia', ['videovigilancia', 'cámaras', 'cámara', 'cctv', 'monitoreo'], ['cámara frigorífica', 'cámara de gas']],
            ['Redes', ['red de datos', 'cableado estructurado', 'fibra óptica', 'switch', 'wifi', 'inalámbric', 'enlaces'], ['red de agua', 'red de desagüe']],
            ['Software', ['software', 'sistema de información', 'telemetría', 'centro de datos', 'data center', 'servidor', 'servidores'], ['software contable licencia']],
        ];

        foreach ($lineas as $i => [$nombre, $palabras, $negativas]) {
            DB::table('seace_lineas')->insert(['nombre' => $nombre, 'palabras' => json_encode($palabras, JSON_UNESCAPED_UNICODE), 'negativas' => json_encode($negativas, JSON_UNESCAPED_UNICODE),
                'valor_minimo' => '20000.00', 'orden' => $i + 1, 'created_at' => now(), 'updated_at' => now()]);
        }

        Schema::table('convocatorias_seace', function (Blueprint $table): void {
            $table->foreignId('linea_id')->nullable()->constrained('seace_lineas')->nullOnDelete();
            $table->foreignId('responsable_user_id')->nullable()->constrained('users');
            $table->json('etiquetas')->nullable();
            $table->boolean('es_traida')->default(false);
            $table->string('fuera_por', 200)->nullable();
        });

        Schema::create('convocatoria_vistas', function (Blueprint $table): void {
            $table->foreignId('convocatoria_id')->constrained('convocatorias_seace')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestampTz('vista_at');
            $table->primary(['convocatoria_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('convocatoria_vistas');
        Schema::table('convocatorias_seace', function (Blueprint $t): void {
            $t->dropConstrainedForeignId('linea_id');
            $t->dropConstrainedForeignId('responsable_user_id');
            $t->dropColumn(['etiquetas', 'es_traida', 'fuera_por']);
        });
        Schema::dropIfExists('seace_lineas');
    }
};
