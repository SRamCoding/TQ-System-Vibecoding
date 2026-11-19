<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Services;

use App\Modules\Nucleo\Numeracion\FormatoNumeracion;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;

/**
 * M6 · El único camino para tomar un número de documento (plan 1.6). Sin huecos ni duplicados (D1): una sola sentencia
 * crea el contador o le suma uno, y la fila queda bloqueada hasta que termina la transacción que guarda el documento.
 * Si esa transacción falla, el número vuelve atrás con ella; si otra persona pide a la vez, espera su turno. Por eso
 * exige una transacción abierta y conviene pedirlo lo más tarde posible dentro de ella (lo hace
 * `TransicionesService::crear`, justo antes de guardar).
 */
final readonly class NumeracionService
{
    public function __construct(private ContextoAuditoria $auditoria) {}

    /**
     * El siguiente número de la serie: `REQ-2026-0001`.
     *
     * @param  string  $documentoType  el alias del morph map del documento
     * @param  string  $ambito  vacío = toda la empresa; si no, el código del proyecto o contrato (`CT0003`), que va dentro
     *                          del número (D4)
     */
    public function siguiente(string $documentoType, FormatoNumeracion $formato, CarbonImmutable $fecha, string $ambito = ''): string
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('El número se toma dentro de la transacción que guarda el documento: fuera de ella, un fallo dejaría un hueco.');
        }

        if (preg_match('/^([A-Z0-9][A-Z0-9.]*)?$/', $ambito) !== 1 || strlen($ambito) > 60) {
            throw new InvalidArgumentException("El ámbito «{$ambito}» debe ser un código en mayúsculas, sin espacios ni guiones, de hasta 60 caracteres.");
        }

        $periodo = $formato->periodo($fecha);
        $actor = $this->auditoria->idActor();

        $correlativo = DB::selectOne(
            'INSERT INTO series_documento (documento_type, serie, ambito, periodo, ultimo_numero, created_by, created_at)
             VALUES (?, ?, ?, ?, 1, ?, now())
             ON CONFLICT (documento_type, serie, ambito, periodo)
             DO UPDATE SET ultimo_numero = series_documento.ultimo_numero + 1, updated_by = EXCLUDED.created_by, updated_at = now()
             RETURNING ultimo_numero',
            [$documentoType, $formato->serie, $ambito, $periodo, $actor],
        )->ultimo_numero;

        return $formato->numero($periodo, (int) $correlativo, $ambito);
    }
}
