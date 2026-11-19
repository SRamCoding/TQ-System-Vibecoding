<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Enums;

/** Cómo terminó una llamada al modelo (plan 1.11, D3), con su CHECK en la base. Todas cuentan para el tope. */
enum ResultadoLlamadaIa: string
{
    case Valida = 'valida';

    /** La respuesta no tenía la forma pedida o no pasó la validación. Camino manual, sin respaldo. */
    case Invalida = 'invalida';

    /** El modelo llegó a su límite de tokens y la respuesta quedó a medias. Camino manual, sin respaldo. */
    case Cortada = 'cortada';

    case Error = 'error';
    case TiempoAgotado = 'tiempo_agotado';

    /** El proveedor está saturado (429, 529): se reintenta una vez, corto. */
    case Saturado = 'saturado';

    /** Crédito agotado: no se reintenta y avisa a su responsable. */
    case SinCredito = 'sin_credito';

    /** Clave inválida o sin permiso: no se reintenta y avisa a su responsable. */
    case ClaveInvalida = 'clave_invalida';

    /** Falló la llamada, no la respuesta: puede pasar al modelo de respaldo (D5). */
    public function pasaAlRespaldo(): bool
    {
        return in_array($this, [self::Error, self::TiempoAgotado, self::Saturado, self::SinCredito, self::ClaveInvalida], true);
    }
}
