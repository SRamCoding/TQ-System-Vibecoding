<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Services;

use App\Modules\Nucleo\Excepciones\ContactoRechazado;
use App\Modules\Nucleo\Excepciones\PartyRechazada;
use App\Modules\Nucleo\Excepciones\VersionDesactualizada;
use App\Modules\Nucleo\Models\Party;
use App\Modules\Nucleo\Models\PartyContacto;
use App\Modules\Nucleo\Models\PartyRedSocial;
use App\Modules\Nucleo\Models\PartySede;
use App\Modules\Nucleo\Parties\DatosContacto;
use App\Modules\Nucleo\Tiempo\Calendario;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * M1 · Escritura de los contactos de una party (plan 1.3b, D1, D2 y D7). Interno del núcleo: los demás módulos
 * escriben por las acciones registradas (`nucleo.parties.…`) y leen por PartiesService.
 *
 * Un contacto es un vínculo: esta persona en esta organización. Cuando rota no se borra: se cierra su vínculo y
 * se abre otro en la nueva institución, enlazado al anterior. Un solo principal por party, siempre vigente:
 * marcar otro desmarca al anterior en la misma transacción, con la party bloqueada para que dos personas no lo
 * hagan a la vez; si aun así choca, la base lo impide y se avisa con un mensaje de negocio.
 */
final class ContactosPartyService
{
    /**
     * @throws ContactoRechazado|PartyRechazada
     */
    public function agregar(int $partyId, DatosContacto $datos): int
    {
        return DB::transaction(function () use ($partyId, $datos): int {
            $party = $this->bloquearParty($partyId);

            if (! $party->es_activo) {
                throw ContactoRechazado::partyInactiva();
            }

            $contacto = new PartyContacto(['party_id' => $party->id]);
            $this->llenar($contacto, $datos);
            $contacto->hasta = $datos->hasta === null ? null : self::cierreNoFuturo($datos->hasta);
            $this->validarFechas($contacto);

            if ($datos->esPrincipal) {
                if ($datos->hasta !== null) {
                    throw ContactoRechazado::principalCerrado();
                }

                $this->desmarcarPrincipal($party->id);
                $contacto->es_principal = true;
            }

            $this->guardarCuidandoPrincipal($contacto);

            return $contacto->id;
        });
    }

    /**
     * Reemplaza los datos del contacto: lo que llega vacío se borra (el formulario manda lo que se ve). Ni
     * `hasta` ni «principal»: tienen su operación.
     *
     * @throws ContactoRechazado|VersionDesactualizada
     */
    public function editar(int $id, DatosContacto $datos, int $versionLeida): void
    {
        DB::transaction(function () use ($id, $datos, $versionLeida): void {
            $contacto = $this->bloquear($id, $versionLeida);
            $this->llenar($contacto, $datos);
            $this->validarFechas($contacto);
            $this->guardarConNuevaVersion($contacto);
        });
    }

    /**
     * Lo hace el contacto principal de su party; el que lo era deja de serlo.
     *
     * @throws ContactoRechazado|VersionDesactualizada
     */
    public function marcarPrincipal(int $id, int $versionLeida): void
    {
        DB::transaction(function () use ($id, $versionLeida): void {
            $partyId = PartyContacto::query()->whereKey($id)->value('party_id') ?? throw ContactoRechazado::noExiste();
            $this->bloquearParty((int) $partyId);
            $contacto = $this->bloquear($id, $versionLeida);

            if ($contacto->hasta !== null) {
                throw ContactoRechazado::vinculoCerrado();
            }

            if ($contacto->es_principal) {
                throw ContactoRechazado::yaEsPrincipal();
            }

            $this->desmarcarPrincipal($contacto->party_id);
            $contacto->es_principal = true;
            $contacto->version++;
            $this->guardarCuidandoPrincipal($contacto);
        });
    }

    /**
     * Cierra el vínculo: `hasta` es su último día en la organización. Deja de ser principal y sale de las sedes
     * que lo tenían como contacto, en la misma transacción (D2 y D4).
     *
     * @throws ContactoRechazado|VersionDesactualizada
     */
    public function cerrarVinculo(int $id, CarbonImmutable $hasta, int $versionLeida): void
    {
        DB::transaction(function () use ($id, $hasta, $versionLeida): void {
            $this->cerrar($this->bloquear($id, $versionLeida), $hasta);
        });
    }

