<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Observación 9.6: el catálogo de rubros con sus palabras clave y negativas sugeridas (configuración, ampliable), el
 * historial de cambios de la búsqueda (quién, cuándo, qué) y las sugerencias de palabras por confirmar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seace_rubros', function (Blueprint $table): void {
            $table->id();
            $table->string('nombre', 80)->unique();
            $table->json('palabras');
            $table->json('negativas');
            $table->smallInteger('orden')->default(100);
            $table->timestamps();
        });

        Schema::create('seace_busqueda_cambios', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users');
            $table->string('texto', 400);
            $table->timestampTz('created_at')->useCurrent();
        });

        Schema::create('seace_sugerencias', function (Blueprint $table): void {
            $table->id();
            $table->string('palabra', 80);
            $table->string('rubro', 80);
            $table->string('motivo', 300);
            $table->string('estado', 12)->default('pendiente');
            $table->timestamps();
        });

        $rubros = [
            'Videovigilancia' => [['videovigilancia', 'cámaras', 'cámara', 'cctv', 'monitoreo'], ['cámara frigorífica', 'cámara de gas']],
            'Redes y cableado' => [['red de datos', 'cableado estructurado', 'fibra óptica', 'switch', 'wifi', 'inalámbric', 'enlaces'], ['red de agua', 'red de desagüe']],
            'Software' => [['software', 'sistema de información', 'plataforma web', 'aplicativo', 'telemetría'], ['software contable licencia']],
            'Control de acceso' => [['control de acceso', 'torniquete', 'biométric', 'lector de proximidad'], []],
            'Centro de datos' => [['centro de datos', 'data center', 'servidor', 'servidores', 'rack', 'almacenamiento'], []],
            'Energía y UPS' => [['ups', 'sistema de alimentación ininterrumpida', 'estabilizador', 'banco de baterías'], ['grupo electrógeno alquiler']],
            'Telecomunicaciones' => [['telecomunicaciones', 'radioenlace', 'antena', 'telefonía ip', 'central telefónica'], ['telefonía móvil']],
            'Alarmas e incendios' => [['alarma', 'detección de incendios', 'detector de humo', 'sistema contra incendio'], ['recarga de extintores']],
        ];
        $orden = 1;

        foreach ($rubros as $nombre => [$palabras, $negativas]) {
            DB::table('seace_rubros')->insert(['nombre' => $nombre, 'palabras' => json_encode($palabras, JSON_UNESCAPED_UNICODE), 'negativas' => json_encode($negativas, JSON_UNESCAPED_UNICODE),
                'orden' => $orden++, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('seace_sugerencias');
        Schema::dropIfExists('seace_busqueda_cambios');
        Schema::dropIfExists('seace_rubros');
    }
};
