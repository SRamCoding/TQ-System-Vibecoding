<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Imputacion;

use App\Modules\Nucleo\Models\Imputacion;
use App\Modules\Nucleo\RegistroAcciones\Campo;

/**
 * Lo que la persona eligió en el selector de imputación (plan 2.2, D2). Viaja en los datos de la acción del documento
 * (`campos()` y `desdeDatos()`), no aparte de la pantalla: el servicio del documento guarda la línea y su destino en una
 * sola transacción. La validación de fondo (si la partida acepta, si el motivo es de su lista) es de `FuenteDeDestinos`.
 */
final readonly class EleccionImputacion
{
    public const string PREFIJO = 'imputacion_';

    public function __construct(
        public TipoImputacion $tipo,
        public ?int $proyectoId = null,
        public ?int $partidaId = null,
        public ?int $centroCostoId = null,
        public ?int $motivoId = null,
        public ?string $comentario = null,
    ) {}

    /**
     * Los campos que un documento suma a su acción para recibir la elección.
     *
     * @return list<Campo>
     */
    public static function campos(): array
    {
        $p = self::PREFIJO;

        return [
            Campo::opcion("{$p}tipo", 'Imputar a', TipoImputacion::class),
            Campo::entero("{$p}proyecto_id", 'Proyecto')->conReglas('min:1')->opcional(),
            Campo::entero("{$p}partida_id", 'Partida de control')->conReglas('min:1')->opcional(),
            Campo::entero("{$p}centro_costo_id", 'Centro de costo')->conReglas('min:1')->opcional(),
            Campo::entero("{$p}motivo_id", 'Motivo')->conReglas('min:1')->opcional(),
            Campo::texto("{$p}comentario", 'Comentario', maximo: 500)->opcional(),
        ];
    }

    /**
     * La elección desde los datos ya validados de una acción (con los nombres de `campos()`).
     *
     * @param  array<string, mixed>  $datos
     */
    public static function desdeDatos(array $datos): self
    {
        $p = self::PREFIJO;
        $tipo = $datos["{$p}tipo"];
        $comentario = isset($datos["{$p}comentario"]) ? trim((string) $datos["{$p}comentario"]) : null;

        return new self(
            tipo: $tipo instanceof TipoImputacion ? $tipo : TipoImputacion::from((string) $tipo),
            proyectoId: self::entero($datos["{$p}proyecto_id"] ?? null),
            partidaId: self::entero($datos["{$p}partida_id"] ?? null),
            centroCostoId: self::entero($datos["{$p}centro_costo_id"] ?? null),
            motivoId: self::entero($datos["{$p}motivo_id"] ?? null),
            comentario: $comentario === '' ? null : $comentario,
        );
    }

    /** Lo que una línea ya tiene guardado, para volver a abrir el formulario. */
    public static function desdeImputacion(Imputacion $imputacion, bool $esNoPresupuestada = false): self
    {
        $tipo = match (true) {
            $imputacion->partida_control_id === null => TipoImputacion::CentroDeCosto,
            $esNoPresupuestada => TipoImputacion::NoPresupuestado,
            default => TipoImputacion::Partida,
        };

        return new self($tipo, $imputacion->proyecto_id, $imputacion->partida_control_id, $imputacion->centro_costo_id, $imputacion->motivo_id, $imputacion->comentario);
    }

    /**
     * La elección como datos de una acción, con los nombres de `campos()`.
     *
     * @return array<string, int|string|null>
     */
    public function comoDatos(): array
    {
        $p = self::PREFIJO;

        return [
            "{$p}tipo" => $this->tipo->value,
            "{$p}proyecto_id" => $this->proyectoId,
            "{$p}partida_id" => $this->partidaId,
            "{$p}centro_costo_id" => $this->centroCostoId,
            "{$p}motivo_id" => $this->motivoId,
            "{$p}comentario" => $this->comentario,
        ];
    }

    private static function entero(mixed $valor): ?int
    {
        return $valor === null || $valor === '' ? null : (int) $valor;
    }
}
