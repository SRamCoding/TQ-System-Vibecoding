<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Seguridad;

/**
 * Grupos de datos sensibles (plan 1.4, D6; ADR 0016). Un campo de un grupo está oculto salvo para quien tiene su
 * permiso `campos.ver-<grupo>`: si se olvida configurar algo, el dato no se ve. Un valor calculado a partir de campos
 * de un grupo (un margen, un total, una cifra de tablero) es del mismo grupo y se oculta igual.
 */
enum GrupoSensible: string
{
    case Costos = 'costos';
    case Margen = 'margen';
    case Remuneracion = 'remuneracion';
    case DetalleMedico = 'detalle_medico';
    /** Por evaluar con el v4: puede bastar con `costos`. */
    case PrecioCompra = 'precio_compra';
    case DatosBancarios = 'datos_bancarios';

    public function permiso(): string
    {
        return 'campos.ver-'.$this->value;
    }

    public function etiqueta(): string
    {
        return match ($this) {
            self::Costos => 'Costos',
            self::Margen => 'Márgenes',
            self::Remuneracion => 'Remuneraciones',
            self::DetalleMedico => 'Detalle médico',
            self::PrecioCompra => 'Precios de compra',
            self::DatosBancarios => 'Datos bancarios',
        };
    }

    /**
     * Los permisos de todos los grupos: los crea PermisosSeeder.
     *
     * @return list<string>
     */
    public static function permisos(): array
    {
        return array_map(fn (self $grupo): string => $grupo->permiso(), self::cases());
    }
}
