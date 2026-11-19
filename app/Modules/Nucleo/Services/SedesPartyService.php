<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Services;

use App\Modules\Nucleo\Enums\Catalogo;
use App\Modules\Nucleo\Excepciones\PartyRechazada;
use App\Modules\Nucleo\Excepciones\SedeRechazada;
use App\Modules\Nucleo\Excepciones\VersionDesactualizada;
use App\Modules\Nucleo\Models\Party;
use App\Modules\Nucleo\Models\PartyContacto;
use App\Modules\Nucleo\Models\PartySede;
use App\Modules\Nucleo\Parties\DatosSede;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * M1 · Escritura de las sedes de una party (plan 1.3b, D3, D4 y D7). Interno del núcleo: los demás módulos
 * escriben por las acciones registradas y leen por PartiesService.
 *
 * Una sede es un lugar operativo (sucursal, almacén, punto de entrega). Ni la dirección fiscal (es de la party)
 * ni la de una obra (es del proyecto, etapa 2). El nombre no se repite entre las activas sin contar mayúsculas
 * ni acentos, y el anexo SUNAT tampoco: la base lo garantiza y aquí se traduce a un mensaje claro.
 */
final class SedesPartyService
{
    public function __construct(private readonly CatalogosService $catalogos) {}

    /**
     * @throws SedeRechazada|PartyRechazada
     */
    public function agregar(int $partyId, DatosSede $datos): int
    {
        return DB::transaction(function () use ($partyId, $datos): int {
            $this->bloquearContacto($datos->contactoId);
            $party = Party::query()->find($partyId) ?? throw PartyRechazada::noExiste();

            if (! $party->es_activo) {
                throw SedeRechazada::partyInactiva();
            }

            $sede = new PartySede(['party_id' => $party->id, 'es_activa' => true]);
            $this->llenar($sede, $datos);
            $this->guardar($sede);

            return $sede->id;
        });
    }

    /**
     * Reemplaza los datos de la sede: lo que llega vacío se borra (el formulario manda lo que se ve).
     *
     * @throws SedeRechazada|VersionDesactualizada
     */
    public function editar(int $id, DatosSede $datos, int $versionLeida): void
    {
        DB::transaction(function () use ($id, $datos, $versionLeida): void {
            $this->bloquearContacto($datos->contactoId);
            $sede = $this->bloquear($id, $versionLeida);
            $this->llenar($sede, $datos);

            if ($sede->isDirty()) {
                $sede->version++;
                $this->guardar($sede);
            }
        });
    }

    /**
     * Deja de ofrecerse; lo ya registrado la conserva. Libera su nombre y su anexo para otra sede.
     *
     * @throws SedeRechazada|VersionDesactualizada
     */
    public function desactivar(int $id, int $versionLeida): void
    {
        $this->cambiarEstado($id, false, $versionLeida);
    }

    /**
     * Se rechaza si otra sede activa tomó su nombre o su anexo mientras estaba desactivada.
     *
     * @throws SedeRechazada|VersionDesactualizada
     */
    public function activar(int $id, int $versionLeida): void
    {
        $this->cambiarEstado($id, true, $versionLeida);
    }

    /**
     * Borra una sede que nunca se usó; si otro registro la referencia, la llave foránea lo impide.
     *
     * @throws SedeRechazada|VersionDesactualizada
     */
    public function borrar(int $id, int $versionLeida): void
    {
        DB::transaction(function () use ($id, $versionLeida): void {
            $sede = $this->bloquear($id, $versionLeida);

            try {
                DB::transaction(fn (): ?bool => $sede->delete());
            } catch (QueryException $e) {
                // 23503: foreign_key_violation.
                throw ($e->errorInfo[0] ?? null) === '23503' ? SedeRechazada::enUso() : $e;
            }
        });
    }

    private function cambiarEstado(int $id, bool $activa, int $versionLeida): void
    {
        DB::transaction(function () use ($id, $activa, $versionLeida): void {
            $sede = $this->bloquear($id, $versionLeida);

            if ($sede->es_activa === $activa) {
                throw SedeRechazada::yaEstaEnEseEstado($activa);
            }

            $sede->es_activa = $activa;
            $sede->version++;
            $this->guardar($sede);
        });
    }

