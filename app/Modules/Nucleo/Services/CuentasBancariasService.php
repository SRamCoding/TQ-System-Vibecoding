<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Services;

use App\Modules\Nucleo\Enums\Catalogo;
use App\Modules\Nucleo\Events\CuentaBancariaActivada;
use App\Modules\Nucleo\Events\CuentaBancariaDesactivada;
use App\Modules\Nucleo\Events\CuentaBancariaRegistrada;
use App\Modules\Nucleo\Excepciones\CatalogoNoEncontrado;
use App\Modules\Nucleo\Excepciones\CuentaBancariaRechazada;
use App\Modules\Nucleo\Excepciones\PartyRechazada;
use App\Modules\Nucleo\Excepciones\VersionDesactualizada;
use App\Modules\Nucleo\Models\Party;
use App\Modules\Nucleo\Models\PartyCuentaBancaria;
use App\Modules\Nucleo\Parties\DatosCuentaBancaria;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * M1 · Escritura de las cuentas bancarias de una party (plan 1.3b, D2, D5 y D7). Interno del núcleo: los demás
 * módulos escriben por las acciones registradas y leen por PartiesService (`cuentaPrincipal`, `cuentasDePago`,
 * `cuentaDetracciones`).
 *
 * Protección contra fraude: banco, número y moneda no cambian (se desactiva y se registra otra); el CCI solo se
 * completa si estaba vacío; la misma cuenta solo puede estar en otra party si es la misma persona (pareja DNI ↔
 * RUC 10), lo que garantiza la base. La de detracciones es del Banco de la Nación, en moneda local, una activa por
 * party, y nunca principal. Registrar, desactivar o reactivar emite un evento (hoy sin oyente).
 */
final class CuentasBancariasService
{
    public function __construct(
        private readonly CatalogosService $catalogos,
        private readonly ParametrosService $parametros,
        private readonly PartiesService $parties,
    ) {}

    /**
     * @throws CuentaBancariaRechazada|PartyRechazada
     */
    public function registrar(int $partyId, DatosCuentaBancaria $datos): int
    {
        $numero = PartyCuentaBancaria::normalizarNumero($datos->numeroCuenta);

        if (preg_match('/^[0-9]{1,30}$/', $numero) !== 1) {
            throw CuentaBancariaRechazada::numeroInvalido();
        }

        $cci = self::cci($datos->cci);
        $monedaId = $this->monedaId($datos->codigoMoneda);

        if ($datos->esDetraccion && $datos->esPrincipal) {
            throw CuentaBancariaRechazada::detraccionPrincipal();
        }

        return DB::transaction(function () use ($partyId, $datos, $numero, $cci, $monedaId): int {
            $party = $this->bloquearParty($partyId);

            if (! $party->es_activo) {
                throw CuentaBancariaRechazada::partyInactiva();
            }

            $banco = $this->bancoVigente($datos->bancoPartyId);

            if ($datos->esDetraccion) {
                $this->validarDetraccion($banco, $datos->codigoMoneda);
            }

            if ($datos->esPrincipal) {
                $this->desmarcarPrincipal($party->id, $monedaId);
            }

            $cuenta = new PartyCuentaBancaria([
                'party_id' => $party->id,
                'banco_party_id' => $banco->id,
                'numero_cuenta' => $numero,
                'cci' => $cci,
                'moneda_id' => $monedaId,
                'es_detraccion' => $datos->esDetraccion,
                'es_principal' => $datos->esPrincipal,
                'es_activa' => true,
            ]);
            $this->guardar($cuenta);

            CuentaBancariaRegistrada::dispatch($cuenta->id, $party->id, $cuenta->created_by);

            return $cuenta->id;
        });
    }

    /**
     * Solo si estaba vacío: un CCI registrado no cambia (D5).
     *
     * @throws CuentaBancariaRechazada|VersionDesactualizada
     */
    public function completarCci(int $id, string $cci, int $versionLeida): void
    {
        $cci = self::cci($cci) ?? throw CuentaBancariaRechazada::cciInvalido();

        DB::transaction(function () use ($id, $cci, $versionLeida): void {
            $cuenta = $this->bloquear($id, $versionLeida);

            if ($cuenta->cci !== null) {
                throw CuentaBancariaRechazada::cciYaRegistrado();
            }

            $cuenta->cci = $cci;
            $cuenta->version++;
            $this->guardar($cuenta);
        });
    }

