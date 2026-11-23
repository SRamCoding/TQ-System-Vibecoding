<?php

declare(strict_types=1);

namespace App\Modules\Erp\Personal\Models;

use Illuminate\Database\Eloquent\Model;

/** MVP rápido (tramo 4). */
class TareoLinea extends Model
{
    protected $table = 'tareo_lineas';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['costo_hora' => 'decimal:2', 'de_jornada' => 'boolean', 'faltas' => 'array'];
    }

    public function horas(): string
    {
        return array_reduce(['lu', 'ma', 'mi', 'ju', 'vi', 'sa', 'do'], fn (string $s, string $d): string => bcadd($s, (string) $this->{$d}, 1), '0');
    }

    public function costo(): string
    {
        return bcmul($this->horas(), (string) $this->costo_hora, 2);
    }
}
