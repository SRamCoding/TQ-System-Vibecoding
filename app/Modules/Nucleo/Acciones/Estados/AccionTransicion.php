<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Acciones\Estados;

use App\Modules\Nucleo\Estados\DefinicionDocumento;
use App\Modules\Nucleo\Estados\DocumentoConEstados;
use App\Modules\Nucleo\Estados\MotivoElegido;
use App\Modules\Nucleo\Estados\TransicionDefinida;
use App\Modules\Nucleo\Models\HistorialEstado;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\Accion;
use App\Modules\Nucleo\RegistroAcciones\Campo;
use App\Modules\Nucleo\Services\TransicionesService;
use Illuminate\Database\Eloquent\Model;

/**
 * Una transición de persona como acción registrada (plan 1.5, D9): `compras.orden.anular`, con el permiso de la
 * transición. RegistroDocumentos registra una por transición, sin escribir una clase cada vez. Solo traduce campos:
 * permiso con ámbito, condiciones, motivo, bloqueo y versión los revisa TransicionesService.
 */
final class AccionTransicion extends Accion
{
    public function __construct(
        private readonly DefinicionDocumento $definicion,
        private readonly TransicionDefinida $transicion,
    ) {}

    public function clave(): string
    {
        return "{$this->definicion->clave()}.{$this->transicion->codigo}";
    }

    public function nombre(): string
    {
        return "{$this->transicion->nombre} {$this->definicion->nombre()}";
    }

    public function campos(): array
    {
        $motivo = $this->transicion->requiereMotivo ? [
            Campo::motivoDe('motivo_id', 'Motivo', (string) $this->definicion->familiaDeMotivos($this->transicion)),
            Campo::texto('motivo_detalle', 'Detalle del motivo', maximo: 1000)->opcional(),
        ] : [];

        return [
            Campo::entero('documento_id', 'Documento')->conReglas('min:1'),
            Campo::entero('version', 'Versión')->conReglas('min:1')->deControl(),
            ...$motivo,
            Campo::texto('comentario', 'Comentario', maximo: 1000)->opcional(),
        ];
    }

    public function permiso(): string
    {
        return (string) $this->definicion->permiso($this->transicion);
    }

    public function permisoPorAmbito(): bool
    {
        return true;
    }

    public function ejecutar(array $datos, User $usuario): HistorialEstado
    {
        /** @var Model&DocumentoConEstados $documento */
        $documento = $this->definicion->modelo()::query()->findOrFail($datos['documento_id']);
        $motivo = isset($datos['motivo_id']) ? new MotivoElegido($datos['motivo_id'], $datos['motivo_detalle'] ?? null) : null;

        return app(TransicionesService::class)->transicionar($documento, $this->transicion->codigo, $datos['version'], $usuario, $motivo, $datos['comentario'] ?? null);
    }
}
