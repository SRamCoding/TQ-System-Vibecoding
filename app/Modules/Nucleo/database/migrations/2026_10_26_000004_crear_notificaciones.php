<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * M8 · Notificaciones (plan 1.8). Cada aviso emitido a una persona: la campana del panel lo lee de aquí (con la forma
 * de las notificaciones de Laravel: `notifiable_*`, `data`, `read_at`) y el correo marca aquí cuándo salió. La clave
 * única por persona hace que un reintento no repita el aviso ni el correo. Desviaciones del DBML, anotadas en el plan:
 * `data` (título, texto, enlace, como los dibuja la campana) en vez de `asunto` y `cuerpo` sueltos; `type` es la clave
 * del aviso declarado; sin plantilla (las plantillas editables llegan con las alertas, 1.10).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notificaciones', function (Blueprint $table): void {
            $table->id();
            $table->string('type', 80);
            $table->string('notifiable_type', 120);
            $table->unsignedBigInteger('notifiable_id');
            $table->jsonb('data');
            $table->timestampTz('read_at')->nullable();
            $table->string('clave_unica', 150);
            $table->boolean('enviar_correo')->default(false);
            // Plan 1.8: el correo se reserva en una transacción corta, se envía fuera de toda transacción y se confirma en
            // otra (`codigo.md`, efectos externos). Una reserva vencida es de un intento que murió: se retoma.
            $table->timestampTz('correo_reservado_hasta')->nullable();
            $table->timestampTz('correo_enviado_at')->nullable();
            $table->auditoria();

            $table->index(['notifiable_type', 'notifiable_id', 'read_at']);
            // Un aviso (su tipo) por hecho y por persona: otro aviso del mismo hecho es otra fila.
            $table->unique(['notifiable_type', 'notifiable_id', 'type', 'clave_unica']);
        });

        DB::statement("ALTER TABLE notificaciones ADD CONSTRAINT notificaciones_textos_check CHECK (btrim(type) <> '' AND btrim(clave_unica) <> '')");
        DB::statement('ALTER TABLE notificaciones ADD CONSTRAINT notificaciones_correo_check CHECK ((correo_enviado_at IS NULL AND correo_reservado_hasta IS NULL) OR enviar_correo)');
    }

    public function down(): void
    {
        Schema::dropIfExists('notificaciones');
    }
};
