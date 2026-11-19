<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Saludo\Piezas;

use App\Modules\Nucleo\Enums\MomentoDia;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * Las piezas de los dibujos de cada fecha y estación (plan 1.4e2) y el dibujo del día. Agregar una pieza es agregar su
 * vista y una línea en `PiezasDeLaFecha`: las combinaciones crecen solas. Un tema sin piezas sigue con sus variantes de
 * `RegistroAmbientaciones`.
 *
 * El dibujo del día es el mismo para todos (D4) y nunca el mismo dos días seguidos: un contador continuo de días (no se
 * reinicia al cambiar de año) avanza por las combinaciones con un paso que no comparte divisores con su cantidad, así
 * las recorre todas antes de repetir.
 */
final class RegistroPiezasDibujo
{
    /** @var array<string, array<string, array<string, PiezaDibujo>>> tema => tipo => clave => pieza */
    private array $piezas = [];

    /** @var array<string, list<CombinacionDibujo>> */
    private array $combinaciones = [];

    public function registrar(string $tema, PiezaDibujo ...$piezas): void
    {
        foreach ($piezas as $pieza) {
            if (isset($this->piezas[$tema][$pieza->tipo->value][$pieza->clave])) {
                throw new InvalidArgumentException("La pieza «{$pieza->clave}» de {$tema} ya está registrada.");
            }

            $this->piezas[$tema][$pieza->tipo->value][$pieza->clave] = $pieza;
        }

        unset($this->combinaciones[$tema]);
    }

    public function tienePiezas(string $tema): bool
    {
        return $this->combinaciones($tema) !== [];
    }

    /**
     * Todas las combinaciones con sentido del tema, en un orden estable: cada fondo con cada motivo cuyas exigencias
     * cumple, sin acompañante y con cada acompañante cuyas exigencias cumplen el fondo y el motivo.
     *
     * @return list<CombinacionDibujo>
     */
    public function combinaciones(string $tema): array
    {
        if (isset($this->combinaciones[$tema])) {
            return $this->combinaciones[$tema];
        }

        $de = fn (TipoPiezaDibujo $tipo): array => array_values($this->piezas[$tema][$tipo->value] ?? []);
        $todas = [];

        foreach ($de(TipoPiezaDibujo::Fondo) as $fondo) {
            foreach ($de(TipoPiezaDibujo::Motivo) as $motivo) {
                // Un motivo también puede exigir algo del fondo (una tabla de surf, el mar).
                if (array_diff($motivo->requiere, $fondo->etiquetas) !== []) {
                    continue;
                }

                $todas[] = new CombinacionDibujo($fondo, $motivo, null);
                $hay = [...$fondo->etiquetas, ...$motivo->etiquetas];

                foreach ($de(TipoPiezaDibujo::Acompanante) as $acompanante) {
                    if (array_diff($acompanante->requiere, $hay) === []) {
                        $todas[] = new CombinacionDibujo($fondo, $motivo, $acompanante);
                    }
                }
            }
        }

        return $this->combinaciones[$tema] = $todas;
    }

    /** El dibujo de ese día para ese tema, o null si no tiene piezas. */
    public function delDia(string $tema, CarbonImmutable $dia): ?CombinacionDibujo
    {
        $todas = $this->combinaciones($tema);
        $cantidad = count($todas);

        if ($cantidad === 0) {
            return null;
        }

        $numeroDia = intdiv($dia->startOfDay()->getTimestamp() + $dia->getOffset(), 86400);
        $semilla = crc32("dibujo|{$tema}");

        return $todas[($semilla + $numeroDia * self::paso($cantidad, $semilla)) % $cantidad];
    }

    /** La luz del dibujo: la del momento del día del saludo (la madrugada es noche). */
    public static function luz(CarbonImmutable $ahora): MomentoDia
    {
        return MomentoDia::deLaHora($ahora->hour);
    }

    /** Un paso entre 1 y n-1 sin divisores comunes con n: recorre todas y nunca repite dos días seguidos. */
    private static function paso(int $cantidad, int $semilla): int
    {
        if ($cantidad < 2) {
            return 0;
        }

        $paso = 1 + $semilla % ($cantidad - 1);

        while (self::mcd($paso, $cantidad) !== 1) {
            $paso = $paso % ($cantidad - 1) + 1;
        }

        return $paso;
    }

    private static function mcd(int $a, int $b): int
    {
        return $b === 0 ? $a : self::mcd($b, $a % $b);
    }
}