    private function llenar(PartySede $sede, DatosSede $datos): void
    {
        $nombre = self::texto($datos->nombre);
        $direccion = self::texto($datos->direccion);

        if ($nombre === null || $direccion === null) {
            throw SedeRechazada::faltanDatos();
        }

        $codigo = self::texto($datos->codigoEstablecimiento);

        if ($codigo !== null && preg_match('/^[0-9]{4}$/', $codigo) !== 1) {
            throw SedeRechazada::codigoEstablecimientoInvalido();
        }

        [$latitud, $longitud] = self::coordenadas($datos->latitud, $datos->longitud);

        $sede->fill([
            'nombre' => $nombre,
            'direccion' => $direccion,
            'ubigeo_id' => $datos->ubigeoId,
            'latitud' => $latitud,
            'longitud' => $longitud,
            'contacto_id' => $datos->contactoId,
            'codigo_establecimiento' => $codigo,
        ]);

        // Un ubigeo o un contacto que ya tenía se respetan aunque hoy no se pudieran elegir.
        if ($sede->ubigeo_id !== null && $sede->isDirty('ubigeo_id') && ! $this->catalogos->estaActivo(Catalogo::Ubigeos, $sede->ubigeo_id)) {
            throw SedeRechazada::ubigeoNoDisponible();
        }

        if ($sede->contacto_id !== null && $sede->isDirty('contacto_id') && ! $this->contactoVigenteDe($sede->party_id, $sede->contacto_id)) {
            throw SedeRechazada::contactoNoDisponible();
        }
    }

    private function contactoVigenteDe(int $partyId, int $contactoId): bool
    {
        return PartyContacto::query()
            ->whereKey($contactoId)
            ->where('party_id', $partyId)
            ->vigentes()
            ->exists();
    }

    /** Guarda en su propio punto de guardado y traduce la unicidad del nombre y del anexo a un mensaje claro. */
    private function guardar(PartySede $sede): void
    {
        try {
            DB::transaction(fn (): bool => $sede->save());
        } catch (QueryException $e) {
            // 23505: unique_violation.
            if (($e->errorInfo[0] ?? null) !== '23505') {
                throw $e;
            }

            throw match (true) {
                str_contains($e->getMessage(), 'party_sedes_nombre_activa_unique') => SedeRechazada::nombreRepetido($sede->nombre),
                str_contains($e->getMessage(), 'party_sedes_codigo_establecimiento_unique') => SedeRechazada::codigoEstablecimientoRepetido((string) $sede->codigo_establecimiento),
                default => $e,
            };
        }
    }

    /**
     * Las dos o ninguna; como texto con 7 decimales como máximo (nunca `float`, regla 4).
     *
     * @return array{0: ?string, 1: ?string}
     */
    private static function coordenadas(?string $latitud, ?string $longitud): array
    {
        $latitud = self::texto($latitud);
        $longitud = self::texto($longitud);

        if (($latitud === null) !== ($longitud === null)) {
            throw SedeRechazada::coordenadasIncompletas();
        }

        if ($latitud === null || $longitud === null) {
            return [null, null];
        }

        return [self::coordenada($latitud, 90, 'latitud'), self::coordenada($longitud, 180, 'longitud')];
    }

    private static function coordenada(string $valor, int $limite, string $cual): string
    {
        $valor = str_replace(',', '.', $valor);

        if (preg_match('/^-?[0-9]{1,3}(\.[0-9]{1,7})?$/', $valor) !== 1 || bccomp(ltrim($valor, '-'), (string) $limite, 7) === 1) {
            throw SedeRechazada::coordenadaInvalida($cual, "-{$limite} y {$limite}");
        }

        return $valor;
    }

    /**
     * El contacto elegido, bloqueado antes que la sede: así nadie cierra su vínculo mientras se asigna (la otra mitad
     * la hace ContactosPartyService::cerrar(), que bloquea el contacto y luego sus sedes, en el mismo orden).
     */
    private function bloquearContacto(?int $contactoId): void
    {
        if ($contactoId !== null) {
            PartyContacto::query()->whereKey($contactoId)->lockForUpdate()->first();
        }
    }

    private function bloquear(int $id, int $versionLeida): PartySede
    {
        $sede = PartySede::query()->lockForUpdate()->find($id) ?? throw SedeRechazada::noExiste();

        if ($sede->version !== $versionLeida) {
            throw VersionDesactualizada::de("la sede «{$sede->nombre}»");
        }

        return $sede;
    }

    /** Texto sin espacios alrededor; vacío es nulo. */
    private static function texto(?string $valor): ?string
    {
        $texto = trim((string) $valor);

        return $texto === '' ? null : $texto;
    }
}
