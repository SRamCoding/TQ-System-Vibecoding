<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Services;

use App\Modules\Nucleo\Estados\DetalleDeOtro;
use App\Modules\Nucleo\Estados\RegistroDocumentos;
use App\Modules\Nucleo\Excepciones\MotivoRechazado;
use App\Modules\Nucleo\Excepciones\VersionDesactualizada;
use App\Modules\Nucleo\Models\HistorialEstado;
use App\Modules\Nucleo\Models\Motivo;
use App\Modules\Nucleo\Models\TipoMotivo;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;

/**
 * Las listas de motivos de las transiciones (plan 1.5, D9). Las familias las declara el código; aquí se agregan,
 * renombran, desactivan y activan sus motivos. Nada se borra: un motivo que no se quiere más se desactiva. «Otro»
 * existe en cada familia y no se desactiva. Dos motivos de una lista no se llaman igual, sin contar mayúsculas,
 * tildes ni espacios repetidos (lo garantiza la base).
 */
final readonly class MotivosService
{
    public function __construct(private RegistroDocumentos $registro) {}

    /**
     * Los detalles escritos en «Otro» de una familia, agrupados por texto normalizado, del más repetido al menos: los
     * que se repiten son candidatos a entrar en la lista (D10, agregado 2). Solo lectura. Suma lo que guardan otros
     * módulos por sus FuenteDeMotivos.
     *
     * @return list<DetalleDeOtro>
     */
    public function detallesDeOtro(string $familia, ?CarbonImmutable $desde = null): array
    {
        $tipo = TipoMotivo::query()->where('codigo', $familia)->firstOrFail();
        $clave = self::normalizacionSql('motivo_detalle');

        $propios = HistorialEstado::query()
            ->where('tipo_motivo_id', $tipo->id)
            ->where('motivo_es_otro', true)
            ->when($desde !== null, fn ($consulta) => $consulta->where('fecha', '>=', $desde))
            ->selectRaw("{$clave} AS clave, count(*) AS veces, max(fecha) AS ultimo_uso, (array_agg(btrim(motivo_detalle) ORDER BY fecha DESC, id DESC))[1] AS texto")
            ->groupByRaw($clave)
            ->toBase()
            ->get()
            ->map(fn (object $fila): DetalleDeOtro => new DetalleDeOtro($fila->clave, $fila->texto, (int) $fila->veces, CarbonImmutable::parse($fila->ultimo_uso)))
            ->all();

        $todos = $propios;

        foreach ($this->registro->fuentesDeMotivos() as $fuente) {
            $todos = [...$todos, ...app($fuente)->detallesDeOtro($tipo, $desde)];
        }

        return self::juntar($todos);
    }

    /**
     * Cómo se normaliza un detalle para agruparlo: sin espacios al borde ni repetidos, en minúsculas y sin tildes.
     * Una FuenteDeMotivos agrupa con esto mismo.
     */
    public static function normalizacionSql(string $columna): string
    {
        return "regexp_replace(lower(unaccent(btrim({$columna}))), '\\s+', ' ', 'g')";
    }

    /**
     * @param  list<DetalleDeOtro>  $detalles
     * @return list<DetalleDeOtro>
     */
    public static function juntar(array $detalles): array
    {
        $porClave = [];

        foreach ($detalles as $detalle) {
            $previo = $porClave[$detalle->clave] ?? null;
            $masReciente = $previo === null || $detalle->ultimoUso->greaterThan($previo->ultimoUso) ? $detalle : $previo;

            $porClave[$detalle->clave] = new DetalleDeOtro(
                $detalle->clave,
                $masReciente->texto,
                $detalle->veces + ($previo->veces ?? 0),
                $masReciente->ultimoUso,
            );
        }

        usort($porClave, fn (DetalleDeOtro $a, DetalleDeOtro $b): int => [$b->veces, $b->ultimoUso] <=> [$a->veces, $a->ultimoUso]);

        return $porClave;
    }

    /**
     * Para el selector común (`Campo::motivoDe`, plan 2.21): los motivos activos de la familia, con «Otro» al final, y
     * el que el registro ya tiene aunque esté desactivado, marcado (si no, el formulario no se podría guardar).
     *
     * @return array<int, string> nombre por id
     */
    public function opciones(string $familia, ?int $actual = null): array
    {
        $tipo = TipoMotivo::query()->where('codigo', $familia)->value('id')
            ?? throw new LogicException("No existe la lista de motivos «{$familia}».");

        $opciones = Motivo::query()
            ->where('tipo_motivo_id', $tipo)
            ->where('es_activo', true)
            ->orderBy('es_otro')
            ->orderBy('nombre')
            ->pluck('nombre', 'id')
            ->all();

        if ($actual !== null && ! isset($opciones[$actual])) {
            $nombre = Motivo::query()->where('tipo_motivo_id', $tipo)->whereKey($actual)->value('nombre');

            if ($nombre !== null) {
                $opciones[$actual] = "{$nombre} (desactivado)";
            }
        }

        return $opciones;
    }

    /** Si el motivo es el «Otro» de su lista: entonces se pide el detalle. */
    public function esOtro(mixed $motivoId): bool
    {
        return is_numeric($motivoId) && Motivo::query()->whereKey((int) $motivoId)->value('es_otro') === true;
    }

    public function agregar(int $tipoMotivoId, string $nombre): Motivo
    {
        $nombre = trim($nombre);
        $base = self::codigoDe($nombre);

        return DB::transaction(function () use ($tipoMotivoId, $nombre, $base): Motivo {
            // Bloquea la familia: dos altas a la vez no crean el mismo motivo.
            $familia = TipoMotivo::query()->lockForUpdate()->findOrFail($tipoMotivoId);
            $this->rechazarRepetido($familia->id, $nombre);

            return Motivo::query()->create(['tipo_motivo_id' => $familia->id, 'codigo' => $this->codigoLibre($familia->id, $base), 'nombre' => $nombre]);
        });
    }

    public function renombrar(int $motivoId, int $versionLeida, string $nombre): Motivo
    {
        $nombre = trim($nombre);
        self::codigoDe($nombre);

        return DB::transaction(function () use ($motivoId, $versionLeida, $nombre): Motivo {
            $motivo = $this->bloquear($motivoId, $versionLeida);
            TipoMotivo::query()->lockForUpdate()->findOrFail($motivo->tipo_motivo_id);
            $this->rechazarRepetido($motivo->tipo_motivo_id, $nombre, $motivo->id);
            $motivo->nombre = $nombre;

            return $this->guardar($motivo);
        });
    }

    public function desactivar(int $motivoId, int $versionLeida): Motivo
    {
        return $this->cambiarActivo($motivoId, $versionLeida, false);
    }

    public function activar(int $motivoId, int $versionLeida): Motivo
    {
        return $this->cambiarActivo($motivoId, $versionLeida, true);
    }

    private function cambiarActivo(int $motivoId, int $versionLeida, bool $activo): Motivo
    {
        return DB::transaction(function () use ($motivoId, $versionLeida, $activo): Motivo {
            $motivo = $this->bloquear($motivoId, $versionLeida);

            if (! $activo && $motivo->es_otro) {
                throw MotivoRechazado::otroNoSeDesactiva();
            }

            if ($motivo->es_activo === $activo) {
                throw MotivoRechazado::yaEsta($motivo->nombre, $activo);
            }

            $motivo->es_activo = $activo;

            return $this->guardar($motivo);
        });
    }

    private function bloquear(int $motivoId, int $versionLeida): Motivo
    {
        $motivo = Motivo::query()->lockForUpdate()->findOrFail($motivoId);

        if ($motivo->version !== $versionLeida) {
            throw VersionDesactualizada::de("el motivo «{$motivo->nombre}»");
        }

        return $motivo;
    }

    private function guardar(Motivo $motivo): Motivo
    {
        $motivo->version++;
        $motivo->save();

        return $motivo;
    }

    /**
     * La base garantiza que dos motivos de una lista no se llamen igual (`nombre_comparable`); aquí se revisa antes,
     * con la misma normalización, para dar un mensaje claro.
     */
    private function rechazarRepetido(int $tipoMotivoId, string $nombre, ?int $excepto = null): void
    {
        $repetido = Motivo::query()
            ->where('tipo_motivo_id', $tipoMotivoId)
            ->where('nombre_comparable', Motivo::comparable($nombre))
            ->when($excepto !== null, fn ($q) => $q->whereKeyNot($excepto))
            ->exists();

        if ($repetido) {
            throw MotivoRechazado::repetido($nombre);
        }
    }

    /**
     * El código sale del nombre: «Precio alto» da `precio_alto`. Sin letras ni números, no hay código. También para los
     * motivos de partida de una familia.
     */
    public static function codigoDe(string $nombre): string
    {
        $codigo = Str::limit(Str::slug($nombre, '_'), 40, '');

        return $codigo !== '' ? $codigo : throw MotivoRechazado::sinLetras();
    }

    /** Dos nombres distintos pueden dar el mismo código (largos, o con signos): el segundo lleva `_2`, `_3`… */
    private function codigoLibre(int $tipoMotivoId, string $base): string
    {
        $usados = Motivo::query()->where('tipo_motivo_id', $tipoMotivoId)->where('codigo', 'like', mb_substr($base, 0, 30).'%')->pluck('codigo')->all();
        $codigo = $base;

        for ($n = 2; in_array($codigo, $usados, true); $n++) {
            $codigo = Str::limit($base, 40 - strlen("_{$n}"), '')."_{$n}";
        }

        return $codigo;
    }
}
