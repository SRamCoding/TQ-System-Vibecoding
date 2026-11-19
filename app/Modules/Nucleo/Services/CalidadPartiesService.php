<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Services;

use App\Modules\Nucleo\Models\AuditoriaCambio;
use App\Modules\Nucleo\Models\Party;
use App\Modules\Nucleo\Parties\PosiblesDuplicados;
use App\Modules\Nucleo\Parties\ReglasFichaCompleta;
use App\Modules\Nucleo\Tiempo\Calendario;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * Lectura de la calidad de las fichas para la portada de Personas y empresas (plan 1.4m, D2 y D3): cuáles están
 * incompletas y por qué, y cuáles podrían estar duplicadas. Solo lee; no cambia nada. Mira solo fichas activas.
 */
final class CalidadPartiesService
{
    /**
     * Restringe una consulta de fichas a las incompletas por cualquier regla.
     *
     * @param  Builder<Party>  $consulta
     */
    public function soloIncompletas(Builder $consulta, bool $conDatosBancarios): void
    {
        $hoy = Calendario::hoy();

        $consulta->where('es_activo', true)->where(function (Builder $alguna) use ($hoy, $conDatosBancarios): void {
            foreach (ReglasFichaCompleta::todas($conDatosBancarios) as [, $regla]) {
                $alguna->orWhere(fn (Builder $q) => $regla($q, $hoy));
            }
        });
    }

    /**
     * Cuántas fichas incumplen cada regla (solo las que tienen alguna), con su texto para el aviso. La de la cuenta
     * bancaria, solo con `$conDatosBancarios` (ReglasFichaCompleta).
     *
     * @return array<string, array{texto: string, cantidad: int}>
     */
    public function resumenIncompletas(bool $conDatosBancarios): array
    {
        $hoy = Calendario::hoy();
        $resumen = [];

        foreach (ReglasFichaCompleta::todas($conDatosBancarios) as $clave => [$texto, $regla]) {
            $cantidad = Party::query()->where('es_activo', true)->where(fn (Builder $q) => $regla($q, $hoy))->count();

            if ($cantidad > 0) {
                $resumen[$clave] = ['texto' => $texto, 'cantidad' => $cantidad];
            }
        }

        return $resumen;
    }

    public function cuantasIncompletas(bool $conDatosBancarios): int
    {
        $consulta = Party::query();
        $this->soloIncompletas($consulta, $conDatosBancarios);

        return $consulta->count();
    }

    /**
     * Los últimos cambios de fichas en la bitácora (plan 1.4m, D4): quién, qué hizo, a qué ficha y cuándo; el enlace lo
     * pone la pantalla. Solo la ficha misma (no sus contactos ni cuentas: las cuentas no las ve cualquiera).
     *
     * @return list<array{quien: string, accion: string, ficha: string, party_id: ?int, cuando: CarbonImmutable}>
     */
    public function ultimosCambios(int $cuantos = 10): array
    {
        $cambios = AuditoriaCambio::query()
            ->where('auditable_type', (new Party)->getMorphClass())
            ->with('usuario:id,name')
            ->orderByDesc('fecha')->orderByDesc('id')
            ->limit($cuantos)
            ->get();
        $existentes = Party::query()->whereKey($cambios->pluck('auditable_id'))->pluck('razon_social', 'id');

        return $cambios->map(fn (AuditoriaCambio $c): array => [
            'quien' => $c->usuario->name ?? 'Alguien',
            'accion' => $c->evento->etiqueta(),
            'ficha' => (string) ($existentes[$c->auditable_id] ?? $c->valores_anteriores['razon_social'] ?? $c->valores_nuevos['razon_social'] ?? 'una ficha'),
            'party_id' => isset($existentes[$c->auditable_id]) ? $c->auditable_id : null,
            'cuando' => $c->fecha,
        ])->values()->all();
    }

    /**
     * Los ids de las fichas activas con una posible pareja duplicada.
     *
     * @return list<int>
     */
    public function idsPosiblesDuplicados(): array
    {
        // Una sola vez por petición: la pantalla y el filtro de la tabla lo piden juntos.
        return once(fn (): array => $this->calcularPosiblesDuplicados());
    }

    /** @return list<int> */
    private function calcularPosiblesDuplicados(): array
    {
        $fichas = Party::query()->where('es_activo', true)->with('tipoDocumento:id,codigo')
            ->get(['id', 'razon_social', 'tipo_documento_id', 'numero_documento'])
            ->map(fn (Party $p): array => [
                'id' => $p->id,
                'nombre' => $p->razon_social,
                'tipo_documento' => (string) $p->tipoDocumento->codigo,
                'numero' => $p->numero_documento,
            ]);

        return PosiblesDuplicados::ids($fichas);
    }
}