    /**
     * La hace la principal de su moneda; la que lo era deja de serlo. Nunca la de detracciones.
     *
     * @throws CuentaBancariaRechazada|VersionDesactualizada
     */
    public function marcarPrincipal(int $id, int $versionLeida): void
    {
        DB::transaction(function () use ($id, $versionLeida): void {
            $partyId = PartyCuentaBancaria::query()->whereKey($id)->value('party_id') ?? throw CuentaBancariaRechazada::noExiste();
            $this->bloquearParty((int) $partyId);
            $cuenta = $this->bloquear($id, $versionLeida);

            match (true) {
                $cuenta->es_detraccion => throw CuentaBancariaRechazada::detraccionPrincipal(),
                ! $cuenta->es_activa => throw CuentaBancariaRechazada::inactiva(),
                $cuenta->es_principal => throw CuentaBancariaRechazada::yaEsPrincipal(),
                default => null,
            };

            $this->desmarcarPrincipal($cuenta->party_id, $cuenta->moneda_id);
            $cuenta->es_principal = true;
            $cuenta->version++;
            $this->guardar($cuenta);
        });
    }

    /**
     * Deja de usarse para pagar o cobrar; lo ya registrado la conserva. Pierde la marca de principal.
     *
     * @throws CuentaBancariaRechazada|VersionDesactualizada
     */
    public function desactivar(int $id, int $versionLeida): void
    {
        DB::transaction(function () use ($id, $versionLeida): void {
            $cuenta = $this->bloquear($id, $versionLeida);

            if (! $cuenta->es_activa) {
                throw CuentaBancariaRechazada::yaEstaEnEseEstado(false);
            }

            $cuenta->es_activa = false;
            $cuenta->es_principal = false;
            $cuenta->version++;
            $this->guardar($cuenta);

            CuentaBancariaDesactivada::dispatch($cuenta->id, $cuenta->party_id, (int) $cuenta->updated_by);
        });
    }

    /**
     * Vuelve a recibir pagos: también es un punto de fraude, por eso emite su evento. Una de detracciones solo si
     * no hay otra activa (lo garantiza la base) y si su banco sigue siendo el de detracciones.
     *
     * @throws CuentaBancariaRechazada|VersionDesactualizada
     */
    public function activar(int $id, int $versionLeida): void
    {
        DB::transaction(function () use ($id, $versionLeida): void {
            $cuenta = $this->bloquear($id, $versionLeida);

            if ($cuenta->es_activa) {
                throw CuentaBancariaRechazada::yaEstaEnEseEstado(true);
            }

            if ($cuenta->es_detraccion) {
                $this->validarDetraccion($this->bancoVigente($cuenta->banco_party_id), $this->catalogos->codigoDe(Catalogo::Monedas, $cuenta->moneda_id));
            }

            $cuenta->es_activa = true;
            $cuenta->version++;
            $this->guardar($cuenta);

            CuentaBancariaActivada::dispatch($cuenta->id, $cuenta->party_id, (int) $cuenta->updated_by);
        });
    }

    /**
     * Borra una cuenta que nunca se usó; si un pago o un cobro la referencia, la llave foránea lo impide.
     *
     * @throws CuentaBancariaRechazada|VersionDesactualizada
     */
    public function borrar(int $id, int $versionLeida): void
    {
        DB::transaction(function () use ($id, $versionLeida): void {
            $cuenta = $this->bloquear($id, $versionLeida);

            try {
                DB::transaction(fn (): ?bool => $cuenta->delete());
            } catch (QueryException $e) {
                // 23503: foreign_key_violation.
                throw ($e->errorInfo[0] ?? null) === '23503' ? CuentaBancariaRechazada::enUso() : $e;
            }
        });
    }

    /** El banco: una party activa con el rol `banco` vigente hoy. */
    private function bancoVigente(int $bancoPartyId): Party
    {
        $banco = Party::query()->find($bancoPartyId);

        if ($banco === null || ! $banco->es_activo || ! $this->parties->tieneRol($banco->id, 'banco')) {
            throw CuentaBancariaRechazada::bancoNoDisponible();
        }

        return $banco;
    }

