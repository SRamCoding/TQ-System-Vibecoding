<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Ubigeos;

use App\Modules\Nucleo\Excepciones\PadronUbigeosNoDisponible;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as Http;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Str;
use Psr\Http\Message\ResponseInterface;

/**
 * La lista de distritos del INEI (códigos que usa SUNAT; los del RENIEC son otros). Vive como CSV en
 * el repositorio para que pruebas, CI e instalaciones nuevas no dependan de internet; el comando
 * `nucleo:actualizar-csv-ubigeos` la descarga, la compara y, si alguien confirma, reemplaza ese archivo.
 */
final class PadronUbigeos
{
    /** Primeras columnas del Excel del INEI; las demás (capital legal…) se ignoran. */
    public const array ENCABEZADO_INEI = ['IDDIST', 'NOMBDEP', 'NOMBPROV', 'NOMBDIST'];

    private const array ENCABEZADO_CSV = ['codigo', 'departamento', 'provincia', 'distrito'];

    /** El archivo real pesa unos 90 KB: más de 10 MB es otra cosa (dirección equivocada). */
    public const int MAXIMO_BYTES = 10 * 1024 * 1024;

    public function __construct(
        private readonly Http $http,
        private readonly LectorXlsx $lector,
        private readonly string $rutaCsv,
        private readonly int $minimoDistritos,
    ) {}

    public function rutaCsv(): string
    {
        return $this->rutaCsv;
    }

    /**
     * @return list<DistritoUbigeo>
     */
    public function descargar(string $url): array
    {
        $demasiadoGrande = false;

        try {
            // El portal de datos abiertos rechaza (418) los clientes que no parecen navegador. Tiempos por
            // debajo del límite del servidor web, para avisar antes de que la página se corte.
            $respuesta = $this->http->withUserAgent('Mozilla/5.0 (compatible; TechqukERP/1.0)')
                ->connectTimeout(10)
                ->timeout(30)
                ->withOptions(['on_headers' => function (ResponseInterface $cabecera) use (&$demasiadoGrande): void {
                    // Corta antes de recibir el cuerpo si el servidor anuncia un archivo enorme.
                    if ((int) $cabecera->getHeaderLine('Content-Length') > self::MAXIMO_BYTES) {
                        $demasiadoGrande = true;

                        throw new PadronUbigeosNoDisponible('Archivo demasiado grande.');
                    }
                }])
                ->get($url);
        } catch (ConnectionException|RequestException) {
            throw $demasiadoGrande
                ? self::demasiadoGrande()
                : new PadronUbigeosNoDisponible('El portal de datos abiertos no respondió a tiempo. Intenta más tarde.');
        }

        if (! $respuesta->successful()) {
            throw new PadronUbigeosNoDisponible("La descarga de ubigeos falló (HTTP {$respuesta->status()}). Revisa la dirección en config/nucleo.php.");
        }

        // Si el servidor no anunció el tamaño, se revisa lo recibido antes de abrirlo.
        if ((int) $respuesta->header('Content-Length') > self::MAXIMO_BYTES || strlen($respuesta->body()) > self::MAXIMO_BYTES) {
            throw self::demasiadoGrande();
        }

        // ZipArchive solo abre archivos: la descarga pasa por uno con nombre aleatorio y se borra.
        $temporal = storage_path('app/ubigeos-'.Str::random(16).'.xlsx');

        try {
            if (file_put_contents($temporal, $respuesta->body()) === false) {
                throw new PadronUbigeosNoDisponible('No se pudo guardar la descarga en storage/app.');
            }

            return $this->desdeFilasInei($this->lector->filas($temporal));
        } finally {
            @unlink($temporal);
        }
    }

    private static function demasiadoGrande(): PadronUbigeosNoDisponible
    {
        return new PadronUbigeosNoDisponible('El archivo descargado es demasiado grande (más de 10 MB) para ser la lista de ubigeos. Revisa la dirección en config/nucleo.php.');
    }

    /**
     * @param  list<list<string>>  $filas  la hoja tal como viene, con su encabezado
     * @return list<DistritoUbigeo>
     */
    public function desdeFilasInei(array $filas): array
    {
        $encabezado = array_map('mb_strtoupper', array_slice(array_shift($filas) ?? [], 0, 4));

        if ($encabezado !== self::ENCABEZADO_INEI) {
            throw new PadronUbigeosNoDisponible('El archivo cambió de formato: se esperaban las columnas '.implode(', ', self::ENCABEZADO_INEI).'.');
        }

        return $this->validar(array_map(
            fn (array $fila): DistritoUbigeo => new DistritoUbigeo(...array_pad(array_slice($fila, 0, 4), 4, '')),
            $filas,
        ));
    }

