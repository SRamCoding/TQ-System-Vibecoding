<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Parties;

use App\Modules\Nucleo\Enums\TipoPersona;
use App\Modules\Nucleo\Models\Party;
use App\Modules\Nucleo\RegistroParametros\ValidadorParametro;

/**
 * `empresa.ficha` (plan 1.13, D2): el RUC de la ficha de Techquk en Personas y empresas, o «sin ficha» mientras no se
 * elija. La ficha ya trae lo demás con sus reglas (razón social, dirección fiscal, sedes, cuentas bancarias). Tiene que
 * ser una empresa activa con ese RUC: un parámetro de texto no puede quedar vacío, por eso la marca.
 */
final readonly class ValidadorFichaEmpresa implements ValidadorParametro
{
    public const string SIN_FICHA = 'sin ficha';

    public function normalizar(mixed $valor): string
    {
        $valor = mb_strtolower(trim((string) $valor));

        return in_array($valor, ['', '-', '--', 'ninguna', self::SIN_FICHA], true) ? self::SIN_FICHA : preg_replace('/\D/', '', $valor);
    }

    public function rechazo(mixed $valor): ?string
    {
        if ($valor === self::SIN_FICHA) {
            return null;
        }

        // Con bloqueo compartido: ParametrosService valida dentro de su transacción, y así quien desactiva la ficha a la
        // vez espera, o se ve que ya está inactiva (plan 1.15, D3).
        return self::ficha((string) $valor, bloquear: true) !== null
            ? null
            : 'escribe el RUC de una empresa activa registrada en Personas y empresas, o «sin ficha».';
    }

    /** La ficha activa de empresa con ese RUC, o null. */
    public static function ficha(string $ruc, bool $bloquear = false): ?Party
    {
        if (preg_match('/^\d{11}$/', $ruc) !== 1) {
            return null;
        }

        return Party::query()->where('tipo', TipoPersona::Juridica)->where('numero_documento', $ruc)->where('es_activo', true)
            ->when($bloquear, fn ($consulta) => $consulta->sharedLock())
            ->first();
    }
}
