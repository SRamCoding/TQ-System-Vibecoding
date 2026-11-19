<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Services;

use App\Modules\Nucleo\Adjuntos\ConAdjuntos;
use App\Modules\Nucleo\Estados\DocumentoConEstados;
use App\Modules\Nucleo\Excepciones\AdjuntoRechazado;
use App\Modules\Nucleo\Models\Adjunto;
use App\Modules\Nucleo\Models\TipoAdjunto;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\ArchivoSubido;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * M7 · Los archivos adjuntos de cualquier registro (plan 1.8). Guarda el archivo en el disco privado `adjuntos` con un
 * nombre interno y en la base solo su ruta (D1). Acepta solo lo que dicen los parámetros y el tipo, revisando el
 * contenido real y no solo la extensión (D3), sin repetir el mismo archivo en un registro. Quitar deja rastro (D2) y en
 * un documento no editable solo se agrega (plan 1.5, D8). Un sensible solo lo ve quien tiene su permiso (D5).
 */
final readonly class AdjuntosService
{
    public const string DISCO = 'adjuntos';

    /** Lo que el contenido de cada extensión puede ser (lo que dice `finfo`). Una extensión sin entrada no se revisa. */
    private const array CONTENIDOS = [
        'pdf' => ['application/pdf'],
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
        'webp' => ['image/webp'],
        // Un .xlsx o .docx es un zip por dentro: según la versión, `finfo` dice una cosa o la otra.
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
        'xml' => ['text/xml', 'application/xml'],
        'zip' => ['application/zip', 'application/x-zip-compressed'],
    ];

    /** Nunca, aunque un parámetro las permita: se ejecutan o se abren como página. */
    private const array PROHIBIDAS = [
        'php', 'php3', 'php4', 'php5', 'php6', 'php7', 'php8', 'phps', 'pht', 'phtml', 'phar',
        'exe', 'msi', 'scr', 'dll', 'jar', 'bat', 'cmd', 'sh', 'ps1', 'vbs', 'hta',
        'js', 'html', 'htm', 'xhtml', 'shtml', 'svg',
    ];

    public function __construct(
        private ParametrosService $parametros,
        private ContextoAuditoria $auditoria,
    ) {}

    /** Agrega un archivo al registro; se puede también en un documento no editable. */
    public function agregar(string $adjuntableType, int $adjuntableId, ArchivoSubido $archivo, ?string $tipoCodigo, User $usuario): Adjunto
    {
        $registro = $this->registro($adjuntableType, $adjuntableId);
        $this->exigirQuePuedaAdjuntar($registro, $usuario);

        return $this->guardar($registro, $archivo, $this->tipo($tipoCodigo, $adjuntableType), $usuario, null);
    }

    /**
     * Lo quita del registro, dejando rastro (D2). No en un documento no editable: el estado se revisa con la fila del
     * registro bloqueada, como en las transiciones, para que una que lo cierre a la vez no se cruce (`codigo.md`).
     */
    public function quitar(int $adjuntoId, User $usuario): Adjunto
    {
        return DB::transaction(fn (): Adjunto => $this->auditoria->comoUsuario($usuario, function () use ($adjuntoId, $usuario): Adjunto {
            $adjunto = Adjunto::query()->findOrFail($adjuntoId);
            $registro = $this->registro($adjunto->adjuntable_type, $adjunto->adjuntable_id, bloquear: true);
            $adjunto = Adjunto::query()->lockForUpdate()->findOrFail($adjuntoId);
            $this->exigirQuePuedaAdjuntar($registro, $usuario);
            $this->exigirQuePuedaVer($adjunto, $usuario);
            $this->exigirEditable($registro, 'quitar');
            $this->exigirVigente($adjunto);

            $adjunto->update(['quitado_at' => now(), 'quitado_por_user_id' => $usuario->id]);

            return $adjunto;
        }));
    }

    /** Cambia el archivo por otro del mismo tipo: el anterior queda quitado y el nuevo apunta a él. */
    public function reemplazar(int $adjuntoId, ArchivoSubido $archivo, User $usuario): Adjunto
    {
        $anterior = Adjunto::query()->findOrFail($adjuntoId);
        $registro = $this->registro($anterior->adjuntable_type, $anterior->adjuntable_id);
        $this->exigirQuePuedaAdjuntar($registro, $usuario);
        $this->exigirQuePuedaVer($anterior, $usuario);
        // Primera revisión, para no escribir el archivo en vano; la que vale se repite con el registro bloqueado.
        $this->exigirEditable($registro, 'reemplazar');
        $this->exigirVigente($anterior);

        return $this->guardar($registro, $archivo, $anterior->tipo, $usuario, $anterior);
    }

    /**
     * Los vigentes del registro que la persona puede ver: sin los sensibles que no le tocan.
     *
     * @return Collection<int, Adjunto>
     */
    public function vigentes(Model $registro, User $usuario): Collection
    {
        if (! $registro instanceof ConAdjuntos || ! $registro->puedeVerAdjuntos($usuario)) {
            return new Collection;
        }

        return Adjunto::query()
            ->with('tipo')
            ->where('adjuntable_type', $registro->getMorphClass())
            ->where('adjuntable_id', $registro->getKey())
            ->whereNull('quitado_at')
            ->orderByDesc('id')
            ->get()
            ->filter(fn (Adjunto $adjunto): bool => $this->puedeVerSensible($adjunto, $usuario))
            ->values();
    }

    /**
     * El adjunto, si la persona puede descargarlo (D1 y D5). Uno quitado también se descarga: por eso se conserva (D2),
     * para revisar después qué había.
     */
    public function paraDescargar(int $adjuntoId, User $usuario): Adjunto
    {
        $adjunto = Adjunto::query()->findOrFail($adjuntoId);
        $registro = $this->registro($adjunto->adjuntable_type, $adjunto->adjuntable_id);

        if (! $registro->puedeVerAdjuntos($usuario) || ! $this->puedeVerSensible($adjunto, $usuario)) {
            throw AdjuntoRechazado::conMotivo('No puedes ver este archivo.');
        }

        return $adjunto;
    }

    /**
     * Los tipos obligatorios para ese registro que todavía no tienen un adjunto vigente, por nombre (D4).
     *
     * @return list<string>
     */
    public function obligatoriosQueFaltan(Model $registro): array
    {
        $presentes = Adjunto::query()
            ->where('adjuntable_type', $registro->getMorphClass())
            ->where('adjuntable_id', $registro->getKey())
            ->whereNull('quitado_at')
            ->whereNotNull('tipo_adjunto_id')
            ->pluck('tipo_adjunto_id')
            ->all();

        return TipoAdjunto::query()
            ->where('es_activo', true)
            ->where('es_obligatorio', true)
            ->where('documento_type', $registro->getMorphClass())
            ->whereNotIn('id', $presentes)
            ->orderBy('nombre')
            ->pluck('nombre')
            ->all();
    }

    private function guardar(Model&ConAdjuntos $registro, ArchivoSubido $archivo, ?TipoAdjunto $tipo, User $usuario, ?Adjunto $reemplaza): Adjunto
    {
        [$extension, $mime, $tamano, $hash] = $this->revisar($archivo, $tipo);
        $ruta = sprintf('%s/%s/%s.%s', $registro->getMorphClass(), now('America/Lima')->format('Y/m'), Str::uuid7(), $extension);

        // Si la escritura o la base fallan, el archivo no se queda huérfano en el disco. (Si quien llama tiene su propia
        // transacción y la deshace después, sí queda: lo limpiará la revisión de huérfanos de la página de salud, 1.12.)
        try {
            $origen = fopen($archivo->ruta, 'rb');

            try {
                Storage::disk(self::DISCO)->writeStream($ruta, $origen);
            } finally {
                is_resource($origen) && fclose($origen);
            }

            return DB::transaction(fn (): Adjunto => $this->auditoria->comoUsuario($usuario, function () use ($registro, $archivo, $tipo, $reemplaza, $usuario, $extension, $mime, $tamano, $hash, $ruta): Adjunto {
                // Bloquea el registro: su estado y sus adjuntos no cambian mientras se guarda (plan 1.5, D8).
                $registro = $this->registro($registro->getMorphClass(), (int) $registro->getKey(), bloquear: true);

                if ($reemplaza !== null) {
                    $this->exigirEditable($registro, 'reemplazar');
                    $vigente = Adjunto::query()->lockForUpdate()->findOrFail($reemplaza->id);
                    $this->exigirVigente($vigente);
                    $vigente->update(['quitado_at' => now(), 'quitado_por_user_id' => $usuario->id]);
                }

                $repetido = Adjunto::query()
                    ->where('adjuntable_type', $registro->getMorphClass())
                    ->where('adjuntable_id', $registro->getKey())
                    ->whereNull('quitado_at')
                    ->where('hash', $hash)
                    ->exists();

                if ($repetido) {
                    throw AdjuntoRechazado::conMotivo('Ese archivo ya está adjunto aquí.');
                }

                return Adjunto::query()->create([
                    'adjuntable_type' => $registro->getMorphClass(),
                    'adjuntable_id' => $registro->getKey(),
                    'tipo_adjunto_id' => $tipo?->id,
                    'nombre_original' => self::nombreLimpio($archivo->nombreOriginal, $extension),
                    'ruta' => $ruta,
                    'mime' => $mime,
                    'tamano_bytes' => $tamano,
                    'hash' => $hash,
                    'disco' => self::DISCO,
                    'grupo_sensible' => $tipo?->grupo_sensible,
                    'reemplaza_a_adjunto_id' => $reemplaza?->id,
                ]);
            }));
        } catch (Throwable $e) {
            Storage::disk(self::DISCO)->delete($ruta);

            throw $e;
        }
    }

    /**
     * Extensión, contenido real, tamaño y huella; rechaza lo que no vale (D3).
     *
     * @return array{string, string, int, string}
     */
    private function revisar(ArchivoSubido $archivo, ?TipoAdjunto $tipo): array
    {
        if (! is_file($archivo->ruta) || ! is_readable($archivo->ruta) || ! self::enCarpetaDeSubidas($archivo->ruta)) {
            throw AdjuntoRechazado::conMotivo('El archivo no llegó.');
        }

        $extension = Str::lower(pathinfo($archivo->nombreOriginal, PATHINFO_EXTENSION));
        $permitidas = self::lista($tipo->extensiones ?? $this->parametros->texto('adjuntos.extensiones'));

        if ($extension === '' || in_array($extension, self::PROHIBIDAS, true) || ! in_array($extension, $permitidas, true)) {
            throw AdjuntoRechazado::conMotivo('Ese tipo de archivo no se acepta. Se aceptan: '.implode(', ', $permitidas).'.');
        }

        $tamano = (int) filesize($archivo->ruta);
        $maximo = min($this->parametros->entero('adjuntos.tamano_maximo_mb'), $tipo->tamano_maximo_mb ?? PHP_INT_MAX);

        if ($tamano === 0) {
            throw AdjuntoRechazado::conMotivo('El archivo está vacío.');
        }

        if ($tamano > $maximo * 1024 * 1024) {
            throw AdjuntoRechazado::conMotivo("El archivo pesa más de {$maximo} MB.");
        }

        $mime = (string) (new \finfo(FILEINFO_MIME_TYPE))->file($archivo->ruta);

        if (isset(self::CONTENIDOS[$extension]) && ! in_array($mime, self::CONTENIDOS[$extension], true)) {
            throw AdjuntoRechazado::conMotivo("El archivo dice ser .{$extension}, pero su contenido no lo es.");
        }

        return [$extension, $mime, $tamano, (string) hash_file('sha256', $archivo->ruta)];
    }

    private function tipo(?string $codigo, string $adjuntableType): ?TipoAdjunto
    {
        if ($codigo === null || $codigo === '') {
            return null;
        }

        $tipo = TipoAdjunto::query()->where('codigo', $codigo)->where('es_activo', true)->first()
            ?? throw AdjuntoRechazado::conMotivo('Ese tipo de adjunto no existe o está desactivado.');

        if ($tipo->documento_type !== null && $tipo->documento_type !== $adjuntableType) {
            throw AdjuntoRechazado::conMotivo("«{$tipo->nombre}» no es para este tipo de registro.");
        }

        return $tipo;
    }

    private function registro(string $tipo, int $id, bool $bloquear = false): Model&ConAdjuntos
    {
        $clase = Relation::getMorphedModel($tipo);
        $registro = $clase === null ? null : ($bloquear ? $clase::query()->lockForUpdate()->find($id) : $clase::query()->find($id));

        if (! $registro instanceof ConAdjuntos) {
            throw AdjuntoRechazado::conMotivo('Ese registro no existe o no lleva adjuntos.');
        }

        return $registro;
    }

    private function exigirQuePuedaAdjuntar(ConAdjuntos $registro, User $usuario): void
    {
        if (! $registro->puedeAdjuntar($usuario)) {
            throw AdjuntoRechazado::conMotivo('No puedes cambiar los archivos de este registro.');
        }
    }

    private function exigirEditable(Model $registro, string $que): void
    {
        if ($registro instanceof DocumentoConEstados && ! $registro->esEditable()) {
            throw AdjuntoRechazado::conMotivo("En este estado no se puede {$que} un archivo: solo agregar otro.");
        }
    }

    private function exigirVigente(Adjunto $adjunto): void
    {
        if ($adjunto->quitado_at !== null) {
            throw AdjuntoRechazado::conMotivo('Ese archivo ya se quitó.');
        }
    }

    private function exigirQuePuedaVer(Adjunto $adjunto, User $usuario): void
    {
        if (! $this->puedeVerSensible($adjunto, $usuario)) {
            throw AdjuntoRechazado::conMotivo('No puedes ver este archivo.');
        }
    }

    /**
     * Solo se toma un archivo de las carpetas temporales de subida (la del sistema y la de Livewire): nunca de otro
     * lado del servidor, y menos del disco de adjuntos (plan 1.8: un archivo de otro registro no se copia así).
     */
    private static function enCarpetaDeSubidas(string $ruta): bool
    {
        $real = realpath($ruta);

        if ($real === false) {
            return false;
        }

        $carpetas = array_filter([realpath(sys_get_temp_dir()), realpath(storage_path('app/private/livewire-tmp')), realpath(storage_path('app/livewire-tmp'))]);

        foreach ($carpetas as $carpeta) {
            if (str_starts_with($real, $carpeta.DIRECTORY_SEPARATOR)) {
                return true;
            }
        }

        return false;
    }

    /** Sin carpetas, barras ni caracteres de control (romperían la descarga), y con su extensión. */
    private static function nombreLimpio(string $nombre, string $extension): string
    {
        $limpio = trim((string) preg_replace('/[\x00-\x1F\x7F\/\\\\]+/u', '_', basename(str_replace('\\', '/', $nombre))));

        return Str::limit($limpio !== '' && $limpio !== '.' ? $limpio : "archivo.{$extension}", 250, '');
    }

    private function puedeVerSensible(Adjunto $adjunto, User $usuario): bool
    {
        return $adjunto->grupo_sensible === null || $usuario->can($adjunto->grupo_sensible->permiso());
    }

    /**
     * @return list<string>
     */
    private static function lista(string $extensiones): array
    {
        return array_values(array_filter(array_map(fn (string $e): string => Str::lower(trim($e, " .\t")), explode(',', $extensiones))));
    }
}
