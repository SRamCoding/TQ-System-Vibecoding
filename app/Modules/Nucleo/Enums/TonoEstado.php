<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Enums;

use App\Modules\Nucleo\Navegacion\AreaModulo;

/**
 * Color de un estado, elegido de una lista con nombre (plan 1.5, D10). Cada tono usa un color de Filament, que ya
 * tiene su versión clara y oscura; en pantalla va siempre junto al nombre del estado (`interfaz.md`).
 */
enum TonoEstado: string
{
    case Neutro = 'neutro';
    case EnCurso = 'en_curso';
    case PorAprobar = 'por_aprobar';
    case Aprobado = 'aprobado';
    case Alerta = 'alerta';
    case Cerrado = 'cerrado';
    case Anulado = 'anulado';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Neutro => 'Neutro',
            self::EnCurso => 'En curso',
            self::PorAprobar => 'Por aprobar',
            self::Aprobado => 'Aprobado',
            self::Alerta => 'Alerta',
            self::Cerrado => 'Cerrado',
            self::Anulado => 'Anulado',
        };
    }

    /**
     * El color de Filament del tono. «En curso» toma el color del área del documento (plan 1.4ab, D4; desarrollador,
     * 2026-11-15): turquesa en el ERP y en el núcleo (`info`), morado en el CRM. Los demás tonos no dependen del área.
     */
    public function colorFilament(?AreaModulo $area = null): string
    {
        return match ($this) {
            self::Neutro, self::Cerrado => 'gray',
            self::EnCurso => $area === AreaModulo::Crm ? AreaModulo::Crm->color() : 'info',
            self::PorAprobar, self::Alerta => 'warning',
            self::Aprobado => 'success',
            self::Anulado => 'danger',
        };
    }

    /** El color del tono para un documento según su clave (`crm.oportunidades.oportunidad` es del CRM). */
    public function colorDeDocumento(string $claveDocumento): string
    {
        return $this->colorFilament(AreaModulo::tryFrom(strstr($claveDocumento, '.', true) ?: $claveDocumento));
    }
}
