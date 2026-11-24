<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Observación 9.3: el catálogo de canales por los que llega un lead, con los de siempre (configuración, no demo). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('canales_lead', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo', 20)->unique();
            $table->string('nombre', 60);
            $table->string('icono', 20);
            $table->boolean('es_activo')->default(true);
            $table->integer('version')->default(1);
            $table->auditoria();
        });

        $autor = DB::table('users')->orderBy('id')->value('id');
        $canales = [
            'sitio_web' => 'Página web', 'whatsapp' => 'WhatsApp', 'instagram' => 'Instagram', 'facebook' => 'Facebook',
            'tiktok' => 'TikTok', 'linkedin' => 'LinkedIn', 'referido' => 'Referido', 'feria' => 'Feria o evento',
            'llamada' => 'Llamada', 'correo' => 'Correo', 'otro' => 'Otro',
        ];

        if ($autor === null) {
            return;
        }

        foreach ($canales as $codigo => $nombre) {
            DB::table('canales_lead')->insert(['codigo' => $codigo, 'nombre' => $nombre, 'icono' => $codigo, 'created_by' => $autor, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('canales_lead');
    }
};
