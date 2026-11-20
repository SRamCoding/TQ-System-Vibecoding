<?php

declare(strict_types=1);

namespace App\Modules\Erp\Compras\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** MV-028: una herramienta propia y dónde está. El estado es texto (atajo del MVP). */
class Herramienta extends Model
{
    public const array TIPOS = ['electrica' => 'Eléctrica', 'altura' => 'Trabajo en altura', 'vehiculo' => 'Vehículo', 'medicion' => 'Medición', 'manual' => 'Manual'];

    public const array ESTADOS = ['en_almacen' => 'En almacén', 'en_obra' => 'En obra', 'en_mantenimiento' => 'En mantenimiento', 'baja' => 'De baja'];

    protected $fillable = ['codigo', 'nombre', 'tipo', 'marca_modelo', 'serie', 'estado', 'proyecto_id', 'colaborador_id', 'salio', 'debe_volver'];

    protected function casts(): array
    {
        return ['salio' => 'date', 'debe_volver' => 'date'];
    }

    /** @return HasMany<HerramientaMovimiento, $this> */
    public function movimientos(): HasMany
    {
        return $this->hasMany(HerramientaMovimiento::class);
    }
}
