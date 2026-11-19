<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Services;

use App\Modules\Nucleo\Enums\Catalogo;
use App\Modules\Nucleo\Excepciones\PartyRechazada;
use App\Modules\Nucleo\Excepciones\RedSocialRechazada;
use App\Modules\Nucleo\Excepciones\VersionDesactualizada;
use App\Modules\Nucleo\Models\Party;
use App\Modules\Nucleo\Models\PartyContacto;
use App\Modules\Nucleo\Models\PartyRedSocial;
use App\Modules\Nucleo\Models\RedSocial;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Parties\DatosRedSocial;
use App\Modules\Nucleo\Parties\ResumenRedSocial;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * M1 · Las redes sociales de una party o de uno de sus contactos (plan 1.4o, D1 a D3). Interno del núcleo: los demás
 * módulos escriben por las acciones registradas y leen por redesQueVe().
 *
 * El enlace se revisa contra los dominios de la red del catálogo (también sus subdominios: `www.`, `pe.`); la red sin
 * dominios, como el sitio web, acepta cualquiera. Se guarda con el esquema y el dominio en minúsculas, y sin `https://`
 * se lo pone. Una red privada (D3) solo la ve la persona del equipo dueña; las demás, todo el equipo.
 *
 * Quién escribe (D2): sin `$propia`, desde la ficha de una party, y nunca las de una persona del equipo; con `$propia`,
 * la persona sobre las suyas, nunca sobre las de otra ni las de un contacto.
 */
final class RedesSocialesService
{
    /**
     * @return int el id de la red agregada
     *
     * @throws RedSocialRechazada|PartyRechazada
     */
    public function agregar(int $partyId, ?int $contactoId, DatosRedSocial $datos, ?User $propia = null): int
    {
        return DB::transaction(function () use ($partyId, $contactoId, $datos, $propia): int {
            $party = Party::query()->lockForUpdate()->find($partyId) ?? throw PartyRechazada::noExiste();
            $this->exigirQuePuedaEscribir($partyId, $contactoId, $propia);

            if (! $party->es_activo) {
                throw RedSocialRechazada::partyInactiva();
            }

            if ($contactoId !== null && ! PartyContacto::query()->whereKey($contactoId)->where('party_id', $partyId)->vigentes()->exists()) {
                throw RedSocialRechazada::contactoNoDisponible();
            }

            $red = new PartyRedSocial(['party_id' => $partyId, 'contacto_id' => $contactoId]);
            $this->llenar($red, $datos);
            $this->guardar($red);

            return $red->id;
        });
    }

    /**
     * Cambia la red, el enlace y la visibilidad, todo a la vez, como están en el formulario.
     *
     * @throws RedSocialRechazada|VersionDesactualizada
     */
    public function cambiar(int $id, DatosRedSocial $datos, int $versionLeida, ?User $propia = null): void
    {
        DB::transaction(function () use ($id, $datos, $versionLeida, $propia): void {
            $red = $this->bloquear($id, $versionLeida, $propia);
            $this->llenar($red, $datos);

            if ($red->isDirty()) {
                $red->version++;
                $this->guardar($red);
            }
        });
    }

    /**
     * Nada la referencia: se borra (queda en la bitácora de la ficha).
     *
     * @throws RedSocialRechazada|VersionDesactualizada
     */
    public function quitar(int $id, int $versionLeida, ?User $propia = null): void
    {
        DB::transaction(fn (): ?bool => $this->bloquear($id, $versionLeida, $propia)->delete());
    }

    /**
     * Punto de consulta (D3): las redes de la party (sin `$contactoId`) o de ese contacto que `$quienMira` puede ver,
     * en el orden del catálogo. El directorio del equipo y las fichas leen por aquí. Sin `$quienMira`, solo las
     * públicas: las que imprime un documento (plan 1.15).
     *
     * @return list<ResumenRedSocial>
     */
    public function redesQueVe(int $partyId, ?int $contactoId, ?User $quienMira): array
    {
        $veTodas = $quienMira !== null && $quienMira->party_id === $partyId;

        return PartyRedSocial::query()
            ->with('redSocial')
            ->where('party_id', $partyId)
            ->when($contactoId === null, fn ($consulta) => $consulta->whereNull('contacto_id'), fn ($consulta) => $consulta->where('contacto_id', $contactoId))
            ->when(! $veTodas, fn ($consulta) => $consulta->where('es_privada', false))
            ->orderBy('red_social_id')
            ->orderBy('id')
            ->get()
            ->map(fn (PartyRedSocial $r): ResumenRedSocial => new ResumenRedSocial(
                $r->id, $r->contacto_id, $r->red_social_id, $r->nombreDeLaRed(), $r->redSocial->icono->componente(), $r->enlace, $r->es_privada, $r->version,
            ))
            ->values()
            ->all();
    }

