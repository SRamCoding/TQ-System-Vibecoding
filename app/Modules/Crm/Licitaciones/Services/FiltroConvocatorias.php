<?php

declare(strict_types=1);

namespace App\Modules\Crm\Licitaciones\Services;

use App\Modules\Nucleo\Services\ParametrosService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Observación 7.4: decide si una convocatoria guardada entra a la bandeja (coincide con alguna palabra clave y pasa el
 * valor mínimo). Se aplica al llegar (reacción «Filtrar») y, si cambian las palabras o el mínimo, otra vez sobre todo lo
 * guardado: lo que antes no entraba aparece al instante.
 */
final readonly class FiltroConvocatorias
{
    private const string CLAVE_FIRMA = 'crm.seace.firma_filtro';

    /**
     * @param  object{objeto: string, texto?: ?string, valor_referencial: mixed}  $c
     * @return array{entra: bool, coincide: list<string>, motivo: ?string}
     */
    public function evaluar(object $c): array
    {
        $texto = mb_strtolower((string) ($c->texto ?? null ?: $c->objeto));
        $coinciden = array_values(array_filter(app(ConvocatoriasSeaceService::class)->palabras(), fn (string $p): bool => str_contains($texto, $p)));
        $minimo = (float) app(ParametrosService::class)->entero(ConvocatoriasSeaceService::PARAMETRO_VALOR_MINIMO);

        return match (true) {
            $coinciden === [] => ['entra' => false, 'coincide' => [], 'motivo' => 'No coincide con ninguna palabra clave'],
            $c->valor_referencial !== null && (float) $c->valor_referencial < $minimo => ['entra' => false, 'coincide' => $coinciden, 'motivo' => 'Valor referencial bajo el mínimo (S/ '.number_format($minimo, 2).')'],
            default => ['entra' => true, 'coincide' => $coinciden, 'motivo' => null],
        };
    }

    /** Aplica el filtro a una guardada y deja el resultado. */
    public function aplicar(int $id): bool
    {
        $c = DB::table('convocatorias_seace')->find($id);

        if ($c === null) {
            return false;
        }

        $r = $this->evaluar($c);
        DB::table('convocatorias_seace')->where('id', $id)->update(['en_bandeja' => $r['entra'], 'coincide' => $r['coincide'] === [] ? null : mb_substr(implode(', ', $r['coincide']), 0, 200)]);

        return $r['entra'];
    }

    /** Si las palabras o el mínimo cambiaron desde la última vez, vuelve a filtrar todo lo guardado. */
    public function reaplicarSiCambio(): int
    {
        $firma = md5(implode('|', app(ConvocatoriasSeaceService::class)->palabras()).'#'.app(ParametrosService::class)->entero(ConvocatoriasSeaceService::PARAMETRO_VALOR_MINIMO));

        if (Cache::get(self::CLAVE_FIRMA) === $firma) {
            return 0;
        }

        $n = 0;

        // Las guardadas antes de la observación 7.4 no tienen su texto completo: se quedan como estaban.
        foreach (DB::table('convocatorias_seace')->whereNotNull('texto')->pluck('id') as $id) {
            $n += $this->aplicar((int) $id) ? 1 : 0;
        }

        Cache::forever(self::CLAVE_FIRMA, $firma);

        return $n;
    }
}
