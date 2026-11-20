<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Modules\Nucleo\Tiempo\Calendario;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/** MV-040: tickets de ayuda de la demo, en varios procesos, estados y esperas, para ver los filtros llenos. */
final class TicketsAyudaSeeder extends Seeder
{
    public function run(): void
    {
        if (DB::table('tickets_ayuda')->count() > 2) {
            return;
        }

        $hoy = CarbonImmutable::parse(Calendario::hoy())->setTime(9, 0);
        $gente = DB::table('users')->where('es_sistema', false)->orderBy('id')->pluck('id')->all();
        $lista = [
            ['/flujo-caja', 'El flujo de caja de diciembre no suma la factura de la Municipalidad', 'sin_atender', 9],
            ['/ordenes/1', 'No me deja recibir la orden: dice que la cantidad supera lo pedido', 'sin_atender', 4],
            ['/tablero-pagos', 'Programé un pago para el viernes y no aparece en la columna', 'en_curso', 3],
            ['/oportunidades', 'La oportunidad de la Universidad no sale en «Mías»', 'sin_atender', 1],
            ['/planilla', 'La boleta de noviembre muestra el aporte a la AFP equivocada', 'en_curso', 6],
            ['/herramientas', 'Quiero registrar una herramienta sin número de serie', 'resuelto', 12],
            ['/cotizaciones/1', 'No encuentro cómo duplicar una cotización emitida', 'resuelto', 15],
            ['/postventa', 'El SLA del ticket de garantía vence pero el equipo ya se cambió', 'sin_atender', 0],
            ['/personas/7', 'No puedo agregar un segundo contacto a la empresa', 'resuelto', 2],
            ['/configuracion/estados', 'Cambié el color de un estado y no se ve en la lista', 'en_curso', 8],
        ];
        $n = (int) DB::table('tickets_ayuda')->count();

        foreach ($lista as $i => [$pantalla, $resumen, $estado, $dias]) {
            $n++;
            $creado = $hoy->subDays($dias)->addMinutes($i * 37);
            DB::table('tickets_ayuda')->insert([
                'codigo' => sprintf('TK-2026-%04d', $n), 'user_id' => $gente[$i % count($gente)], 'pantalla' => $pantalla, 'resumen' => $resumen,
                'conversacion' => "Persona: {$resumen}.\nn_n: Lo anoto para soporte con la pantalla donde estabas. ¿Lo confirmas?\nPersona: Sí.",
                'estado' => $estado, 'created_at' => $creado, 'updated_at' => $estado === 'resuelto' ? $creado->addDays(1) : $creado,
            ]);
        }
    }
}
