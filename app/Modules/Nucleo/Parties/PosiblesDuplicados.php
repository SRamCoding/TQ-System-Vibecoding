<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Parties;

use App\Modules\Nucleo\Enums\Catalogo;
use Illuminate\Support\Str;

/**
 * Fichas que podrían ser la misma (plan 1.4m, D3): solo se avisan, no se unen (pendiente «DNI y RUC 10 de una persona»).
 * Dos fichas son sospechosas si su nombre normalizado coincide (sin acentos, mayúsculas, puntuación ni la forma
 * societaria) o si el DNI de una está dentro del RUC 10 de la otra (`10` + DNI + dígito).
 */
final class PosiblesDuplicados
{
    /** Formas societarias que no distinguen a una empresa de otra. */
    private const array FORMAS = ['sac', 'saa', 'sa', 'srl', 'eirl', 'sociedad anonima cerrada', 'sociedad anonima', 'sociedad comercial de responsabilidad limitada', 'empresa individual de responsabilidad limitada'];

    /**
     * Los ids que tienen al menos una pareja sospechosa.
     *
     * @param  iterable<array{id: int, nombre: string, tipo_documento: string, numero: string}>  $fichas
     * @return list<int>
     */
    public static function ids(iterable $fichas): array
    {
        $porNombre = [];
        $dnis = [];
        $rucs10 = [];

        foreach ($fichas as $f) {
            $porNombre[self::normalizar($f['nombre'])][] = $f['id'];

            if ($f['tipo_documento'] === Catalogo::DNI) {
                $dnis[$f['numero']][] = $f['id'];
            } elseif ($f['tipo_documento'] === Catalogo::RUC && str_starts_with($f['numero'], '10') && strlen($f['numero']) === 11) {
                $rucs10[substr($f['numero'], 2, 8)][] = $f['id'];
            }
        }

        $sospechosos = [];

        foreach ($porNombre as $nombre => $ids) {
            if ($nombre !== '' && count($ids) > 1) {
                array_push($sospechosos, ...$ids);
            }
        }

        foreach ($rucs10 as $dni => $ids) {
            if (isset($dnis[$dni])) {
                array_push($sospechosos, ...$ids, ...$dnis[$dni]);
            }
        }

        $unicos = array_values(array_unique($sospechosos));
        sort($unicos);

        return $unicos;
    }

    /**
     * «Ferretería El Norte S.A.C.» y «FERRETERIA EL NORTE SAC» dan lo mismo.
     *
     * Regla (plan 2.20a, N1): `parties.nombre_normalizado` y `nombre_comercial_normalizado` guardan lo que devuelve esta
     * función. Si cambia, se corre `php artisan nucleo:recalcular-nombres` en el mismo PR (con `--simular`, primero).
     */
    public static function normalizar(string $nombre): string
    {
        $texto = Str::lower(Str::ascii($nombre));
        // Puntos y comas se borran (S.A.C. → sac); los demás signos separan palabras (Hermanos-López → hermanos lopez).
        $texto = (string) preg_replace('/[^a-z0-9 ]+/', ' ', str_replace(['.', ','], '', $texto));
        $texto = trim((string) preg_replace('/\s+/', ' ', $texto));

        foreach (self::FORMAS as $forma) {
            $texto = trim((string) preg_replace('/\s'.preg_quote($forma, '/').'$/', '', ' '.$texto));
        }

        return $texto;
    }

    /** Para el nombre comercial: nulo o en blanco queda nulo. */
    public static function normalizarOpcional(?string $nombre): ?string
    {
        $normal = $nombre === null ? '' : self::normalizar($nombre);

        return $normal === '' ? null : $normal;
    }
}