    /** Si la party es de una persona del equipo (tiene cuenta): sus redes las cambia ella misma (D2). */
    public function esDelEquipo(int $partyId): bool
    {
        return User::query()->where('party_id', $partyId)->exists();
    }

    /**
     * Un enlace como se guarda: sin espacios alrededor, con `https://` si no trae esquema, y esquema y dominio en
     * minúsculas. Nulo si no es una dirección web.
     */
    public static function normalizar(string $enlace): ?string
    {
        $enlace = trim($enlace);

        if ($enlace === '' || preg_match('/\s/u', $enlace) === 1) {
            return null;
        }

        if (preg_match('~^[a-z][a-z0-9+.-]*://~i', $enlace) !== 1) {
            $enlace = 'https://'.$enlace;
        }

        $partes = parse_url($enlace);

        if ($partes === false) {
            return null;
        }

        $esquema = strtolower($partes['scheme'] ?? '');
        $dominio = strtolower(rtrim($partes['host'] ?? '', '.'));

        if (! in_array($esquema, ['http', 'https'], true) || isset($partes['user']) || preg_match('/^[a-z0-9-]+(\.[a-z0-9-]+)*\.[a-z0-9-]+$/', $dominio) !== 1) {
            return null;
        }

        $resto = substr($enlace, strlen("{$partes['scheme']}://{$partes['host']}"));

        return "{$esquema}://{$dominio}{$resto}";
    }

    /** Si el dominio del enlace es uno de `$dominios` o un subdominio suyo; sin dominios, cualquiera. */
    public static function esDeLaRed(string $enlaceNormalizado, ?string $dominios): bool
    {
        if ($dominios === null) {
            return true;
        }

        $dominio = (string) parse_url($enlaceNormalizado, PHP_URL_HOST);

        foreach (explode(',', $dominios) as $permitido) {
            if ($dominio === $permitido || str_ends_with($dominio, '.'.$permitido)) {
                return true;
            }
        }

        return false;
    }

    private function llenar(PartyRedSocial $red, DatosRedSocial $datos): void
    {
        $catalogo = RedSocial::query()->find($datos->redSocialId);

        // Una red que ya tenía se respeta aunque hoy esté desactivada en el catálogo.
        if ($catalogo === null || (! $catalogo->es_activo && $red->red_social_id !== $catalogo->id)) {
            throw RedSocialRechazada::redNoDisponible();
        }

        $enlace = self::normalizar($datos->enlace) ?? throw RedSocialRechazada::enlaceInvalido();

        if (! self::esDeLaRed($enlace, $catalogo->dominios)) {
            throw RedSocialRechazada::enlaceDeOtraRed($catalogo->nombre, str_replace(',', ' o ', $catalogo->dominios ?? ''));
        }

        if ($datos->esPrivada && ($red->contacto_id !== null || ! $this->esDelEquipo($red->party_id))) {
            throw RedSocialRechazada::privadaSoloDelEquipo();
        }

        // Plan 2.0, D5: con «Otra red», cuál es.
        $detalle = app(CatalogosService::class)->detalleDeOtro(Catalogo::RedesSociales, $catalogo->id, $datos->detalle, 'la red');

        $red->fill(['red_social_id' => $catalogo->id, 'detalle' => $detalle, 'enlace' => $enlace, 'es_privada' => $datos->esPrivada]);
    }

    /** Guarda en su propio punto de guardado y traduce el enlace repetido a un mensaje claro. */
    private function guardar(PartyRedSocial $red): void
    {
        try {
            DB::transaction(fn (): bool => $red->save());
        } catch (QueryException $e) {
            // 23505: unique_violation.
            throw ($e->errorInfo[0] ?? null) === '23505' && str_contains($e->getMessage(), 'party_redes_sociales_enlace_unique')
                ? RedSocialRechazada::enlaceRepetido() : $e;
        }
    }

    /**
     * D2: la persona, solo sobre las suyas (no las de otra ni las de un contacto); desde una ficha, nunca las de una
     * persona del equipo.
     */
    private function exigirQuePuedaEscribir(int $partyId, ?int $contactoId, ?User $propia): void
    {
        if ($propia !== null) {
            if ($propia->party_id === null) {
                throw PartyRechazada::cuentaSinFicha();
            }

            if ($propia->party_id !== $partyId || $contactoId !== null) {
                throw RedSocialRechazada::noEsTuya();
            }

            return;
        }

        if ($this->esDelEquipo($partyId)) {
            throw RedSocialRechazada::esDelEquipo();
        }
    }

    private function bloquear(int $id, int $versionLeida, ?User $propia): PartyRedSocial
    {
        $red = PartyRedSocial::query()->lockForUpdate()->find($id) ?? throw RedSocialRechazada::noExiste();
        $this->exigirQuePuedaEscribir($red->party_id, $red->contacto_id, $propia);

        if ($red->version !== $versionLeida) {
            throw VersionDesactualizada::de('la red social');
        }

        return $red;
    }
}
