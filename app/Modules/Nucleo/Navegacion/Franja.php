<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Navegacion;

/**
 * Las franjas del inicio (MV-016): los procesos de Techquk agrupados como en un mapa de procesos. Reemplazan a las
 * áreas Núcleo, CRM y ERP en el inicio y en la barra lateral. «Personal» es la de Mien, que va arriba, a lo ancho.
 * El orden de los casos es el orden de las franjas.
 */
enum Franja: string
{
    case Personal = 'personal';
    case Estrategicos = 'estrategicos';
    case Misionales = 'misionales';
    case Apoyo = 'apoyo';
    case Plataforma = 'plataforma';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Personal => 'Personal',
            self::Estrategicos => 'Estratégicos',
            self::Misionales => 'Misionales',
            self::Apoyo => 'De Apoyo',
            self::Plataforma => 'Plataforma',
        };
    }

    public function descripcion(): string
    {
        return match ($this) {
            self::Personal => 'Lo tuyo del negocio, de un vistazo.',
            self::Estrategicos => 'Hacia dónde va Techquk y cuánto gana.',
            self::Misionales => 'Lo que Techquk hace para sus clientes, de conseguir el negocio a la postventa.',
            self::Apoyo => 'Lo que sostiene a los procesos misionales.',
            self::Plataforma => 'El sistema mismo: usuarios, permisos y configuración.',
        };
    }

    /**
     * El color de cada franja (MV-033, reemplaza MV-019): Misionales turquesa, Estratégicos azul noche, De Apoyo morado,
     * Plataforma gris neutro y el oro solo para Mien. Se toma el área cuyo color es ese, así los estilos por estación, la
     * barra lateral y las portadas siguen funcionando.
     */
    public function areaDeColor(): AreaModulo
    {
        return match ($this) {
            self::Personal => AreaModulo::Personal,
            self::Estrategicos => AreaModulo::Nucleo,
            self::Misionales => AreaModulo::Erp,
            self::Apoyo => AreaModulo::Crm,
            self::Plataforma => AreaModulo::Plataforma,
        };
    }
}
