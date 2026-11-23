<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * MVP rápido (observación 7.6, ficha t1-campanias): campañas con su retorno y campañas a los propios clientes. Se agrega
 * (sin reconstruir):
 * - campanias: el enlace público del formulario (token), su responsable y, en las de tipo «Clientes», el grupo del que
 *   nacieron y su seguimiento (pasos con su día, canal y plantilla).
 * - campania_gastos: los gastos reales de la campaña (gastos directos del centro de costo Comercial).
 * - campania_destinatarios: los clientes a los que va una campaña a clientes, con su proyecto y el dato que los puso ahí.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campanias', function (Blueprint $table): void {
            $table->string('token', 40)->nullable()->unique();
            $table->foreignId('responsable_user_id')->nullable()->constrained('users');
            $table->string('grupo', 30)->nullable();
            $table->json('seguimiento')->nullable();
        });

        Schema::create('campania_gastos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('campania_id')->index()->constrained('campanias')->cascadeOnDelete();
            $table->foreignId('gasto_id')->unique()->constrained('gastos_demo');
            $table->timestamps();
        });

        Schema::create('campania_destinatarios', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('campania_id')->index()->constrained('campanias')->cascadeOnDelete();
            $table->foreignId('cuenta_id')->constrained('cuentas');
            $table->foreignId('proyecto_id')->nullable()->constrained('proyectos');
            $table->string('dato', 200)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campania_destinatarios');
        Schema::dropIfExists('campania_gastos');
        Schema::table('campanias', function (Blueprint $t): void {
            $t->dropConstrainedForeignId('responsable_user_id');
            $t->dropColumn(['token', 'grupo', 'seguimiento']);
        });
    }
};
