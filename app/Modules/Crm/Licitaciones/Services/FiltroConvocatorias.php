<?php

declare(strict_types=1);

namespace App\Modules\Crm\Licitaciones\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Observaciones 7.4 y 7.5: decide si una convocatoria guardada entra a la bandeja y en qué línea de negocio. Cada línea
 * tiene sus palabras clave, sus palabras negativas (si aparece una, esa línea no la toma aunque coincida otra palabra) y
 * su valor mínimo. Entra con la línea que más palabras coincide sin negativas y sobre su mínimo; o si alguien la trajo
 * igual. Se aplica al llegar (reacción «Filtrar») y, si cambian las líneas, otra vez sobre todo lo guardado.
 */
final readonly class FiltroConvocatorias
{
    private const string CLAVE_FIRMA = 'crm.seace.firma_filtro';

    /**
     * Las líneas de negocio de la búsqueda, en orden.
     *
     * @return list<array{id: int, nombre: string, palabras: list<string>, negativas: list<string>, minimo: string}>
     */
    public function lineas(): array
    {
        return DB::table('seace_lineas')->orderBy('orden')->orderBy('id')->get()->map(fn (object $l): array => [
            'id' => (int) $l->id, 'nombre' => $l->nombre, 'palabras' => array_values((array) json_decode($l->palabras, true)),
            'negativas' => array_values((array) json_decode($l->negativas, true)), 'minimo' => (string) $l->valor_minimo,
        ])->all();
    }

    /**
     * La evaluación completa, línea por línea (para «¿Por qué no entró?»).
     *
     * @param  object{objeto: string, texto?: ?string, valor_referencial: mixed}  $c
     * @return array{entra: bool, linea: ?int, coincide: list<string>, motivo: ?string, detalle: list<array{linea: string, coincide: list<string>, negativas: list<string>, bajoMinimo: bool, minimo: string}>}
     */
    public function evaluar(object $c): array
    {
        $texto = self::normal((string) (($c->texto ?? null) ?: $c->objeto));
        $detalle = [];
        $mejor = null;

        foreach ($this->lineas() as $l) {
            $coincide = array_values(array_filter($l['palabras'], fn (string $p): bool => $p !== '' && str_contains($texto, self::normal($p))));
            $negativas = array_values(array_filter($l['negativas'], fn (string $p): bool => $p !== '' && str_contains($texto, self::normal($p))));
            $bajo = $c->valor_referencial !== null && bccomp((string) $c->valor_referencial, $l['minimo'], 2) < 0;
            $detalle[] = ['linea' => $l['nombre'], 'coincide' => $coincide, 'negativas' => $negativas, 'bajoMinimo' => $bajo, 'minimo' => $l['minimo']];

            if ($coincide !== [] && $negativas === [] && ! $bajo && ($mejor === null || count($coincide) > count($mejor[1]))) {
                $mejor = [$l['id'], $coincide];
            }
        }

        if ($mejor !== null) {
            return ['entra' => true, 'linea' => $mejor[0], 'coincide' => $mejor[1], 'motivo' => null, 'detalle' => $detalle];
        }

        $conPalabra = array_values(array_filter($detalle, fn (array $d): bool => $d['coincide'] !== []));
        $motivo = match (true) {
            $conPalabra === [] => 'No coincide con ninguna palabra clave',
            $conPalabra[0]['negativas'] !== [] => "La descartó la palabra negativa «{$conPalabra[0]['negativas'][0]}» ({$conPalabra[0]['linea']})",
            default => 'Valor referencial bajo el mínimo de '.$conPalabra[0]['linea'].' (S/ '.number_format((float) $conPalabra[0]['minimo'], 2).')',
        };
        $linea = $conPalabra === [] ? null : collect($this->lineas())->firstWhere('nombre', $conPalabra[0]['linea'])['id'] ?? null;

        return ['entra' => false, 'linea' => $linea, 'coincide' => $conPalabra[0]['coincide'] ?? [], 'motivo' => $motivo, 'detalle' => $detalle];
    }

    /** Aplica el filtro a una guardada y deja el resultado (las traídas a mano se quedan en la bandeja). */
    public function aplicar(int $id): bool
    {
        $c = DB::table('convocatorias_seace')->find($id);

        if ($c === null) {
            return false;
        }

        $r = $this->evaluar($c);
        $entra = $r['entra'] || (bool) $c->es_traida;
        DB::table('convocatorias_seace')->where('id', $id)->update([
            'en_bandeja' => $entra, 'linea_id' => $r['linea'], 'fuera_por' => $r['entra'] ? null : mb_substr((string) $r['motivo'], 0, 200),
            'coincide' => $r['coincide'] === [] ? null : mb_substr(implode(', ', $r['coincide']), 0, 200),
        ]);

        return $entra;
    }

    /** Vuelve a filtrar todo lo guardado (lo que antes no entraba aparece al instante). */
    public function reaplicar(): int
    {
        $n = 0;

        // Las guardadas antes de la observación 7.4 sin su texto completo se quedan como estaban.
        foreach (DB::table('convocatorias_seace')->whereNotNull('texto')->pluck('id') as $id) {
            $n += $this->aplicar((int) $id) ? 1 : 0;
        }

        Cache::forever(self::CLAVE_FIRMA, $this->firma());

        return $n;
    }

    /** Si las líneas cambiaron desde la última vez, vuelve a filtrar todo lo guardado. */
    public function reaplicarSiCambio(): int
    {
        return Cache::get(self::CLAVE_FIRMA) === $this->firma() ? 0 : $this->reaplicar();
    }

    /** Sin tildes y en minúsculas: «cámaras» coincide con «CAMARAS». */
    public static function normal(string $texto): string
    {
        return mb_strtolower(Str::ascii($texto));
    }

    private function firma(): string
    {
        return md5((string) json_encode($this->lineas()));
    }
}
