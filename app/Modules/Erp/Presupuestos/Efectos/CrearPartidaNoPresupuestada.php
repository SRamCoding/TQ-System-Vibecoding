<?php

declare(strict_types=1);

namespace App\Modules\Erp\Presupuestos\Efectos;

use App\Modules\Erp\Presupuestos\Models\PartidaControl;
use App\Modules\Erp\Proyectos\Services\ProyectosService;
use App\Modules\Nucleo\Estados\Efecto;
use Illuminate\Database\Eloquent\Model;

/**
 * Al aprobar el árbol, la PC-99 «No presupuestados» del proyecto, con presupuesto cero y el jefe del proyecto como
 * responsable (v4, parte 6; plan 2.1, D5). Solo una por proyecto (la base lo garantiza): una versión nueva del árbol
 * no crea otra. No se borra: un árbol aprobado no se anula, así que no hay nada que revertir.
 */
final readonly class CrearPartidaNoPresupuestada implements Efecto
{
    public function __construct(private ProyectosService $proyectos) {}

    public function aplicar(Model $documento): array
    {
        $proyectoId = (int) $documento->getAttribute('proyecto_id');
        $existente = PartidaControl::query()->where('proyecto_id', $proyectoId)->where('es_no_presupuestado', true)->value('id');

        if ($existente !== null) {
            return ['partida_id' => (int) $existente, 'creada' => false];
        }

        $partida = PartidaControl::query()->create([
            'proyecto_id' => $proyectoId,
            'arbol_alta_id' => $documento->getKey(),
            'codigo' => PartidaControl::CODIGO_NO_PRESUPUESTADA,
            'nombre' => 'No presupuestados',
            'es_hoja' => true,
            'es_no_presupuestado' => true,
            'responsable_user_id' => $this->proyectos->resumen($proyectoId)->jefeProyectoUserId,
        ]);

        return ['partida_id' => $partida->id, 'creada' => true];
    }

    public function revertir(Model $documento, array $datos): void
    {
        // La PC-99 no se deshace: un árbol aprobado no se anula, y la PC-99 no se borra nunca (v4). Si «Nueva versión» reabre el árbol, la PC-99 sigue.
    }
}
