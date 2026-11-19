<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Historico;

use App\Modules\Nucleo\Alertas\AlertasDelNucleo;
use App\Modules\Nucleo\Services\AlertasService;
use App\Modules\Nucleo\Tiempo\Calendario;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * El archivo histórico (plan 1.4y, D6; decisión del desarrollador: «que se almacene como histórico todo»). Toda limpieza
 * pasa por aquí: las filas que salen de la base se escriben antes, por lotes, en un archivo comprimido y cifrado del
 * disco `historico` (`historico/<tabla>/<AAAA-MM>/…`), que nunca se borra ni se sobrescribe. Se borra solo lo escrito y
 * comprobado (se vuelve a leer, se descifra y se comparan los ids); si algo falla, no se borra nada de ese lote y se
 * avisa al administrador. Una fila que dejó de cumplir la condición entre la lectura y el borrado no se borra (queda una
 * copia de más en el histórico, nunca una de menos). Si falla un lote, los anteriores ya quedaron archivados y borrados.
 *
 * Se llama **fuera de toda transacción** (`codigo.md`: efectos externos): escribe en un disco que puede ser S3, y un
 * rollback dejaría archivos de filas que siguen en la base. No se comprueba con `transactionLevel()` porque las pruebas
 * corren dentro de una (RefreshDatabase). Hoy lo llaman solo comandos del programador.
 *
 * Punto de extensión pendiente (pendientes.md, «Plazo del archivo histórico», Asistencia Legal): si se fija un plazo,
 * su borrado va aquí, por carpeta `<tabla>/<AAAA-MM>`, nunca archivo por archivo.
 */
final readonly class ArchivoHistoricoService
{
    public const int LOTE = 1000;

    public function __construct(
        private CifradoHistorico $cifrado,
        private AlertasService $alertas,
    ) {}

    /**
     * Archiva y saca de la base las filas de `$consulta` (de la tabla `$tabla`, con columna `id`). Devuelve cuántas.
     *
     * @throws HistoricoFallido
     */
    public function archivar(string $tabla, Builder $consulta, int $lote = self::LOTE): int
    {
        $total = 0;

        try {
            while (($filas = (clone $consulta)->orderBy('id')->limit($lote)->get())->isNotEmpty()) {
                $ids = $filas->pluck('id')->map(fn (mixed $id): int => (int) $id)->all();
                $this->escribir($tabla, $filas->map(fn (object $fila): array => (array) $fila)->all(), $ids);
                $total += DB::transaction(fn (): int => (clone $consulta)->whereIn('id', $ids)->delete());

                if (count($ids) < $lote) {
                    break;
                }
            }
        } catch (Throwable $error) {
            // Sin el contenido de las filas ni el mensaje (regla 10): la tabla y la clase del error.
            Log::error('El archivo histórico falló; no se borró ese lote.', ['tabla' => $tabla, 'error' => $error::class]);

            // Si avisar falla, no tapa el error que lo causó.
            try {
                $this->alertas->emitir(AlertasDelNucleo::HISTORICO_FALLIDO, Calendario::ahora()->toDateString().':'.$tabla, ['tabla' => $tabla]);
            } catch (Throwable $aviso) {
                Log::error('No se pudo avisar que el archivo histórico falló.', ['tabla' => $tabla, 'error' => $aviso::class]);
            }

            throw $error;
        }

        return $total;
    }

    /**
     * Las filas de un archivo del histórico, descifradas (para `nucleo:leer-historico`).
     *
     * @param  ?string  $contrasena  la de antes de un cambio de `BACKUP_ARCHIVE_PASSWORD`; nula, la actual
     * @return list<array<string, mixed>>
     */
    public function leer(string $archivo, ?string $contrasena = null): array
    {
        $texto = gzdecode($this->cifrado->descifrar((string) $this->disco()->get($archivo), $contrasena));

        if ($texto === false) {
            throw HistoricoFallido::ilegible();
        }

        return array_values(array_map(
            fn (string $linea): array => (array) json_decode($linea, true, flags: JSON_THROW_ON_ERROR),
            array_filter(explode("\n", $texto), fn (string $linea): bool => $linea !== ''),
        ));
    }

    /**
     * Guarda un archivo entero en el histórico (plan 1.12: lo que vence en la cuarentena de huérfanos), comprimido y
     * cifrado, en `<categoria>/<AAAA-MM>/<fecha y hora>-<id único>-<nombre>.gz.cifrado`. Se relee y se compara antes de
     * devolver: quien lo llama borra el original solo después. Devuelve la ruta en el histórico.
     *
     * @throws HistoricoFallido
     */
    public function archivarArchivo(string $categoria, string $nombre, string $contenido): string
    {
        $ahora = Calendario::ahora();
        $limpio = preg_replace('/[^A-Za-z0-9._-]+/', '_', $nombre) ?? 'archivo';
        $archivo = sprintf('%s/%s/%s-%s-%s.gz.cifrado', $categoria, $ahora->format('Y-m'), $ahora->format('Ymd-His'), Str::uuid7(), $limpio);

        if ($this->disco()->exists($archivo)) {
            throw HistoricoFallido::noComprobado($archivo);
        }

        $this->disco()->put($archivo, $this->cifrado->cifrar((string) gzencode($contenido, 9)));

        if ($this->leerArchivo($archivo) !== $contenido) {
            throw HistoricoFallido::noComprobado($archivo);
        }

        return $archivo;
    }

    /** Un archivo guardado con `archivarArchivo()`, descifrado (para `nucleo:leer-historico --guardar`). */
    public function leerArchivo(string $archivo, ?string $contrasena = null): string
    {
        $contenido = gzdecode($this->cifrado->descifrar((string) $this->disco()->get($archivo), $contrasena));

        return $contenido === false ? throw HistoricoFallido::ilegible() : $contenido;
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @param  list<int>  $ids
     */
    private function escribir(string $tabla, array $filas, array $ids): void
    {
        $ahora = Calendario::ahora();
        $archivo = sprintf('%s/%s/%s-%s.jsonl.gz.cifrado', $tabla, $ahora->format('Y-m'), $ahora->format('Ymd-His'), Str::uuid7());
        $lineas = implode('', array_map(fn (array $fila): string => json_encode($fila, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n", $filas));

        // Nunca sobrescribe: el nombre es único y, si ya existiera, falla antes de escribir.
        if ($this->disco()->exists($archivo)) {
            throw HistoricoFallido::noComprobado($archivo);
        }

        $this->disco()->put($archivo, $this->cifrado->cifrar((string) gzencode($lineas, 9)));

        $leidos = array_map(fn (array $fila): int => (int) $fila['id'], $this->leer($archivo));

        if ($leidos !== $ids) {
            throw HistoricoFallido::noComprobado($archivo);
        }
    }

    private function disco(): Filesystem
    {
        return Storage::disk((string) config('nucleo.historico.disco'));
    }
}
