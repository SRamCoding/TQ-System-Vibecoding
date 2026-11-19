<?php

declare(strict_types=1);

namespace App\Modules\Crm\Licitaciones\Enums;

/** MVP rápido (t1-licitaciones): tipos de procedimiento como lista fija (el DBML los quiere en un catálogo). */
enum TipoProcedimiento: string
{
    case LicitacionPublica = 'LP';
    case ConcursoPublico = 'CP';
    case AdjudicacionSimplificada = 'AS';
    case SubastaInversa = 'SIE';
    case ComparacionPrecios = 'COMPRE';
    case ConvocatoriaPrivada = 'PRIVADA';

    public function etiqueta(): string
    {
        return match ($this) {
            self::LicitacionPublica => 'Licitación pública',
            self::ConcursoPublico => 'Concurso público',
            self::AdjudicacionSimplificada => 'Adjudicación simplificada',
            self::SubastaInversa => 'Subasta inversa electrónica',
            self::ComparacionPrecios => 'Comparación de precios',
            self::ConvocatoriaPrivada => 'Convocatoria privada',
        };
    }

    /** @return array<string, string> */
    public static function opciones(): array
    {
        return array_combine(array_map(fn (self $e): string => $e->value, self::cases()), array_map(fn (self $e): string => $e->etiqueta(), self::cases()));
    }
}