    /**
     * La persona cambió de institución: cierra este vínculo (si sigue abierto) y abre uno nuevo en la otra party,
     * enlazado a este. Los datos nuevos llegan precargados desde la pantalla y se pueden cambiar (el correo
     * institucional suele cambiar); el cargo nuevo es obligatorio. Sin `desde`, el día siguiente al cierre.
     * Un vínculo ya cerrado también se puede trasladar (se supo después a dónde fue): entonces no hace falta `hasta`.
     *
     * @return int el id del vínculo nuevo
     *
     * @throws ContactoRechazado|VersionDesactualizada
     */
    public function trasladar(int $id, int $nuevaPartyId, ?CarbonImmutable $hasta, DatosContacto $nuevos, int $versionLeida): int
    {
        if (self::texto($nuevos->cargo) === null) {
            throw ContactoRechazado::faltaCargoNuevo();
        }

        return DB::transaction(function () use ($id, $nuevaPartyId, $hasta, $nuevos, $versionLeida): int {
            // Mismo orden que marcarPrincipal() y agregar(): primero las parties (por id), luego el contacto.
            $origenId = (int) (PartyContacto::query()->whereKey($id)->value('party_id') ?? throw ContactoRechazado::noExiste());
            $parties = Party::query()->whereKey([$origenId, $nuevaPartyId])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $anterior = $this->bloquear($id, $versionLeida);

            if ($anterior->party_id === $nuevaPartyId) {
                throw ContactoRechazado::trasladoMismaParty();
            }

            if (PartyContacto::query()->where('contacto_anterior_id', $anterior->id)->exists()) {
                throw ContactoRechazado::yaTrasladado();
            }

            $destino = $parties->get($nuevaPartyId) ?? throw PartyRechazada::noExiste();

            if (! $destino->es_activo) {
                throw ContactoRechazado::destinoInactivo();
            }

            if ($anterior->hasta === null) {
                $this->cerrar($anterior, $hasta ?? throw ContactoRechazado::faltaFechaDeCierre());
            }

            $nuevo = new PartyContacto(['party_id' => $destino->id, 'contacto_anterior_id' => $anterior->id]);
            $this->llenar($nuevo, $nuevos);
            $nuevo->desde = $nuevos->desde ?? $anterior->hasta?->addDay();
            $this->validarFechas($nuevo);

            if ($nuevos->esPrincipal) {
                $this->desmarcarPrincipal($destino->id);
                $nuevo->es_principal = true;
            }

            $this->guardarCuidandoPrincipal($nuevo);

            // Las redes son de la persona, no del vínculo: la siguen a su nuevo lugar (plan 1.4o).
            PartyRedSocial::query()->where('contacto_id', $anterior->id)->orderBy('id')->get()
                ->each(fn (PartyRedSocial $red): bool => (new PartyRedSocial([
                    'party_id' => $destino->id, 'contacto_id' => $nuevo->id, 'red_social_id' => $red->red_social_id, 'enlace' => $red->enlace,
                ]))->save());

            return $nuevo->id;
        });
    }

    /**
     * Borra un contacto que nunca se usó. Si una sede, un traslado u otro registro lo referencia, la llave
     * foránea lo impide y se sugiere cerrar su vínculo.
     *
     * @throws ContactoRechazado|VersionDesactualizada
     */
    public function borrar(int $id, int $versionLeida): void
    {
        DB::transaction(function () use ($id, $versionLeida): void {
            $contacto = $this->bloquear($id, $versionLeida);

            try {
                // Sus redes son suyas: se van con él (plan 1.4o).
                DB::transaction(function () use ($contacto): void {
                    PartyRedSocial::query()->where('contacto_id', $contacto->id)->get()->each(fn (PartyRedSocial $red): ?bool => $red->delete());
                    $contacto->delete();
                });
            } catch (QueryException $e) {
                // 23503: foreign_key_violation.
                throw ($e->errorInfo[0] ?? null) === '23503' ? ContactoRechazado::enUso() : $e;
            }
        });
    }

