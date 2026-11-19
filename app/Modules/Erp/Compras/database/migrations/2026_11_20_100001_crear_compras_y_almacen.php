<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * MVP rápido (ficha t3-compras): el flujo 2 (de la necesidad al costo): tipos de compra (configuración), almacenes y
 * stock, requerimientos, cuadros comparativos, órdenes de compra y de servicio y sus recepciones. Los montos de cada
 * línea viven en `imputaciones` (origen: la línea de la orden o la del requerimiento que se despacha). Estados como
 * texto (atajo). El almacén vive en este módulo (atajo: en el repo original es su propio módulo).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tipos_compra', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo', 30)->unique();
            $table->string('nombre', 80);
            $table->string('ejemplos', 200)->nullable();
            $table->string('atencion', 20); // recepcion | conformidad
            $table->boolean('es_bien')->default(false);
            $table->string('comportamiento', 200)->nullable();
            $table->boolean('es_activo')->default(true);
            $table->smallInteger('orden');
        });

        Schema::create('almacenes', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo', 20)->unique();
            $table->string('nombre', 120);
            $table->string('tipo', 20); // central | obra
            $table->foreignId('proyecto_id')->nullable()->constrained('proyectos');
            $table->timestamps();
        });

        Schema::create('stock', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('almacen_id')->constrained('almacenes');
            $table->foreignId('producto_id')->constrained('productos');
            $table->decimal('cantidad', 14, 3)->default(0);
            $table->decimal('reservado', 14, 3)->default(0);
            $table->decimal('costo_promedio', 14, 4)->default(0);
            $table->timestamps();
            $table->unique(['almacen_id', 'producto_id']);
        });

        Schema::create('movimientos_almacen', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('almacen_id')->constrained('almacenes');
            $table->foreignId('producto_id')->constrained('productos');
            $table->string('tipo', 20); // ingreso | salida | reserva | liberacion
            $table->decimal('cantidad', 14, 3);
            $table->decimal('costo_unitario', 14, 4);
            $table->string('documento', 60);
            $table->foreignId('proyecto_id')->nullable()->constrained('proyectos');
            $table->date('fecha');
            $table->auditoria();
        });

        Schema::create('requerimientos', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo', 30)->unique();
            $table->foreignId('proyecto_id')->index()->constrained('proyectos');
            $table->foreignId('solicitante_user_id')->constrained('users');
            $table->string('descripcion', 200);
            $table->date('fecha_necesidad')->nullable();
            $table->string('estado', 20)->index(); // borrador | por_aprobar | aprobado | rechazado | atendido
            $table->string('motivo_exceso', 300)->nullable();
            $table->string('comentario_rechazo', 300)->nullable();
            $table->foreignId('aprobado_por')->nullable()->constrained('users');
            $table->timestampTz('aprobado_at')->nullable();
            $table->auditoria();
        });

        Schema::create('requerimiento_lineas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('requerimiento_id')->index()->constrained('requerimientos')->cascadeOnDelete();
            $table->foreignId('tipo_compra_id')->constrained('tipos_compra');
            $table->foreignId('producto_id')->nullable()->constrained('productos');
            $table->string('descripcion', 250);
            $table->foreignId('unidad_medida_id')->constrained('unidades_medida');
            $table->decimal('cantidad', 14, 3);
            $table->decimal('precio_estimado', 14, 4);
            $table->foreignId('partida_control_id')->nullable()->constrained('partidas_control');
            $table->foreignId('motivo_id')->nullable()->constrained('motivos');
            $table->string('atencion', 20)->nullable(); // compra | despacho
            $table->string('estado', 20)->default('pendiente'); // pendiente | en_cuadro | ordenada | reservada | despachada
            $table->smallInteger('orden');
            $table->timestamps();
        });

        Schema::create('cuadros_comparativos', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo', 30)->unique();
            $table->foreignId('requerimiento_id')->index()->constrained('requerimientos');
            $table->string('estado', 20); // abierto | adjudicado
            $table->unsignedBigInteger('cotizacion_elegida_id')->nullable();
            $table->string('motivo', 500)->nullable();
            $table->auditoria();
        });

        Schema::create('cuadro_cotizaciones', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cuadro_id')->index()->constrained('cuadros_comparativos')->cascadeOnDelete();
            $table->foreignId('proveedor_party_id')->constrained('parties');
            $table->smallInteger('plazo_dias');
            $table->string('forma_pago', 80);
            $table->decimal('evaluacion', 3, 1)->nullable();
            $table->timestamps();
        });

        Schema::create('cuadro_precios', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cotizacion_id')->index()->constrained('cuadro_cotizaciones')->cascadeOnDelete();
            $table->foreignId('requerimiento_linea_id')->constrained('requerimiento_lineas');
            $table->decimal('precio_unitario', 14, 4);
        });

        Schema::create('ordenes', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo', 30)->unique();
            $table->string('clase', 10); // compra | servicio
            $table->foreignId('tipo_compra_id')->constrained('tipos_compra');
            $table->foreignId('proveedor_party_id')->nullable()->constrained('parties');
            $table->string('proveedor_nombre', 200);
            $table->foreignId('requerimiento_id')->nullable()->constrained('requerimientos');
            $table->foreignId('cuadro_id')->nullable()->constrained('cuadros_comparativos');
            $table->foreignId('proyecto_id')->nullable()->index()->constrained('proyectos');
            $table->string('estado', 20)->index(); // borrador | aprobada | recibida_parcial | recibida | anulada
            $table->date('fecha');
            $table->date('entrega_pactada')->nullable();
            $table->string('lugar_entrega', 150)->nullable();
            $table->string('forma_pago', 80)->nullable();
            $table->string('motivo_exceso', 300)->nullable();
            $table->string('seguimiento', 300)->nullable();
            $table->foreignId('aprobado_por')->nullable()->constrained('users');
            $table->timestampTz('aprobado_at')->nullable();
            $table->auditoria();
        });

        Schema::create('orden_lineas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('orden_id')->index()->constrained('ordenes')->cascadeOnDelete();
            $table->foreignId('requerimiento_linea_id')->nullable()->constrained('requerimiento_lineas');
            $table->foreignId('producto_id')->nullable()->constrained('productos');
            $table->string('descripcion', 250);
            $table->foreignId('unidad_medida_id')->constrained('unidades_medida');
            $table->decimal('cantidad', 14, 3);
            $table->decimal('precio_unitario', 14, 4);
            $table->decimal('subtotal', 14, 2);
            $table->foreignId('partida_control_id')->nullable()->constrained('partidas_control');
            $table->foreignId('centro_costo_id')->nullable()->constrained('centros_costo');
            $table->foreignId('motivo_id')->nullable()->constrained('motivos');
            $table->string('comentario', 300)->nullable();
            $table->decimal('cantidad_recibida', 14, 3)->default(0);
            $table->timestamps();
        });

        Schema::create('recepciones', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo', 30)->unique();
            $table->foreignId('orden_id')->index()->constrained('ordenes');
            $table->date('fecha');
            $table->string('guia', 60)->nullable();
            $table->string('lugar', 20); // obra | almacen
            $table->string('observacion', 300)->nullable();
            $table->auditoria();
        });

        Schema::create('recepcion_lineas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('recepcion_id')->index()->constrained('recepciones')->cascadeOnDelete();
            $table->foreignId('orden_linea_id')->constrained('orden_lineas');
            $table->decimal('cantidad', 14, 3);
        });

        // Los doce tipos del v4 (flujo 2): son configuración, no código; agregar otro es agregar una fila.
        $tipos = [
            ['bien_proyecto', 'Bien para proyecto', 'Cámaras, cable, postes', 'recepcion', true, 'Recepción, stock, series, despacho a obra'],
            ['importacion', 'Importación', 'Switches del exterior', 'recepcion', true, 'Lo anterior más embarque, gastos y prorrateo'],
            ['servicio_puntual', 'Servicio puntual', 'Transporte, grúa, instalación tercerizada', 'conformidad', false, 'Conformidad del servicio en lugar de recepción'],
            ['subcontrato', 'Subcontrato', 'Obra civil, tendido de fibra', 'conformidad', false, 'Avance valorizado, retenciones'],
            ['servicio_vigencia', 'Servicio con vigencia', 'SCTR, pólizas, alquileres, licencias', 'conformidad', false, 'Vigencia, órdenes periódicas, alertas de vencimiento'],
            ['garantia_financiera', 'Garantía financiera', 'Comisión de carta fianza', 'conformidad', false, 'Enlace a la garantía del contrato'],
            ['viaticos', 'Viáticos y caja chica', 'Pasajes y comida en campo', 'conformidad', false, 'Entrega de fondos y rendición con comprobantes'],
            ['bien_empresa', 'Bien para la empresa', 'Laptop, camioneta', 'recepcion', true, 'Alta como activo fijo (fase posterior)'],
            ['alquiler_equipo', 'Alquiler de equipo', 'Grúa, andamios, camioneta por días', 'conformidad', false, 'Fechas de uso y devolución'],
            ['profesionales', 'Servicios profesionales', 'Consultores, cuarta categoría', 'conformidad', false, 'Retención de cuarta categoría cuando aplica'],
            ['software', 'Software y licencias', 'Licencias, suscripciones en la nube', 'conformidad', false, 'Vigencia, usuarios o equipos asignados'],
            ['combustible', 'Combustible y peajes', 'Movilidad propia a obra', 'conformidad', false, 'Vale por vehículo y proyecto; se puede rendir por foto'],
        ];

        foreach ($tipos as $i => [$codigo, $nombre, $ejemplos, $atencion, $esBien, $comportamiento]) {
            DB::table('tipos_compra')->insert(['codigo' => $codigo, 'nombre' => $nombre, 'ejemplos' => $ejemplos, 'atencion' => $atencion, 'es_bien' => $esBien, 'comportamiento' => $comportamiento, 'es_activo' => true, 'orden' => $i + 1]);
        }

        DB::table('almacenes')->insert(['codigo' => 'AC', 'nombre' => 'Almacén Central', 'tipo' => 'central', 'created_at' => now()]);
    }

    public function down(): void
    {
        foreach (['recepcion_lineas', 'recepciones', 'orden_lineas', 'ordenes', 'cuadro_precios', 'cuadro_cotizaciones', 'cuadros_comparativos', 'requerimiento_lineas', 'requerimientos', 'movimientos_almacen', 'stock', 'almacenes', 'tipos_compra'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
