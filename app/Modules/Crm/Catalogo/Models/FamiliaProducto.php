<?php

declare(strict_types=1);

namespace App\Modules\Crm\Catalogo\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** MVP rápido (t1-catalogo): una familia del catálogo («Videovigilancia › Cámaras»). */
class FamiliaProducto extends Model
{
    protected $table = 'familias_producto';

    protected $fillable = ['parent_id', 'codigo', 'nombre', 'es_activa'];

    protected function casts(): array
    {
        return ['es_activa' => 'boolean'];
    }

    /** @return BelongsTo<FamiliaProducto, $this> */
    public function padre(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** «Videovigilancia › Cámaras». */
    public function ruta(): string
    {
        return $this->padre === null ? $this->nombre : "{$this->padre->nombre} › {$this->nombre}";
    }
}
