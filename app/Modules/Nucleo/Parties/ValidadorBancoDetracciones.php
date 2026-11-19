<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Parties;

use App\Modules\Nucleo\Enums\Catalogo;
use App\Modules\Nucleo\Excepciones\DocumentoIdentidadInvalido;
use App\Modules\Nucleo\RegistroParametros\ValidadorParametro;
use App\Modules\Nucleo\Services\PartiesService;

/**
 * El RUC de `parties.ruc_banco_detracciones` (plan 1.3b, D5): dígito verificador correcto y una party registrada,
 * activa y con el rol `banco` vigente. Decide a dónde van las detracciones: no se acepta un banco que no existe.
 */
final readonly class ValidadorBancoDetracciones implements ValidadorParametro
{
    public function __construct(private PartiesService $parties) {}

    public function normalizar(mixed $valor): string
    {
        return DocumentoIdentidad::normalizar((string) $valor);
    }

    public function rechazo(mixed $valor): ?string
    {
        try {
            $documento = DocumentoIdentidad::de(Catalogo::RUC, (string) $valor, 11, 'RUC');
        } catch (DocumentoIdentidadInvalido $e) {
            return $e->getMessage();
        }

        $banco = $this->parties->buscarPorDocumento(Catalogo::RUC, $documento->numero);

        return match (true) {
            $banco === null => "no hay ninguna persona o empresa registrada con el RUC {$documento->numero}.",
            ! $banco->activa => "{$banco->etiqueta} está desactivada.",
            ! $this->parties->tieneRol($banco->id, 'banco') => "{$banco->etiqueta} no tiene el rol Banco vigente.",
            default => null,
        };
    }
}
