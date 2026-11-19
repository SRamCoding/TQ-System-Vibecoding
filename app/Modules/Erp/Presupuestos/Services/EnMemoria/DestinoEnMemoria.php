<?php

declare(strict_types=1);

namespace App\Modules\Erp\Presupuestos\Services\EnMemoria;

use App\Modules\Nucleo\Imputacion\DestinoImputacion;

/**
 * El destino que da `PartidasEnMemoria`: sirve para probar quien pide un destino y lo pasa, no para guardarlo. La base
 * exige que la partida exista (llave foránea): quien necesite imputar de verdad arma un árbol real con `ArbolDePrueba`.
 */
final readonly class DestinoEnMemoria implements DestinoImputacion
{
    public function __construct(
        public int $partidaId,
        public int $proyectoId,
        public string $codigo,
        public ?int $motivoId = null,
        public ?string $comentario = null,
        public ?int $centroCostoId = null,
    ) {}

    public function columnas(): array
    {
        return [
            'partida_control_id' => $this->partidaId,
            'proyecto_id' => $this->proyectoId,
            'centro_costo_id' => $this->centroCostoId,
            'motivo_id' => $this->motivoId,
            'comentario' => $this->comentario,
        ];
    }

    public function revisarAlDestinar(): void {}

    public function descripcion(): string
    {
        return "la partida {$this->codigo}";
    }
}
