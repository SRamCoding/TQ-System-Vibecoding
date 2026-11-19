<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Acciones\Parties;

use App\Modules\Nucleo\Enums\Catalogo;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Parties\DatosCuentaBancaria;
use App\Modules\Nucleo\RegistroAcciones\Campo;
use App\Modules\Nucleo\Services\CatalogosService;
use App\Modules\Nucleo\Services\CuentasBancariasService;

/**
 * Registra una cuenta. Banco, número y moneda no cambian después (D5): si cambian, se desactiva y se registra
 * otra. Devuelve su id.
 */
final class RegistrarCuentaBancaria extends AccionCuentaBancaria
{
    public function __construct(CuentasBancariasService $cuentas, private readonly CatalogosService $catalogos)
    {
        parent::__construct($cuentas);
    }

    protected function operacion(): string
    {
        return 'registrar-cuenta-bancaria';
    }

    public function nombre(): string
    {
        return 'Registrar cuenta bancaria';
    }

    public function campos(): array
    {
        return [
            Campo::entero('party_id', 'Persona o empresa')->conReglas('exists:parties,id'),
            Campo::entero('banco_party_id', 'Banco')->conReglas('exists:parties,id'),
            Campo::texto('numero_cuenta', 'Número de cuenta', maximo: 40),
            Campo::elementoDe('moneda_id', 'Moneda', Catalogo::Monedas),
            Campo::texto('cci', 'CCI', maximo: 30)->opcional(),
            Campo::booleano('es_detraccion', 'Cuenta de detracciones (Banco de la Nación)')->opcional(),
            Campo::booleano('es_principal', 'Principal en su moneda')->opcional(),
        ];
    }

    public function ejecutar(array $datos, User $usuario): mixed
    {
        return $this->cuentas->registrar($datos['party_id'], new DatosCuentaBancaria(
            $datos['banco_party_id'],
            $datos['numero_cuenta'],
            $this->catalogos->codigoDe(Catalogo::Monedas, $datos['moneda_id']),
            cci: $datos['cci'] ?? null,
            esDetraccion: (bool) ($datos['es_detraccion'] ?? false),
            esPrincipal: (bool) ($datos['es_principal'] ?? false),
        ));
    }
}
