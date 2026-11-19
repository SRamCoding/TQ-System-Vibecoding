<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Ia;

use InvalidArgumentException;

/**
 * Los prompts versionados (plan 1.11, D10; ia.md): `<carpeta>/<proceso>/vN.md`, con la fecha y el motivo arriba,
 * entre `---`. El vigente es el de mayor N; cada llamada guarda cuál usó.
 *
 *     ---
 *     fecha: 2026-10-28
 *     motivo: Primera versión.
 *     ---
 *     Lee las bases…
 */
final readonly class PromptsIa
{
    public function __construct(private string $carpeta) {}

    public function vigente(string $proceso): PromptIa
    {
        $versiones = $this->versiones($proceso);

        if ($versiones === []) {
            throw new InvalidArgumentException("El proceso de IA «{$proceso}» no tiene prompt (falta {$proceso}/v1.md).");
        }

        return $this->leer($proceso, max($versiones));
    }

    /** @return list<int> */
    public function versiones(string $proceso): array
    {
        $versiones = [];

        foreach (glob("{$this->carpeta}/{$proceso}/v*.md") ?: [] as $archivo) {
            if (preg_match('/^v([1-9][0-9]*)\.md$/', basename($archivo), $partes) === 1) {
                $versiones[] = (int) $partes[1];
            }
        }

        sort($versiones);

        return $versiones;
    }

    public function leer(string $proceso, int $version): PromptIa
    {
        $archivo = "{$this->carpeta}/{$proceso}/v{$version}.md";
        $contenido = is_file($archivo) ? (string) file_get_contents($archivo) : '';

        if (preg_match('/\A---\R(.*?)\R---\R(.*)\z/s', $contenido, $partes) !== 1) {
            throw new InvalidArgumentException("El prompt {$proceso}/v{$version}.md no existe o no empieza con su fecha y motivo entre «---».");
        }

        $cabecera = [];

        foreach (preg_split('/\R/', $partes[1]) ?: [] as $linea) {
            if (preg_match('/^(fecha|motivo):\s*(.+)$/', trim($linea), $dato) === 1) {
                $cabecera[$dato[1]] = trim($dato[2]);
            }
        }

        $texto = trim($partes[2]);

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $cabecera['fecha'] ?? '') !== 1 || ($cabecera['motivo'] ?? '') === '' || $texto === '') {
            throw new InvalidArgumentException("El prompt {$proceso}/v{$version}.md necesita «fecha: AAAA-MM-DD», «motivo:» y su texto.");
        }

        return new PromptIa($version, $cabecera['fecha'], $cabecera['motivo'], $texto);
    }
}
