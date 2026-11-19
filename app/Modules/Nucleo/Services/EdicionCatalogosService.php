<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Services;

use App\Modules\Nucleo\Enums\Catalogo;
use App\Modules\Nucleo\Enums\IconoAplicacion;
use App\Modules\Nucleo\Enums\OrigenUbigeo;
use App\Modules\Nucleo\Excepciones\ElementoCatalogoRechazado;
use App\Modules\Nucleo\Excepciones\VersionDesactualizada;
use App\Modules\Nucleo\Models\Aplicacion;
use App\Modules\Nucleo\Models\Concerns\ElementoCatalogo;
use App\Modules\Nucleo\Models\Feriado;
use App\Modules\Nucleo\Models\FraseSaludo;
use App\Modules\Nucleo\RegistroAcciones\Campo;
use App\Modules\Nucleo\RegistroAcciones\TipoCampo;
use App\Modules\Nucleo\Saludo\ReglasFrase;
use App\Modules\Nucleo\Tiempo\ReglasFeriado;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Escritura de los catálogos de M5 desde la pantalla (plan 1.2b). Los campos de cada catálogo los define
 * Catalogo (nombres = columnas). El elemento se identifica por su código, que no cambia (E8).
 *
 * Toda operación sobre un elemento existente lleva la versión que la persona leyó (bloqueo optimista) y
 * deja bitácora (EsCatalogo usa RegistraCambios). Lo que mueve dinero se cambia solo por
 * editarSensibles() (E6); el permiso lo exige la acción. Lo que usa el código del sistema (moneda local,
 * DNI, RUC, roles como `cliente`) no se desactiva ni se borra, y sus campos fijos no cambian (plan 1.3, D3).
 */
final class EdicionCatalogosService
{
    public function __construct(
        private readonly CatalogosService $catalogos,
        private readonly ParametrosService $parametros,
    ) {}

    /**
     * Agrega un elemento activo. Un ubigeo agregado a mano queda como manual (E7).
     *
     * @param  array<string, mixed>  $datos  campos del catálogo (Catalogo::campos()), ya convertidos
     *
     * @throws ElementoCatalogoRechazado
     */
    public function crear(Catalogo $catalogo, string $codigo, array $datos): int
    {
        if ($catalogo->esCerrado()) {
            throw ElementoCatalogoRechazado::catalogoCerrado($catalogo);
        }

        $permitidos = array_map(fn (Campo $campo): string => $campo->nombre, $catalogo->campos());
        $this->rechazarAjenos($catalogo, $datos, $permitidos);

        $modelo = $catalogo->modelo();
        /** @var Model&ElementoCatalogo $elemento */
        $elemento = new $modelo;
        $elemento->fill([...$datos, 'codigo' => $codigo]);

        if ($catalogo === Catalogo::Ubigeos) {
            $elemento->setAttribute('origen', OrigenUbigeo::Manual);
        }

        $this->validarReferencias($catalogo, $elemento);
        $this->validarFormato($elemento);

        try {
            $elemento->save();
        } catch (UniqueConstraintViolationException) {
            throw ElementoCatalogoRechazado::codigoDuplicado($catalogo, $codigo);
        }

        // Plan 2.0, D2: el selector deja elegido lo que se acaba de crear.
        return (int) $elemento->getKey();
    }

    /**
     * Cambia los campos generales. El código y los sensibles se rechazan.
     *
     * @param  array<string, mixed>  $datos
     *
     * @throws ElementoCatalogoRechazado|VersionDesactualizada
     */
    public function editar(Catalogo $catalogo, string $codigo, array $datos, int $versionLeida): void
    {
        $permitidos = array_values(array_diff(
            array_map(fn (Campo $campo): string => $campo->nombre, $catalogo->campos()),
            $catalogo->camposSensibles(),
        ));

        $this->cambiar($catalogo, $codigo, $datos, $permitidos, $versionLeida);
    }

