<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Alertas;

/**
 * Las claves de las alertas del núcleo que se emiten al ocurrir algo (plan 1.10, D3). La de cruces sin control vive en
 * su fuente (`CrucesSinControl::ALERTA`).
 */
final class AlertasDelNucleo
{
    public const string EFECTO_FALLIDO = 'nucleo.efecto-externo-fallido';

    public const string CORREO_NO_ENVIADO = 'nucleo.correo-no-enviado';

    public const string AUTOAPROBACION = 'nucleo.autoaprobacion-por-revisar';

    public const string TIPO_CAMBIO_FALTANTE = 'nucleo.tipo-cambio-faltante';

    /** Plan 1.11, D3: un proceso de IA llegó a su tope del mes y queda apagado hasta el siguiente. */
    public const string IA_TOPE_ALCANZADO = 'nucleo.ia-tope-alcanzado';

    /** Plan 1.11, D7: un proveedor de IA sin crédito o con la clave inválida; los procesos siguen en manual. */
    public const string IA_PROVEEDOR_NO_DISPONIBLE = 'nucleo.ia-proveedor-no-disponible';

    /** Revisión de arquitectura de la 1.11: un pedido de sugerencia que falló o agotó la espera de cupo. */
    public const string IA_PEDIDO_FALLIDO = 'nucleo.ia-pedido-fallido';

    /** Plan 1.12, D3: un respaldo que falló. */
    public const string RESPALDO_FALLIDO = 'nucleo.respaldo-fallido';

    /** Plan 1.12, D3: el último respaldo correcto es más viejo que su frecuencia más un día. */
    public const string RESPALDO_VENCIDO = 'nucleo.respaldo-vencido';

    /** Plan 1.12, D4: la revisión de huérfanos encontró más que su tope y no movió nada. */
    public const string HUERFANOS_DEMASIADOS = 'nucleo.huerfanos-demasiados';

    /** Plan 1.4y, D6: el archivo histórico no se pudo escribir o comprobar; no se borró nada. */
    public const string HISTORICO_FALLIDO = 'nucleo.historico-fallido';
}