    /** Solo en el Banco de la Nación (`parties.ruc_banco_detracciones`) y en moneda local. */
    private function validarDetraccion(Party $banco, string $codigoMoneda): void
    {
        if ($banco->numero_documento !== $this->parametros->rucBancoDetracciones()) {
            throw CuentaBancariaRechazada::detraccionFueraDelBanco();
        }

        if ($codigoMoneda !== $this->parametros->monedaLocal()) {
            throw CuentaBancariaRechazada::detraccionEnOtraMoneda();
        }
    }

    private function monedaId(string $codigo): int
    {
        try {
            return $this->catalogos->idPorCodigo(Catalogo::Monedas, $codigo);
        } catch (CatalogoNoEncontrado) {
            throw CuentaBancariaRechazada::monedaNoDisponible($codigo);
        }
    }

    /** Por modelo, para que quede en la bitácora que dejó de ser la principal. */
    private function desmarcarPrincipal(int $partyId, int $monedaId): void
    {
        PartyCuentaBancaria::query()->where('party_id', $partyId)->where('moneda_id', $monedaId)->where('es_principal', true)
            ->lockForUpdate()->get()
            ->each(function (PartyCuentaBancaria $actual): void {
                $actual->es_principal = false;
                $actual->version++;
                $actual->save();
            });
    }

    /** Guarda en su propio punto de guardado y traduce lo que la base rechaza a un mensaje de negocio. */
    private function guardar(PartyCuentaBancaria $cuenta): void
    {
        try {
            DB::transaction(fn (): bool => $cuenta->save());
        } catch (QueryException $e) {
            $codigo = $e->errorInfo[0] ?? null;
            $mensaje = $e->getMessage();

            throw match (true) {
                // 23P01: exclusion_violation (la misma cuenta o CCI en otra persona).
                $codigo === '23P01' => CuentaBancariaRechazada::deOtraPersona(),
                // 23505: unique_violation.
                $codigo === '23505' && str_contains($mensaje, 'party_cuentas_bancarias_cuenta_unique') => CuentaBancariaRechazada::yaRegistrada(),
                $codigo === '23505' && str_contains($mensaje, 'party_cuentas_bancarias_cci_unique') => CuentaBancariaRechazada::cciRepetido(),
                $codigo === '23505' && str_contains($mensaje, 'party_cuentas_bancarias_detraccion_unique') => CuentaBancariaRechazada::yaTieneDetraccion(),
                $codigo === '23505' && str_contains($mensaje, 'party_cuentas_bancarias_principal_unique') => CuentaBancariaRechazada::principalCambiada(),
                default => $e,
            };
        }
    }

    /** La fila de la party bloqueada: el punto donde se ordenan los cambios de «principal». */
    private function bloquearParty(int $partyId): Party
    {
        return Party::query()->lockForUpdate()->find($partyId) ?? throw PartyRechazada::noExiste();
    }

    private function bloquear(int $id, int $versionLeida): PartyCuentaBancaria
    {
        $cuenta = PartyCuentaBancaria::query()->lockForUpdate()->find($id) ?? throw CuentaBancariaRechazada::noExiste();

        if ($cuenta->version !== $versionLeida) {
            throw VersionDesactualizada::de('esta cuenta bancaria');
        }

        return $cuenta;
    }

    /**
     * Solo dígitos, exactamente 20, y sus dígitos de control (hoy sin revisar: `PartyCuentaBancaria::cciValido`).
     * Vacío es nulo.
     */
    private static function cci(?string $cci): ?string
    {
        $cci = PartyCuentaBancaria::normalizarNumero((string) $cci);

        if ($cci === '') {
            return null;
        }

        if (preg_match('/^[0-9]{20}$/', $cci) !== 1) {
            throw CuentaBancariaRechazada::cciInvalido();
        }

        if (! PartyCuentaBancaria::cciValido($cci)) {
            throw CuentaBancariaRechazada::cciNoCorresponde();
        }

        return $cci;
    }
}
