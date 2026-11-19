<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Historial;

/**
 * Lo común de DescribibleEnBitacora: sin valor «—», sí/no y el resto como texto. Cada modelo agrega sus
 * etiquetas y traduce sus llaves foráneas y enums en valorLegibleBitacora().
 */
trait DescribeBitacora
{
    public function sujetoBitacora(): string
    {
        return 'Datos';
    }

    public function valorLegibleBitacora(string $campo, mixed $valor): string
    {
        return self::valorComun($valor);
    }

    /**
     * @param  array<string, mixed>  $valores
     */
    public function resumenCreacionBitacora(array $valores): ?string
    {
        return null;
    }

    protected static function valorComun(mixed $valor): string
    {
        return match (true) {
            $valor === null, $valor === '' => '—',
            is_bool($valor) => $valor ? 'Sí' : 'No',
            is_scalar($valor) => (string) $valor,
            default => (string) json_encode($valor, JSON_UNESCAPED_UNICODE),
        };
    }

    /** Una fecha guardada como «2026-03-31» (o con hora), mostrada como «31/03/2026». */
    protected static function fechaLegible(mixed $valor): string
    {
        return is_string($valor) && preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $valor, $m) === 1
            ? "{$m[3]}/{$m[2]}/{$m[1]}"
            : self::valorComun($valor);
    }
}