    private function cerrar(PartyContacto $contacto, CarbonImmutable $hasta): void
    {
        if ($contacto->hasta !== null) {
            throw ContactoRechazado::vinculoCerrado();
        }

        $contacto->hasta = self::cierreNoFuturo($hasta);
        $this->validarFechas($contacto);
        $contacto->es_principal = false;
        $contacto->version++;
        $contacto->save();

        // Por modelo, no con un update masivo: cada sede deja su bitácora (codigo.md).
        PartySede::query()->where('contacto_id', $contacto->id)->lockForUpdate()->get()
            ->each(function (PartySede $sede): void {
                $sede->contacto_id = null;
                $sede->version++;
                $sede->save();
            });
    }

    private function llenar(PartyContacto $contacto, DatosContacto $datos): void
    {
        $nombres = self::texto($datos->nombres);
        $apellidos = self::texto($datos->apellidos);

        if ($nombres === null || $apellidos === null) {
            throw ContactoRechazado::faltanNombres();
        }

        $email = self::texto($datos->email);

        if ($email !== null) {
            $email = mb_strtolower($email);

            if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                throw ContactoRechazado::emailInvalido();
            }
        }

        $contacto->fill([
            'nombres' => $nombres,
            'apellidos' => $apellidos,
            'cargo' => self::texto($datos->cargo),
            'area' => self::texto($datos->area),
            'telefono' => self::texto($datos->telefono),
            'email' => $email,
            'es_decisor' => $datos->esDecisor,
            'desde' => $datos->desde?->startOfDay(),
        ]);
    }

    private function validarFechas(PartyContacto $contacto): void
    {
        if ($contacto->desde !== null && $contacto->hasta !== null && $contacto->hasta->lt($contacto->desde)) {
            throw ContactoRechazado::hastaAntesDeDesde();
        }
    }

    /** Por modelo, para que quede en la bitácora que dejó de ser el principal. */
    private function desmarcarPrincipal(int $partyId): void
    {
        PartyContacto::query()->where('party_id', $partyId)->where('es_principal', true)->lockForUpdate()->get()
            ->each(function (PartyContacto $actual): void {
                $actual->es_principal = false;
                $actual->version++;
                $actual->save();
            });
    }

    /**
     * Guarda en su propio punto de guardado y traduce el choque del índice de principal (dos personas a la vez,
     * aunque el bloqueo de la party lo hace improbable) a un mensaje de negocio.
     */
    private function guardarCuidandoPrincipal(PartyContacto $contacto): void
    {
        try {
            DB::transaction(fn (): bool => $contacto->save());
        } catch (QueryException $e) {
            // 23505: unique_violation en party_contactos_un_principal_unique.
            $esPrincipal = ($e->errorInfo[0] ?? null) === '23505' && str_contains($e->getMessage(), 'party_contactos_un_principal_unique');

            throw $esPrincipal ? ContactoRechazado::principalCambiado() : $e;
        }
    }

    /** La fila de la party bloqueada: el punto donde se ordenan los cambios de «principal». */
    private function bloquearParty(int $partyId): Party
    {
        return Party::query()->lockForUpdate()->find($partyId) ?? throw PartyRechazada::noExiste();
    }

    private function bloquear(int $id, int $versionLeida): PartyContacto
    {
        $contacto = PartyContacto::query()->lockForUpdate()->find($id) ?? throw ContactoRechazado::noExiste();

        if ($contacto->version !== $versionLeida) {
            throw VersionDesactualizada::de('este contacto');
        }

        return $contacto;
    }

    private function guardarConNuevaVersion(PartyContacto $contacto): void
    {
        if (! $contacto->isDirty()) {
            return;
        }

        $contacto->version++;
        $contacto->save();
    }

    /** Cerrar un vínculo registra algo que ya pasó: nunca después de hoy, en Lima (revisión de la 1.3b). */
    private static function cierreNoFuturo(CarbonImmutable $hasta): CarbonImmutable
    {
        $hasta = $hasta->startOfDay();

        if ($hasta->gt(Calendario::ahora()->startOfDay())) {
            throw ContactoRechazado::cierreFuturo();
        }

        return $hasta;
    }

    /** Texto sin espacios alrededor; vacío es nulo. */
    private static function texto(?string $valor): ?string
    {
        $texto = trim((string) $valor);

        return $texto === '' ? null : $texto;
    }
}
