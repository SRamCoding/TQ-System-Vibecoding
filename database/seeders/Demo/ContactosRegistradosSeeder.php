<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Observación 9.9 (ficha t1-campanias, MV-103): campañas con mezcla de contactos nuevos y ya registrados. La feria tiene
 * muchos ya registrados (clientes que pasaron por el stand y contactos con oportunidad abierta, que no se duplican);
 * Facebook, algunos que ya habían escrito antes. Idempotente.
 */
final class ContactosRegistradosSeeder extends Seeder
{
    public function run(): void
    {
        if (DB::table('lead_llegadas')->exists()) {
            return;
        }

        $feria = DB::table('campanias')->where('tipo', 'feria')->first(['id', 'canal_lead_id', 'created_at']);
        $facebook = DB::table('campanias')->where('nombre', 'Facebook · colegios privados')->first(['id', 'canal_lead_id']);
        $cuentas = DB::table('cuentas as c')->join('parties as p', 'p.id', '=', 'c.party_id')->orderBy('c.id')->get(['c.id', 'p.razon_social']);

        if ($feria !== null && $cuentas->isNotEmpty()) {
            // Clientes que ya conocíamos: el lead queda unido a su cuenta.
            foreach (DB::table('leads')->where('campania_id', $feria->id)->whereNull('oportunidad_id')->orderBy('id')->limit(5)->pluck('id') as $i => $id) {
                $c = $cuentas[($i * 3 + 2) % $cuentas->count()];
                DB::table('leads')->where('id', $id)->update(['ya_registrado' => true, 'registrado_como' => 'cliente', 'cuenta_id' => $c->id, 'organizacion' => $c->razon_social]);
            }

            // Contactos con una oportunidad abierta: no se crea otra; queda anotado que llegaron por la feria.
            $abiertas = DB::table('oportunidades as o')->join('estados as e', 'e.id', '=', 'o.estado_id')->join('cuentas as c', 'c.id', '=', 'o.cuenta_id')->join('parties as p', 'p.id', '=', 'c.party_id')
                ->whereIn('e.codigo', ['calificada', 'propuesta_enviada', 'negociacion'])->orderBy('o.id')->limit(3)->get(['o.id', 'o.cuenta_id', 'p.razon_social']);

            foreach ($abiertas as $i => $o) {
                DB::table('lead_llegadas')->insert(['oportunidad_id' => $o->id, 'cuenta_id' => $o->cuenta_id, 'campania_id' => $feria->id, 'canal_lead_id' => $feria->canal_lead_id,
                    'nombre' => ['Mónica Paredes', 'Ricardo Alfaro', 'Teresa Huanca'][$i], 'organizacion' => $o->razon_social, 'registrado_como' => 'oportunidad', 'created_at' => now()->subDays(12 + $i)]);
            }
        }

        if ($facebook !== null) {
            DB::table('leads')->where('campania_id', $facebook->id)->whereNull('oportunidad_id')->where('ya_registrado', false)->orderByDesc('id')->limit(3)
                ->update(['ya_registrado' => true, 'registrado_como' => 'lead']);
        }
    }
}