    /**
     * @return list<DistritoUbigeo>
     */
    public function leerGuardado(): array
    {
        if (! is_file($this->rutaCsv)) {
            return [];
        }

        $archivo = fopen($this->rutaCsv, 'r');

        if ($archivo === false) {
            throw new PadronUbigeosNoDisponible("No se pudo leer {$this->rutaCsv}.");
        }

        $distritos = [];
        fgetcsv($archivo, escape: '');

        while (($fila = fgetcsv($archivo, escape: '')) !== false) {
            $distritos[] = new DistritoUbigeo(...array_map('strval', array_pad($fila, 4, '')));
        }

        fclose($archivo);

        return $distritos;
    }

    /**
     * @param  list<DistritoUbigeo>  $distritos
     * @param  array<string, mixed>  $fuente  de dónde y cuándo se descargó
     */
    public function guardar(array $distritos, array $fuente): void
    {
        $distritos = $this->validar($distritos);
        usort($distritos, fn (DistritoUbigeo $a, DistritoUbigeo $b): int => strcmp($a->codigo, $b->codigo));

        $archivo = fopen($this->rutaCsv, 'w');

        if ($archivo === false) {
            throw new PadronUbigeosNoDisponible("No se pudo escribir {$this->rutaCsv}.");
        }

        fputcsv($archivo, self::ENCABEZADO_CSV, escape: '');

        foreach ($distritos as $d) {
            fputcsv($archivo, [$d->codigo, $d->departamento, $d->provincia, $d->distrito], escape: '');
        }

        fclose($archivo);

        file_put_contents(
            preg_replace('/\.csv$/', '.fuente.json', $this->rutaCsv) ?? $this->rutaCsv.'.fuente.json',
            json_encode([...$fuente, 'distritos' => count($distritos)], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n",
        );
    }

    /**
     * @param  list<DistritoUbigeo>  $guardados
     * @param  list<DistritoUbigeo>  $descargados
     */
    public function comparar(array $guardados, array $descargados): DiferenciaUbigeos
    {
        $antes = $this->porCodigo($guardados);
        $ahora = $this->porCodigo($descargados);
        $renombrados = [];

        foreach (array_intersect_key($antes, $ahora) as $codigo => $distrito) {
            if (! $distrito->mismoNombre($ahora[$codigo])) {
                $renombrados[] = ['antes' => $distrito, 'ahora' => $ahora[$codigo]];
            }
        }

        return new DiferenciaUbigeos(
            array_values(array_diff_key($ahora, $antes)),
            $renombrados,
            array_values(array_diff_key($antes, $ahora)),
        );
    }

    /**
     * @param  list<DistritoUbigeo>  $distritos
     * @return list<DistritoUbigeo>
     */
    private function validar(array $distritos): array
    {
        $vistos = [];

        foreach ($distritos as $d) {
            if (preg_match('/^\d{6}$/', $d->codigo) !== 1) {
                throw new PadronUbigeosNoDisponible("Código de ubigeo inválido: «{$d->codigo}» (deben ser seis dígitos).");
            }

            if (isset($vistos[$d->codigo])) {
                throw new PadronUbigeosNoDisponible("Código de ubigeo repetido: {$d->codigo}.");
            }

            foreach ([$d->departamento, $d->provincia, $d->distrito] as $nombre) {
                if ($nombre === '' || mb_strlen($nombre) > 60) {
                    throw new PadronUbigeosNoDisponible("Nombre vacío o de más de 60 caracteres en el ubigeo {$d->codigo}.");
                }
            }

            $vistos[$d->codigo] = true;
        }

        if (count($distritos) < $this->minimoDistritos) {
            throw new PadronUbigeosNoDisponible(sprintf('La lista trae %d distritos y se esperan al menos %d: la descarga parece incompleta.', count($distritos), $this->minimoDistritos));
        }

        return $distritos;
    }

    /**
     * @param  list<DistritoUbigeo>  $distritos
     * @return array<string, DistritoUbigeo>
     */
    private function porCodigo(array $distritos): array
    {
        $indice = [];

        foreach ($distritos as $d) {
            $indice[$d->codigo] = $d;
        }

        return $indice;
    }
}
