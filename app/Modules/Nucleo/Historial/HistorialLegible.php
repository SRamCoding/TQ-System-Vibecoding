<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Historial;

use App\Modules\Nucleo\Enums\EventoAuditoria;
use App\Modules\Nucleo\Models\AuditoriaCambio;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Seguridad\CamposVisibles;
use App\Modules\Nucleo\Services\HistorialCambiosService;
use Illuminate\Database\Eloquent\Model;

/**
 * Arma la pestaña de historial de una ficha (plan 1.3, D10): los cambios de uno o varios registros (una
 * party y sus roles) en una sola línea de tiempo, la más reciente primero, una fila por campo, con nombres y
 * valores legibles. Sin los campos que la persona no puede ver (CamposVisibles): se filtra aquí, en el servidor.
 */
final class HistorialLegible
{
    public function __construct(
        private readonly HistorialCambiosService $historial,
        private readonly CamposVisibles $camposVisibles,
    ) {}

    /**
     * @param  list<Model&DescribibleEnBitacora>  $modelos
     * @return list<FilaHistorial>
     */
    public function filas(User $usuario, array $modelos): array
    {
        $porClave = [];
        $ocultos = [];

        foreach ($modelos as $modelo) {
            $clave = self::clave($modelo->getMorphClass(), (int) $modelo->getKey());
            $porClave[$clave] = $modelo;
            $ocultos[$clave] = $this->camposVisibles->ocultos($usuario, $modelo);
        }

        $filas = [];

        foreach ($this->historial->deVarios($modelos) as $cambio) {
            $clave = self::clave($cambio->auditable_type, $cambio->auditable_id);
            $modelo = $porClave[$clave];
            $campos = self::ordenar(self::camposDelCambio($cambio, $ocultos[$clave]), $modelo->etiquetasBitacora());
            // El resumen se arma con todos los valores: solo si la persona puede verlos todos. Si no, filas por campo.
            $resumen = $cambio->evento === EventoAuditoria::Creado && $campos !== []
                && count($campos) === count(self::camposDelCambio($cambio, []))
                ? $modelo->resumenCreacionBitacora($cambio->valores_nuevos ?? [])
                : null;

            if ($resumen !== null) {
                $filas[] = new FilaHistorial($cambio->fecha, self::autor($cambio), $cambio->evento->etiqueta(), $modelo->sujetoBitacora(), '', '', '', $resumen);

                continue;
            }

            foreach ($campos as $campo) {
                $filas[] = new FilaHistorial(
                    fecha: $cambio->fecha,
                    autor: self::autor($cambio),
                    evento: $cambio->evento->etiqueta(),
                    sujeto: $modelo->sujetoBitacora(),
                    campo: $modelo->etiquetasBitacora()[$campo] ?? $campo,
                    anterior: $modelo->valorLegibleBitacora($campo, $cambio->valores_anteriores[$campo] ?? null),
                    nuevo: $modelo->valorLegibleBitacora($campo, $cambio->valores_nuevos[$campo] ?? null),
                );
            }
        }

        return $filas;
    }

    /**
     * Los campos de un cambio que se muestran. Al crear y al borrar, solo los que tenían valor.
     *
     * @param  list<string>  $ocultos
     * @return list<string>
     */
    private static function camposDelCambio(AuditoriaCambio $cambio, array $ocultos): array
    {
        $valores = $cambio->evento === EventoAuditoria::Eliminado ? ($cambio->valores_anteriores ?? []) : ($cambio->valores_nuevos ?? []);

        if ($cambio->evento !== EventoAuditoria::Actualizado) {
            $valores = array_filter($valores, fn (mixed $valor): bool => $valor !== null && $valor !== '');
        }

        $campos = array_values(array_diff(array_keys($valores), ['id', 'created_at', 'updated_at', 'created_by', 'updated_by']));

        return array_values(array_diff($campos, $ocultos));
    }

    /**
     * En el orden en que el modelo declara sus etiquetas; los que no tienen etiqueta, al final.
     *
     * @param  list<string>  $campos
     * @param  array<string, string>  $etiquetas
     * @return list<string>
     */
    private static function ordenar(array $campos, array $etiquetas): array
    {
        $orden = array_flip(array_keys($etiquetas));
        usort($campos, fn (string $a, string $b): int => ($orden[$a] ?? PHP_INT_MAX) <=> ($orden[$b] ?? PHP_INT_MAX));

        return $campos;
    }

    private static function autor(AuditoriaCambio $cambio): string
    {
        $usuario = $cambio->usuario;

        return $usuario === null || $usuario->es_sistema ? 'Sistema' : $usuario->name;
    }

    private static function clave(string $tipo, int $id): string
    {
        return "{$tipo}#{$id}";
    }
}