    /**
     * Cambia solo los campos que mueven dinero (signo de un documento, decimales de una moneda).
     *
     * @param  array<string, mixed>  $datos
     *
     * @throws ElementoCatalogoRechazado|VersionDesactualizada
     */
    public function editarSensibles(Catalogo $catalogo, string $codigo, array $datos, int $versionLeida): void
    {
        $this->cambiar($catalogo, $codigo, $datos, $catalogo->camposSensibles(), $versionLeida);
    }

    /**
     * Deja de ofrecerse en los selectores; lo ya registrado lo conserva.
     *
     * @throws ElementoCatalogoRechazado|VersionDesactualizada
     */
    public function desactivar(Catalogo $catalogo, string $codigo, int $versionLeida): void
    {
        $this->cambiarEstado($catalogo, $codigo, false, $versionLeida);
    }

    /**
     * @throws ElementoCatalogoRechazado|VersionDesactualizada
     */
    public function activar(Catalogo $catalogo, string $codigo, int $versionLeida): void
    {
        $this->cambiarEstado($catalogo, $codigo, true, $versionLeida);
    }

    /**
     * Borra de verdad un elemento que nunca se usó (E1). Si algo lo referencia, la llave foránea lo
     * impide y se rechaza con la sugerencia de desactivarlo.
     *
     * @throws ElementoCatalogoRechazado|VersionDesactualizada
     */
    public function borrar(Catalogo $catalogo, string $codigo, int $versionLeida): void
    {
        DB::transaction(function () use ($catalogo, $codigo, $versionLeida): void {
            $elemento = $this->bloquear($catalogo, $codigo, $versionLeida);

            if ($catalogo->esCerrado()) {
                throw ElementoCatalogoRechazado::catalogoCerrado($catalogo);
            }

            $this->rechazarSiEsDelSistema($catalogo, $codigo);

            // En su propio punto de guardado: PostgreSQL invalida la transacción ante el error de la FK.
            try {
                DB::transaction(fn (): ?bool => $elemento->delete());
            } catch (QueryException $e) {
                // 23503: foreign_key_violation.
                throw ($e->errorInfo[0] ?? null) === '23503' ? ElementoCatalogoRechazado::enUso($catalogo, $codigo) : $e;
            }
        });
    }

    /**
     * @param  array<string, mixed>  $datos
     * @param  list<string>  $permitidos
     */
    private function cambiar(Catalogo $catalogo, string $codigo, array $datos, array $permitidos, int $versionLeida): void
    {
        $this->rechazarAjenos($catalogo, $datos, $permitidos);

        DB::transaction(function () use ($catalogo, $codigo, $datos, $versionLeida): void {
            $elemento = $this->bloquear($catalogo, $codigo, $versionLeida);
            $elemento->fill($datos);
            $this->rechazarCambioDeCampoFijo($catalogo, $codigo, $elemento);
            $this->validarReferencias($catalogo, $elemento);
            $this->validarFormato($elemento);
            $this->guardarConNuevaVersion($elemento);
        });
    }

    private function cambiarEstado(Catalogo $catalogo, string $codigo, bool $activo, int $versionLeida): void
    {
        DB::transaction(function () use ($catalogo, $codigo, $activo, $versionLeida): void {
            $elemento = $this->bloquear($catalogo, $codigo, $versionLeida);

            if (! $activo) {
                $this->rechazarSiEsDelSistema($catalogo, $codigo);
            }

            if ($elemento->estaActivo() === $activo) {
                throw ElementoCatalogoRechazado::yaEstaEnEseEstado($catalogo, $codigo, $activo);
            }

            $elemento->setAttribute($elemento::columnaActivo(), $activo);
            $this->guardarConNuevaVersion($elemento);
        });
    }

    /**
     * Lee el elemento con la fila bloqueada y confirma que sigue en la versión que la persona leyó.
     *
     * @return Model&ElementoCatalogo
     */
    private function bloquear(Catalogo $catalogo, string $codigo, int $versionLeida): Model
    {
        $modelo = $catalogo->modelo();
        $elemento = $modelo::query()->where('codigo', $codigo)->lockForUpdate()->first()
            ?? throw ElementoCatalogoRechazado::noExiste($catalogo, $codigo);

        if ((int) $elemento->getAttribute('version') !== $versionLeida) {
            throw VersionDesactualizada::de("«{$codigo}» de {$catalogo->nombre()}");
        }

        return $elemento;
    }

