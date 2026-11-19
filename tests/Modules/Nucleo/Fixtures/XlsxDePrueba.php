<?php

declare(strict_types=1);

namespace Tests\Modules\Nucleo\Fixtures;

use RuntimeException;
use ZipArchive;

/**
 * Arma un XLSX mínimo en memoria, como los que publica el INEI: la primera columna con textos
 * compartidos (sharedStrings) y las demás en línea, para cubrir las dos formas de guardar texto.
 */
final class XlsxDePrueba
{
    /**
     * @param  list<list<string>>  $filas
     */
    public static function contenido(array $filas): string
    {
        $ruta = tempnam(sys_get_temp_dir(), 'xlsx') ?: throw new RuntimeException('Sin archivo temporal.');
        $zip = new ZipArchive;
        $zip->open($ruta, ZipArchive::OVERWRITE);

        $compartidos = [];
        $xmlFilas = '';

        foreach ($filas as $i => $fila) {
            $celdas = '';

            foreach ($fila as $j => $valor) {
                $ref = chr(65 + $j).($i + 1);
                $texto = htmlspecialchars($valor, ENT_XML1);

                if ($j === 0) {
                    $compartidos[] = $texto;
                    $celdas .= "<c r=\"{$ref}\" t=\"s\"><v>".(count($compartidos) - 1).'</v></c>';
                } else {
                    $celdas .= "<c r=\"{$ref}\" t=\"inlineStr\"><is><t>{$texto}</t></is></c>";
                }
            }

            $xmlFilas .= '<row r="'.($i + 1)."\">{$celdas}</row>";
        }

        $zip->addFromString('xl/worksheets/sheet1.xml', '<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'.$xmlFilas.'</sheetData></worksheet>');
        $zip->addFromString('xl/sharedStrings.xml', '<?xml version="1.0" encoding="UTF-8"?><sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'.implode('', array_map(fn (string $t): string => "<si><t>{$t}</t></si>", $compartidos)).'</sst>');
        $zip->close();

        $contenido = (string) file_get_contents($ruta);
        unlink($ruta);

        return $contenido;
    }

    /**
     * Hoja con el encabezado del INEI y los distritos dados como [código, dep, prov, dist].
     *
     * @param  list<list<string>>  $distritos
     */
    public static function inei(array $distritos): string
    {
        return self::contenido([
            ['IDDIST', 'NOMBDEP', 'NOMBPROV', 'NOMBDIST', 'NOM_CAPITAL (LEGAL)'],
            ...array_map(fn (array $d): array => [...$d, $d[3]], $distritos),
        ]);
    }
}
