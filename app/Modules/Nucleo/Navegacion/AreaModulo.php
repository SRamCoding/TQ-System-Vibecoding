<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Navegacion;

/**
 * A qué parte del sistema pertenece un módulo, para agrupar sus cajas en el inicio
 * (docs/convenciones/interfaz.md, «Inicio»). Cada área tiene su color de marca y una frase corta.
 * El orden de los casos es el orden de las secciones.
 */
enum AreaModulo: string
{
    /** «Mien», el tablero personal (plan 1.4l): antes que todo. */
    case Personal = 'personal';

    case Nucleo = 'nucleo';
    case Crm = 'crm';
    case Erp = 'erp';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Personal => 'Personal',
            self::Nucleo => 'Núcleo',
            self::Crm => 'CRM',
            self::Erp => 'ERP',
        };
    }

    public function descripcion(): string
    {
        return match ($this) {
            self::Personal => 'Lo tuyo del negocio, de un vistazo.',
            self::Nucleo => 'La base común: personas y empresas, catálogos, permisos y configuración.',
            self::Crm => 'Todo para vender: cuentas, oportunidades, cotizaciones y licitaciones.',
            self::Erp => 'Todo para ejecutar los proyectos: contratos, compras, almacén, personal y finanzas.',
        };
    }

    /** Nombre del color de Filament, declarado en el proveedor del panel. */
    public function color(): string
    {
        return match ($this) {
            self::Personal => 'dorado',
            self::Nucleo => 'primary',
            self::Crm => 'morado',
            self::Erp => 'info',
        };
    }
}
