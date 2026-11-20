<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** MV-040: el estado «abierto» del ticket pasa a llamarse «sin atender» (llegó y nadie lo tomó aún). */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('tickets_ayuda')->where('estado', 'abierto')->update(['estado' => 'sin_atender']);
        Schema::table('tickets_ayuda', fn (Blueprint $t) => $t->string('estado', 20)->default('sin_atender')->change());
    }

    public function down(): void
    {
        DB::table('tickets_ayuda')->where('estado', 'sin_atender')->update(['estado' => 'abierto']);
        Schema::table('tickets_ayuda', fn (Blueprint $t) => $t->string('estado', 20)->default('abierto')->change());
    }
};
