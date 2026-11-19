<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Ubigeos;

use App\Modules\Nucleo\Excepciones\PadronUbigeosNoDisponible;
use SimpleXMLElement;
use ZipArchive;

/**
 * Lee la primera hoja de un XLSX como filas de texto. Solo lo que piden las listas oficiales (texto y
 * números, sin fórmulas ni formatos): por eso no se agrega una dependencia de hojas de cálculo.
 */
final class LectorXlsx
{
    /**
     * @return list<list<string>>
     */
    public function filas(string $ruta): array
    {
        $zip = new ZipArchive;

        if ($zip->open($ruta) !== true) {
            throw new PadronUbigeosNoDisponible('El archivo descargado no es un Excel (XLSX) válido.');
        }

        try {
            $compartidos = $this->textosCompartidos($zip);
            $hoja = $zip->getFromName('xl/worksheets/sheet1.xml');

            if ($hoja === false) {
                throw new PadronUbigeosNoDisponible('El Excel no tiene hoja de datos.');
            }

            return $this->leerHoja(new SimpleXMLElement($hoja), $compartidos);
        } finally {
            $zip->close();
        }
    }

    /**
     * @return list<string>
     */
    private function textosCompartidos(ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');

        if ($xml === false) {
            return [];
        }

        $textos = [];

        foreach ((new SimpleXMLElement($xml))->si as $si) {
            $texto = (string) $si->t;

            foreach ($si->r as $tramo) {
                $texto .= (string) $tramo->t;
            }

            $textos[] = $texto;
        }

        return $textos;
    }

    /**
     * @param  list<string>  $compartidos
     * @return list<list<string>>
     */
    private function leerHoja(SimpleXMLElement $hoja, array $compartidos): array
    {
        $filas = [];

        foreach ($hoja->sheetData->row as $fila) {
            $celdas = [];

            foreach ($fila->c as $celda) {
                $valor = match ((string) $celda['t']) {
                    's' => $compartidos[(int) $celda->v] ?? '',
                    'inlineStr' => (string) $celda->is->t,
                    default => (string) $celda->v,
                };
                $celdas[$this->columna((string) $celda['r'])] = trim($valor);
            }

            if ($celdas === []) {
                continue;
            }

            $completa = [];

            for ($i = 0, $ultima = max(array_keys($celdas)); $i <= $ultima; $i++) {
                $completa[] = $celdas[$i] ?? '';
            }

            $filas[] = $completa;
        }

        return $filas;
    }

    /** «C12» → 2 (columna desde cero). */
    private function columna(string $referencia): int
    {
        preg_match('/^[A-Z]+/', $referencia, $letras);
        $numero = 0;

        foreach (str_split($letras[0] ?? 'A') as $letra) {
            $numero = $numero * 26 + (ord($letra) - 64);
        }

        return $numero - 1;
    }
}
