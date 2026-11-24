<?php

declare(strict_types=1);

namespace App\Modules\Crm\Oportunidades\Services;

use App\Modules\Crm\Actividades\Services\ActividadesService;
use App\Modules\Crm\Cuentas\Services\CuentasService;
use App\Modules\Crm\Oportunidades\Models\Oportunidad;
use App\Modules\Nucleo\Ia\ClienteAnthropic;
use App\Modules\Nucleo\Ia\PedidoModeloIa;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Services\PartiesService;
use App\Modules\Nucleo\Tiempo\Calendario;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Observación 9.5 (8 y 10), lienzo `oportunidades-mejoras/Ficha.dc.html`: en la ficha de la oportunidad, quién decide
 * (las personas del cliente con su papel y su postura) y el resumen con el siguiente paso sugerido. La IA se llama solo
 * desde la pantalla y si está configurada; en la demo, el resumen ya viene sugerido.
 */
final readonly class FichaComercialService
{
    public const array PAPELES = ['decide' => 'Decide', 'influye' => 'Influye', 'usa' => 'Usa', 'bloquea' => 'Bloquea'];

    public const array POSTURAS = ['a_favor' => 'a favor', 'neutral' => 'neutral', 'en_contra' => 'en contra'];

    /** @return list<array{id: int, nombre: string, cargo: ?string, papel: string, postura: string, nota: ?string, iniciales: string}> */
    public function decisores(int $oportunidadId): array
    {
        return DB::table('oportunidad_decisores as d')->leftJoin('party_contactos as c', 'c.id', '=', 'd.contacto_id')
            ->where('d.oportunidad_id', $oportunidadId)->orderByRaw("array_position(ARRAY['decide','influye','usa','bloquea']::varchar[], d.papel)")->orderBy('d.id')
            ->get(['d.id', 'd.papel', 'd.postura', 'd.nota', 'd.nombre', 'd.cargo', 'c.nombres', 'c.apellidos', 'c.cargo as cargo_contacto'])
            ->map(function (object $d): array {
                $nombre = $d->nombres !== null ? trim("{$d->nombres} {$d->apellidos}") : (string) $d->nombre;
                $partes = preg_split('/\s+/', $nombre) ?: [];

                return ['id' => (int) $d->id, 'nombre' => $nombre, 'cargo' => $d->cargo_contacto ?? $d->cargo, 'papel' => $d->papel, 'postura' => $d->postura, 'nota' => $d->nota,
                    'iniciales' => mb_strtoupper(mb_substr($partes[0] ?? '', 0, 1).mb_substr($partes[1] ?? '', 0, 1))];
            })->values()->all();
    }

    /** @return array<int, string> los contactos vigentes del cliente de la oportunidad */
    public function contactosDelCliente(int $oportunidadId): array
    {
        $cuenta = (int) Oportunidad::query()->whereKey($oportunidadId)->value('cuenta_id');
        $party = app(CuentasService::class)->resumen($cuenta)->partyId;

        return collect(app(PartiesService::class)->contactosVigentes($party))->mapWithKeys(fn ($c): array => [$c->id => $c->etiqueta])->all();
    }

    public function guardarDecisor(int $oportunidadId, ?int $contactoId, ?string $nombre, ?string $cargo, string $papel, string $postura, ?string $nota): void
    {
        if (! isset(self::PAPELES[$papel]) || ! isset(self::POSTURAS[$postura])) {
            throw new RuntimeException('Elige su papel y su postura.');
        }

        if ($contactoId === null && trim((string) $nombre) === '') {
            throw new RuntimeException('Elige un contacto del cliente o escribe el nombre de la persona.');
        }

        DB::table('oportunidad_decisores')->insert([
            'oportunidad_id' => $oportunidadId, 'contacto_id' => $contactoId, 'nombre' => $contactoId === null ? mb_substr(trim((string) $nombre), 0, 150) : null,
            'cargo' => $contactoId === null && trim((string) $cargo) !== '' ? mb_substr(trim((string) $cargo), 0, 120) : null,
            'papel' => $papel, 'postura' => $postura, 'nota' => trim((string) $nota) === '' ? null : mb_substr(trim((string) $nota), 0, 300),
            'created_by' => auth()->id() ?? 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function quitarDecisor(int $id): void
    {
        DB::table('oportunidad_decisores')->where('id', $id)->delete();
    }

    /** @return array{texto: ?string, paso: ?string, cuando: ?string} */
    public function resumen(int $oportunidadId): array
    {
        $o = DB::table('oportunidades')->where('id', $oportunidadId)->first(['resumen_ia', 'siguiente_paso_ia', 'resumen_ia_at']);

        return ['texto' => $o?->resumen_ia, 'paso' => $o?->siguiente_paso_ia,
            'cuando' => $o?->resumen_ia_at === null ? null : CarbonImmutable::parse($o->resumen_ia_at)->locale('es')->diffForHumans()];
    }

    public function iaDisponible(): bool
    {
        return (string) (config('nucleo.ia.claves')['IA_CLAVE_ANTHROPIC'] ?? '') !== '';
    }

    /** «Volver a sugerir»: llama a la IA con el historial del negocio (solo si está configurada). */
    public function sugerir(int $oportunidadId): void
    {
        $clave = (string) (config('nucleo.ia.claves')['IA_CLAVE_ANTHROPIC'] ?? '');

        if ($clave === '') {
            throw new RuntimeException('La IA no está configurada (falta IA_CLAVE_ANTHROPIC en el .env): el resumen se queda como está.');
        }

        $o = Oportunidad::query()->with('estado')->findOrFail($oportunidadId);
        $margen = app(PortadaOportunidades::class)->margenes([$o->id])[$o->id] ?? null;
        $actividades = DB::table('actividades')->where('oportunidad_id', $o->id)->orderByDesc('fecha')->limit(8)->get(['tipo', 'asunto', 'fecha', 'resultado', 'es_realizada'])
            ->map(fn (object $a): string => '- '.CarbonImmutable::parse($a->fecha)->format('d/m/Y')." {$a->tipo}: {$a->asunto}".($a->resultado ? " → {$a->resultado}" : '').($a->es_realizada ? '' : ' (pendiente)'))->implode("\n");
        $decisores = collect($this->decisores($o->id))->map(fn (array $d): string => "- {$d['nombre']} ({$d['cargo']}): ".self::PAPELES[$d['papel']].', '.self::POSTURAS[$d['postura']].($d['nota'] ? " — {$d['nota']}" : ''))->implode("\n");
        $contexto = "Oportunidad: {$o->nombre}\nEtapa: {$o->estado->nombre} desde ".CarbonImmutable::parse($o->en_etapa_desde)->format('d/m/Y')."\nMonto: S/ ".number_format((float) $o->monto_estimado, 2)
            .($margen && $margen['porcentaje'] !== null ? "\nMargen previsto: {$margen['porcentaje']} % (mínimo {$margen['minimo']} %)" : "\nSin cotización con costos")
            ."\nCierre estimado: ".CarbonImmutable::parse($o->fecha_cierre_estimada)->format('d/m/Y')."\nHoy: ".Calendario::hoy()
            ."\nActividades:\n".($actividades ?: '- ninguna')."\nQuién decide:\n".($decisores ?: '- sin registrar');

        $r = app(ClienteAnthropic::class)->llamar(new PedidoModeloIa(
            (string) config('nucleo.ia.modelo_asistente', 'claude-haiku-5-5'), 500,
            'Eres un asistente comercial de Techquk (Perú). Con el historial de un negocio, escribe en español del Perú un resumen corto (2 o 3 frases) de dónde está y un siguiente paso concreto (quién, qué y para cuándo). No inventes datos.',
            $contexto, [],
            ['type' => 'object', 'properties' => ['resumen' => ['type' => 'string'], 'siguiente_paso' => ['type' => 'string']], 'required' => ['resumen', 'siguiente_paso']], 40,
        ), $clave);

        DB::table('oportunidades')->where('id', $o->id)->update([
            'resumen_ia' => mb_substr((string) ($r->salida['resumen'] ?? ''), 0, 2000), 'siguiente_paso_ia' => mb_substr((string) ($r->salida['siguiente_paso'] ?? ''), 0, 300), 'resumen_ia_at' => now(),
        ]);
    }

    /** «Programar»: el siguiente paso sugerido pasa a ser una actividad pendiente (mañana a las 10:00), del responsable. */
    public function programarPaso(int $oportunidadId, User $quien): void
    {
        $o = Oportunidad::query()->findOrFail($oportunidadId);
        $paso = trim((string) $o->getAttribute('siguiente_paso_ia'));

        if ($paso === '') {
            throw new RuntimeException('No hay un siguiente paso sugerido.');
        }

        app(ActividadesService::class)->registrar('llamada', $o->cuenta_id, $o->id, mb_substr($paso, 0, 200),
            CarbonImmutable::parse(Calendario::hoy(), 'America/Lima')->addDay()->setTime(10, 0), null, null, null, $quien);
    }
}
