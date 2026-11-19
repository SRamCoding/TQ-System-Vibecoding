<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * M6 · Numeración (plan 1.6). Un contador por tipo de documento, serie, ámbito y periodo; la fila se crea al tomar el
 * primer número y se bloquea dentro de la transacción que guarda el documento (D1: sin huecos ni duplicados).
 * Desviaciones del DBML, anotadas en el plan:
 * - Sin `tipo_documento_id`: ese catálogo es el de SUNAT (factura, boleta); REQ, OC, ING… se identifican por su
 *   `documento_type` (alias del morph map), como los estados.
 * - `ambito` de texto en vez de `proyecto_id` y `contrato_id` (D4): vacío = toda la empresa; si no, el código del
 *   proyecto o contrato, que también va dentro del número (`VAL-CT0003-2026-0001`) para que el número siga siendo único.
 *   Listo para la etapa 2 sin migración nueva.
 * - `periodo` de texto en vez de `anio` (D3): `2026` reinicia cada año, `2026-10` cada mes (AS-AAAA-MM-NNNN) y vacío
 *   no reinicia. Así la unicidad no depende de nulos.
 * - Sin `es_activa` ni `digitos`: las series las declara cada módulo en código (D5), con sus dígitos; una copia aquí
 *   podría contradecirlo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('series_documento', function (Blueprint $table): void {
            $table->id();
            $table->string('documento_type', 120);
            $table->string('serie', 10);
            $table->string('ambito', 60)->default('');
            $table->string('periodo', 7)->default('');
            $table->integer('ultimo_numero');
            $table->auditoria();

            $table->unique(['documento_type', 'serie', 'ambito', 'periodo']);
        });

        DB::statement("ALTER TABLE series_documento ADD CONSTRAINT series_documento_serie_check CHECK (serie ~ '^[A-Z][A-Z0-9]{0,9}$')");
        DB::statement("ALTER TABLE series_documento ADD CONSTRAINT series_documento_periodo_check CHECK (periodo ~ '^([0-9]{4}(-(0[1-9]|1[0-2]))?)?$')");
        DB::statement('ALTER TABLE series_documento ADD CONSTRAINT series_documento_ultimo_numero_check CHECK (ultimo_numero >= 1)');
        DB::statement("ALTER TABLE series_documento ADD CONSTRAINT series_documento_ambito_check CHECK (ambito ~ '^([A-Z0-9][A-Z0-9.]*)?$')");
    }

    public function down(): void
    {
        Schema::dropIfExists('series_documento');
    }
};