    /**
     * Si el código del sistema usa el elemento: la pantalla oculta desactivar y borrar en esas filas.
     */
    public function esDelSistema(Catalogo $catalogo, string $codigo): bool
    {
        return $this->esMonedaLocal($catalogo, $codigo) || $catalogo->esDelSistema($codigo);
    }

    private function esMonedaLocal(Catalogo $catalogo, string $codigo): bool
    {
        return $catalogo === Catalogo::Monedas && $codigo === $this->parametros->monedaLocal();
    }

    private function rechazarSiEsDelSistema(Catalogo $catalogo, string $codigo): void
    {
        if ($this->esMonedaLocal($catalogo, $codigo)) {
            throw ElementoCatalogoRechazado::monedaLocal($codigo);
        }

        if ($catalogo->esDelSistema($codigo)) {
            throw ElementoCatalogoRechazado::delSistema($catalogo, $codigo);
        }
    }

    /**
     * @param  Model&ElementoCatalogo  $elemento
     */
    private function rechazarCambioDeCampoFijo(Catalogo $catalogo, string $codigo, Model $elemento): void
    {
        if (! $catalogo->esDelSistema($codigo)) {
            return;
        }

        foreach ($catalogo->camposFijosDelSistema() as $campo) {
            if ($elemento->isDirty($campo)) {
                throw ElementoCatalogoRechazado::campoFijoDelSistema($catalogo, $codigo, $campo);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $datos
     * @param  list<string>  $permitidos
     */
    private function rechazarAjenos(Catalogo $catalogo, array $datos, array $permitidos): void
    {
        foreach (array_keys($datos) as $campo) {
            if (! in_array($campo, $permitidos, true)) {
                throw ElementoCatalogoRechazado::campoNoEditable($catalogo, $campo);
            }
        }
    }

    /**
     * Un elemento elegido de otro catálogo debe estar activo al elegirlo. Si no cambió, se respeta aunque
     * se haya desactivado después: editar otro campo no obliga a cambiarlo.
     *
     * @param  Model&ElementoCatalogo  $elemento
     */
    private function validarReferencias(Catalogo $catalogo, Model $elemento): void
    {
        foreach ($catalogo->campos() as $campo) {
            if ($campo->tipo !== TipoCampo::ElementoCatalogo || $campo->catalogo === null) {
                continue;
            }

            $id = $elemento->getAttribute($campo->nombre);

            if ($id !== null && ($elemento->isDirty($campo->nombre) || ! $elemento->exists) && ! $this->catalogos->estaActivo($campo->catalogo, (int) $id)) {
                throw ElementoCatalogoRechazado::referenciaInactiva($campo->etiqueta);
            }
        }
    }

    /**
     * @param  Model&ElementoCatalogo  $elemento
     */
    /**
     * Lo que la base rechazaría con un CHECK, dicho antes y con un mensaje claro (las frases del saludo y los feriados).
     *
     * @throws ElementoCatalogoRechazado
     */
    private function validarFormato(Model $elemento): void
    {
        $rechazo = match (true) {
            $elemento instanceof FraseSaludo => ReglasFrase::rechazo($elemento),
            $elemento instanceof Feriado => ReglasFeriado::rechazo($elemento),
            // Plan 1.4z, D9: el CHECK `aplicaciones_icono_propio_check`, dicho antes.
            $elemento instanceof Aplicacion && $elemento->icono === IconoAplicacion::Propio && $elemento->icono_adjunto_id === null => 'para usar el ícono «Propio», súbelo primero con «Subir ícono».',
            default => null,
        };

        if ($rechazo !== null) {
            throw ElementoCatalogoRechazado::formato($rechazo);
        }
    }

    private function guardarConNuevaVersion(Model $elemento): void
    {
        if (! $elemento->isDirty()) {
            return;
        }

        $elemento->setAttribute('version', (int) $elemento->getAttribute('version') + 1);
        $elemento->save();
    }
}
