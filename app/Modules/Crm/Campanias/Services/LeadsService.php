<?php

declare(strict_types=1);

namespace App\Modules\Crm\Campanias\Services;

use App\Modules\Crm\Campanias\Models\Lead;
use App\Modules\Crm\Cuentas\Services\CuentasService;
use App\Modules\Crm\Cuentas\Services\DatosCuenta;
use App\Modules\Crm\Oportunidades\Services\DatosOportunidad;
use App\Modules\Crm\Oportunidades\Services\OportunidadesService;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Tiempo\Calendario;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * MVP rápido (ficha t1-campanias): los leads se convierten solo después de revisar coincidencias (v4 paso 6): primero
 * por RUC, después por nombre parecido. «Crear una cuenta nueva de todas formas» existe, pero es la última opción. Al
 * convertir, el origen del lead pasa tal cual a la cuenta y a la oportunidad (plan 2.20a, N3).
 */
final readonly class LeadsService
{
    public const string PERMISO_VER = 'crm.campanias.ver';

    public const string PERMISO_GESTIONAR = 'crm.campanias.gestionar';

    /** Días desde los que un lead nuevo cuenta como «sin atender». */
    public const int DIAS_SIN_ATENDER = 3;

    /**
     * Las cuentas con las que podría ser la misma organización: por RUC (exacta) y por nombre parecido.
     *
     * @return list<array{cuenta_id: int, etiqueta: string, por: string}>
     */
    public function coincidencias(Lead $lead): array
    {
        $cuentas = app(CuentasService::class);
        $resultado = [];

        if ($lead->ruc !== null && trim($lead->ruc) !== '') {
            try {
                $r = $cuentas->revisarRuc($lead->ruc);

                if ($r->cuenta !== null) {
                    $resultado[$r->cuenta->id] = ['cuenta_id' => $r->cuenta->id, 'etiqueta' => $r->cuenta->etiqueta, 'por' => 'coincide RUC'];
                }
            } catch (Throwable) {
                // RUC mal escrito: no hay coincidencia por RUC.
            }
        }

        foreach ($cuentas->parecidosPorNombre((string) $lead->organizacion) as $c) {
            $cuenta = $cuentas->deParty($c->party->id);

            if ($cuenta !== null && ! isset($resultado[$cuenta->id])) {
                $resultado[$cuenta->id] = ['cuenta_id' => $cuenta->id, 'etiqueta' => $cuenta->etiqueta, 'por' => $c->mismoNombre ? 'mismo nombre' : 'coincide nombre'];
            }
        }

        return array_values($resultado);
    }

    /**
     * Observación 7.1: los leads abiertos son tarjetas «Por calificar» en la lista de Oportunidades. Lo que tiene cada
     * uno, ya listo para dibujar (sin monto: un lead aún no lo tiene).
     *
     * @return list<array{id: int, nombre: string, organizacion: ?string, origen: string, campania: ?string, mensaje: ?string, telefono: ?string, desde: CarbonImmutable, dias: int, sinAtender: bool, asignado: ?int}>
     */
    public function porCalificar(): array
    {
        $hoy = Calendario::ahora();
        $origenes = DB::table('origenes_comerciales')->pluck('nombre', 'id');

        return Lead::query()->with('campania:id,nombre')->whereIn('estado', ['nuevo', 'contactado'])->orderBy('created_at')->get()
            ->map(fn (Lead $l): array => [
                'id' => $l->id, 'nombre' => $l->nombre_contacto, 'organizacion' => $l->organizacion,
                'origen' => (string) ($origenes[$l->origen_comercial_id] ?? 'Lead'), 'campania' => $l->campania?->nombre,
                'mensaje' => $l->mensaje, 'telefono' => $l->telefono, 'desde' => CarbonImmutable::instance($l->created_at),
                'dias' => (int) $l->created_at->diffInDays($hoy), 'sinAtender' => $l->estado === 'nuevo' && $l->created_at->lt($hoy->subDays(self::DIAS_SIN_ATENDER)),
                'asignado' => $l->asignado_user_id,
            ])->values()->all();
    }

    public function contactar(int $leadId, User $quien): void
    {
        Lead::query()->whereKey($leadId)->where('estado', 'nuevo')->update(['estado' => 'contactado', 'atendido_at' => Calendario::ahora(), 'asignado_user_id' => $quien->id]);
    }

    public function descartar(int $leadId, string $motivo): void
    {
        if (trim($motivo) === '') {
            throw new RuntimeException('Di por qué se descarta.');
        }

        Lead::query()->whereKey($leadId)->whereIn('estado', ['nuevo', 'contactado'])->update(['estado' => 'descartado', 'motivo_descarte' => trim($motivo), 'atendido_at' => DB::raw('COALESCE(atendido_at, now())')]);
    }

    /**
     * Convierte: con una cuenta existente (de las coincidencias o elegida) o creando una nueva con su RUC; después nace
     * la oportunidad en prospecto, con el origen del lead.
     */
    public function convertir(int $leadId, ?int $cuentaId, ?string $rucNuevo, string $nombreOportunidad, string $monto, int $lineaSolucionId, CarbonImmutable $cierre, User $quien): Lead
    {
        return DB::transaction(function () use ($leadId, $cuentaId, $rucNuevo, $nombreOportunidad, $monto, $lineaSolucionId, $cierre, $quien): Lead {
            $lead = Lead::query()->lockForUpdate()->findOrFail($leadId);

            if (! in_array($lead->estado, ['nuevo', 'contactado'], true)) {
                throw new RuntimeException('Ese lead ya se convirtió o se descartó.');
            }

            if ($cuentaId === null) {
                if ($rucNuevo === null || trim($rucNuevo) === '') {
                    throw new RuntimeException('Elige una cuenta existente o escribe el RUC para crear una nueva.');
                }

                $cuentaId = app(CuentasService::class)->crear(new DatosCuenta(
                    trim($rucNuevo), $quien->id, $lead->origen_comercial_id, razonSocial: $lead->organizacion ?? $lead->nombre_contacto,
                ))->cuenta->id;
            }

            $oportunidad = app(OportunidadesService::class)->crear($cuentaId, new DatosOportunidad(
                $nombreOportunidad, $lead->mensaje, $monto, (int) DB::table('monedas')->where('codigo', 'PEN')->value('id'), $cierre, $lineaSolucionId,
                $lead->origen_comercial_id, $quien->id,
            ), [], $quien);

            $lead->update(['estado' => 'convertido', 'cuenta_id' => $cuentaId, 'oportunidad_id' => $oportunidad->id, 'asignado_user_id' => $quien->id, 'atendido_at' => $lead->atendido_at ?? Calendario::ahora()]);

            return $lead->refresh();
        });
    }

    /**
     * Las cifras de una campaña: leads, convertidos, tasa, oportunidades y su monto, y costo por lead.
     *
     * @return array{leads: int, convertidos: int, tasa: string, monto: string, costo_por_lead: ?string}
     */
    public function cifras(int $campaniaId): array
    {
        $leads = Lead::query()->where('campania_id', $campaniaId)->count();
        $convertidos = Lead::query()->where('campania_id', $campaniaId)->where('estado', 'convertido')->count();
        $monto = (string) (DB::table('leads')->join('oportunidades', 'oportunidades.id', '=', 'leads.oportunidad_id')->where('leads.campania_id', $campaniaId)->sum('oportunidades.monto_estimado') ?: '0');
        $presupuesto = DB::table('campanias')->where('id', $campaniaId)->value('presupuesto');

        return [
            'leads' => $leads, 'convertidos' => $convertidos,
            'tasa' => $leads === 0 ? '0.0' : bcmul(bcdiv((string) $convertidos, (string) $leads, 4), '100', 1),
            'monto' => bcadd($monto, '0', 2),
            'costo_por_lead' => $presupuesto === null || $leads === 0 ? null : bcdiv((string) $presupuesto, (string) $leads, 2),
        ];
    }
}
