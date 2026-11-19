<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Ia;

/**
 * La cita que respalda un campo, comparada con el texto del documento sin exigir que sea idéntica (plan 1.11, D6):
 * espacios y saltos de línea, guiones de corte de palabra, guion blando, ligaduras y mayúsculas no cuentan.
 */
final class CitaIa
{
    private const array LIGADURAS = ['ﬀ' => 'ff', 'ﬁ' => 'fi', 'ﬂ' => 'fl', 'ﬃ' => 'ffi', 'ﬄ' => 'ffl', 'ﬅ' => 'st', 'ﬆ' => 'st'];

    public static function normalizar(string $texto): string
    {
        $texto = strtr($texto, self::LIGADURAS);
        $texto = str_replace("\u{00AD}", '', $texto);
        // «presu-\npuesto» → «presupuesto»: el guion al final de una línea entre dos letras corta la palabra.
        $texto = (string) preg_replace('/(\p{L})[-\x{2010}\x{2011}]\h*\R\s*(\p{L})/u', '$1$2', $texto);
        $texto = mb_strtolower($texto);

        return trim((string) preg_replace('/\s+/u', ' ', $texto));
    }

    public static function estaEn(string $cita, string $texto): bool
    {
        $cita = self::normalizar($cita);

        return $cita !== '' && str_contains(self::normalizar($texto), $cita);
    }
}
