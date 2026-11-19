<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * created_by y updated_by de users (DBML v2). users es tabla de framework y ya tiene sus marcas de
 * tiempo, por eso no usa auditoria(). El primer usuario no tiene quién lo cree: el usuario de sistema
 * es su propio autor (una FK a la misma fila es válida en PostgreSQL), y las filas que ya existan
 * quedan a su nombre.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->foreignId('created_by')->nullable()->index()->constrained('users');
            $table->foreignId('updated_by')->nullable()->index()->constrained('users');
        });

        $sistema = DB::table('users')->where('es_sistema', true)->value('id')
            ?? throw new RuntimeException('Falta el usuario de sistema (migración 2026_10_10_000002).');
        DB::table('users')->update(['created_by' => $sistema]);

        Schema::table('users', function (Blueprint $table): void {
            $table->foreignId('created_by')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('updated_by');
            $table->dropConstrainedForeignId('created_by');
        });
    }
};
