<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Services;

use App\Modules\Nucleo\Alertas\AlertasDelNucleo;
use App\Modules\Nucleo\Historico\ArchivoHistoricoService;
use App\Modules\Nucleo\Models\Adjunto;
use App\Modules\Nucleo\Tiempo\Calendario;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

/**
 * Los archivos huérfanos del disco de adjuntos (plan 1.12, D4; plan 1.8): los que quedan sin fila en `adjuntos` cuando
 * un módulo adjunta dentro de su transacción y la deshace. Nunca se borran de una vez: pasan a `cuarentena/AAAA-MM-DD/`
 * con su ruta original (para devolverlos, se mueven de vuelta) y, después de `adjuntos.dias_cuarentena` días, pasan al
 * archivo histórico (cifrados) y salen del disco de adjuntos.
 *
 * - Un archivo con menos de 24 horas no se toca: puede ser un adjunto que se está guardando.
 * - Si los huérfanos pasan `adjuntos.tope_huerfanos`, no mueve nada y avisa: es más probable un error o una
 *   restauración a medias que huérfanos de verdad.
 * - Con `$simular`, solo cuenta: no mueve, no borra, no avisa.
 */
final readonly class HuerfanosAdjuntosService
{
    public const string TAREA = 'nucleo.huerfanos-adjuntos';

    public const string CUARENTENA = 'cuarentena';

    public const int HORAS_GRACIA = 24;

    public function __construct(private ParametrosService $parametros) {}

    /**
     * Las alertas y el histórico llegan como parámetros (AGENTS.md: dependencias de un solo método). Sin alertas, una
     * corrida detenida no avisa; sin histórico, no borra nada de la cuarentena: así corre `--simular`.
     *
     * @return array{encontrados: int, movidos: int, borrados: int, bytes_borrados: int, detenida: bool, bytes_cuarentena: int, huerfanos: list<string>, por_borrar: list<string>}
     */
    public function revisar(bool $simular = false, ?ArchivoHistoricoService $historico = null, ?AlertasService $alertas = null): array
    {
        $huerfanos = $this->huerfanos();
        $porBorrar = $this->vencidosEnCuarentena();
        $detenida = count($huerfanos) > $this->parametros->entero('adjuntos.tope_huerfanos');
        $movidos = 0;
        $borrados = 0;
        $bytesBorrados = 0;

        if (! $simular) {
            if ($detenida) {
                $alertas?->emitir(AlertasDelNucleo::HUERFANOS_DEMASIADOS, Calendario::ahora()->toDateString(), ['cantidad' => count($huerfanos)]);
            } else {
                $carpeta = self::CUARENTENA.'/'.Calendario::ahora()->toDateString();

                foreach ($huerfanos as $ruta) {
                    $this->disco()->move($ruta, $carpeta.'/'.$ruta);
                    $movidos++;
                }
            }

            // Plan 1.4y, D6: lo que vence en la cuarentena no se pierde: pasa al archivo histórico, cifrado, y solo
            // después se borra del disco de adjuntos. Sin histórico (simular o una prueba), no se toca.
            foreach ($historico === null ? [] : $porBorrar as $ruta) {
                $bytesBorrados += $this->disco()->size($ruta);
                $historico->archivarArchivo('adjuntos-huerfanos', basename($ruta), (string) $this->disco()->get($ruta));
                $this->disco()->delete($ruta);
                $borrados++;
            }

            $this->borrarCarpetasVacias();
        }

        return [
            'encontrados' => count($huerfanos),
            'movidos' => $movidos,
            'borrados' => $borrados,
            'bytes_borrados' => $bytesBorrados,
            'detenida' => $detenida,
            'bytes_cuarentena' => $this->bytesEnCuarentena(),
            'huerfanos' => $huerfanos,
            'por_borrar' => $porBorrar,
        ];
    }

    /**
     * Revisión de arquitectura de la 1.12: el disco de adjuntos también lo usan otros servicios, sin fila en `adjuntos`
     * (las fotos de perfil: `FotoPerfilService`, ruta en `users.foto_ruta`; las de los proyectos, plan 2.61:
     * `FotoProyectoService`, en `proyectos.foto_ruta`; las de las oportunidades, plan 2.21d: `FotoOportunidadService`,
     * en `oportunidades.foto_ruta`). Su carpeta no se revisa nunca, ni la cuarentena.
     * Un servicio nuevo que guarde en este disco sin fila en `adjuntos` tiene que sumar aquí su carpeta.
     */
    public const array CARPETAS_DE_OTROS = [self::CUARENTENA, 'fotos-perfil', 'fotos-proyecto', 'fotos-oportunidad'];

    public static function esDeOtroServicio(string $ruta): bool
    {
        foreach (self::CARPETAS_DE_OTROS as $carpeta) {
            if (str_starts_with($ruta, $carpeta.'/')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Los archivos fuera de la cuarentena y de las carpetas de otros servicios, con más de 24 horas y sin fila en
     * `adjuntos` para ese disco y esa ruta.
     *
     * @return list<string>
     */
    public function huerfanos(): array
    {
        $limite = Calendario::ahora()->subHours(self::HORAS_GRACIA)->getTimestamp();
        $candidatos = array_values(array_filter(
            $this->disco()->allFiles(),
            fn (string $ruta): bool => ! self::esDeOtroServicio($ruta) && $this->disco()->lastModified($ruta) < $limite,
        ));
        $huerfanos = [];

        foreach (array_chunk($candidatos, 500) as $lote) {
            $conFila = Adjunto::query()->where('disco', AdjuntosService::DISCO)->whereIn('ruta', $lote)->pluck('ruta')->all();
            array_push($huerfanos, ...array_values(array_diff($lote, $conFila)));
        }

        sort($huerfanos);

        return $huerfanos;
    }

    /**
     * Los archivos de las carpetas de cuarentena con más de `adjuntos.dias_cuarentena` días.
     *
     * @return list<string>
     */
    public function vencidosEnCuarentena(): array
    {
        $limite = Calendario::ahora()->startOfDay()->subDays($this->parametros->entero('adjuntos.dias_cuarentena'));
        $vencidos = [];

        foreach ($this->disco()->directories(self::CUARENTENA) as $carpeta) {
            $fecha = CarbonImmutable::createFromFormat('!Y-m-d', basename($carpeta), 'America/Lima') ?: null;

            if ($fecha !== null && $fecha->lt($limite)) {
                array_push($vencidos, ...$this->disco()->allFiles($carpeta));
            }
        }

        sort($vencidos);

        return $vencidos;
    }

    public function bytesEnCuarentena(): int
    {
        return array_sum(array_map(fn (string $ruta): int => $this->disco()->size($ruta), $this->disco()->allFiles(self::CUARENTENA)));
    }

    private function borrarCarpetasVacias(): void
    {
        foreach ($this->disco()->directories(self::CUARENTENA) as $carpeta) {
            if ($this->disco()->allFiles($carpeta) === []) {
                $this->disco()->deleteDirectory($carpeta);
            }
        }
    }

    private function disco(): Filesystem
    {
        return Storage::disk(AdjuntosService::DISCO);
    }
}
