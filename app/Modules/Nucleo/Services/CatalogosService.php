<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Services;

use App\Modules\Nucleo\Catalogos\RegistroOtrosDeCatalogo;
use App\Modules\Nucleo\Enums\Catalogo;
use App\Modules\Nucleo\Estados\DetalleDeOtro;
use App\Modules\Nucleo\Excepciones\CatalogoNoEncontrado;
use App\Modules\Nucleo\Excepciones\DetalleDeOtroRechazado;
use App\Modules\Nucleo\Models\Concerns\BusquedaSinAcentos;
use App\Modules\Nucleo\Models\Concerns\ElementoCatalogo;
use App\Modules\Nucleo\Models\Ubigeo;
use App\Modules\Nucleo\Parties\PosiblesDuplicados;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Lectura de los catálogos compartidos (M5) para los demás módulos: guardan el id como llave foránea,
 * validan que siga activo y llenan sus selectores. La escritura está en EdicionCatalogosService.
 */
final class CatalogosService
{
    /**
     * Id de un elemento activo por su código («USD», «NIU», «140101»).
     *
     * @throws CatalogoNoEncontrado
     */
    public function idPorCodigo(Catalogo $catalogo, string $codigo): int
    {
        $id = $this->activos($catalogo)->where('codigo', $codigo)->value('id');

        return $id === null ? throw CatalogoNoEncontrado::codigo($catalogo->nombre(), $codigo) : (int) $id;
    }

    public function codigoDe(Catalogo $catalogo, int $id): string
    {
        return (string) $this->consulta($catalogo)->whereKey($id)->value('codigo');
    }

    /** Para validar lo que llega de un formulario o de la API: existe y sigue activo. */
    public function estaActivo(Catalogo $catalogo, int $id): bool
    {
        return $this->activos($catalogo)->whereKey($id)->exists();
    }

    /**
     * Opciones para un selector: id → etiqueta, solo activos, ordenadas por código. `$actual` es lo que el
     * registro ya tiene elegido: si se desactivó después, se ofrece igual (marcado) para poder guardar
     * otros cambios sin cambiarlo. En un selector de varios, `$actual` es la lista elegida.
     *
     * @param  int|list<int>|null  $actual
     * @return array<int, string>
     */
    public function opciones(Catalogo $catalogo, int|array|null $actual = null): array
    {
        $opciones = [];

        foreach ($this->activos($catalogo)->orderBy('codigo')->get() as $elemento) {
            $opciones[(int) $elemento->getKey()] = $elemento->etiqueta();
        }

        // Lo que el registro ya tiene, aunque esté desactivado: si no, el formulario no se podría guardar (AGENTS.md).
        foreach (is_array($actual) ? $actual : array_filter([$actual]) as $id) {
            if (isset($opciones[$id])) {
                continue;
            }

            $elemento = $this->consulta($catalogo)->find($id);

            if ($elemento !== null) {
                $opciones[$id] = $elemento->etiqueta().' (desactivado)';
            }
        }

        return $opciones;
    }

    /**
     * Las opciones de un filtro de tabla: todos los activos por código. Los formularios no lo usan: van con el selector
     * común (`SelectorCatalogo`, plan 2.0, D4), que busca en la base.
     *
     * @return array<int, string>
     */
    public function paraFiltro(Catalogo $catalogo): array
    {
        return $this->opciones($catalogo);
    }

    /** Resultados de una búsqueda en un selector (plan 2.0, D1). */
    public const int LIMITE_BUSQUEDA = 50;

    /**
     * Lo que un selector ofrece al escribir (plan 2.0, D1): los activos cuyo código o columnas de búsqueda contienen
     * el texto, sin acentos ni mayúsculas; primero los que empiezan con él, luego por código; hasta `$limite`. Sin
     * texto, los primeros por código. En ubigeos busca solo en el distrito y cada resultado lleva su ruta
     * («Chiclayo — Chiclayo, Lambayeque»), porque hay distritos con el mismo nombre. Consulta la base cada vez: un
     * valor agregado en otra pestaña aparece al buscarlo.
     *
     * @return array<int, string> id → etiqueta
     */
    public function buscar(Catalogo $catalogo, string $texto, int $limite = self::LIMITE_BUSQUEDA): array
    {
        $texto = trim($texto);
        $columnas = $catalogo === Catalogo::Ubigeos ? ['distrito'] : $catalogo->columnasBusqueda();
        $consulta = $this->activos($catalogo);

        if ($texto !== '') {
            BusquedaSinAcentos::aplicar($consulta, $texto, ['codigo', ...$columnas]);
            $inicio = BusquedaSinAcentos::escaparComodines($texto).'%';
            $principal = $consulta->getQuery()->getGrammar()->wrap($columnas[0]);
            $consulta->orderByRaw("CASE WHEN unaccent({$principal}) ilike unaccent(?) OR unaccent(codigo) ilike unaccent(?) THEN 0 ELSE 1 END", [$inicio, $inicio]);
        }

        $resultados = [];

        // D5: «Otro» al final.
        if (($otro = $catalogo->codigoOtro()) !== null) {
            $consulta->orderByRaw('(codigo = ?) ASC', [$otro]);
        }

        foreach ($consulta->orderBy('codigo')->limit(max(1, $limite))->get() as $elemento) {
            $resultados[(int) $elemento->getKey()] = $this->etiquetaEnSelector($catalogo, $elemento);
        }

        return $resultados;
    }

    /** Si el elemento elegido es el «Otro» del catálogo (plan 2.0, D5). */
    public function esOtro(Catalogo $catalogo, mixed $id): bool
    {
        $otro = $catalogo->codigoOtro();

        return $otro !== null && is_numeric($id) && $this->consulta($catalogo)->whereKey((int) $id)->value('codigo') === $otro;
    }

    /**
     * El detalle de «Otro» que guarda el registro que lo usa (plan 2.0, D5), como lo piden los motivos: con «Otro», sin
     * espacios al borde y de al menos `motivos.minimo_detalle_otro` caracteres; sin «Otro», nulo. `$que` dice dónde
     * («el origen»), para el mensaje.
     *
     * @throws DetalleDeOtroRechazado
     */
    public function detalleDeOtro(Catalogo $catalogo, ?int $id, ?string $detalle, string $que): ?string
    {
        $detalle = trim((string) $detalle);

        if (! $this->esOtro($catalogo, $id)) {
            return $detalle === '' ? null : throw DetalleDeOtroRechazado::sobra($que);
        }

        $minimo = app(ParametrosService::class)->entero('motivos.minimo_detalle_otro');

        return mb_strlen($detalle) >= $minimo ? $detalle : throw DetalleDeOtroRechazado::falta($que, $minimo);
    }

    /**
     * Lo escrito en «Otro» de un catálogo, de todos los módulos que lo usan, agrupado sin acentos ni mayúsculas y del
     * más repetido al menos (plan 2.0, D3 y D5).
     *
     * @return list<DetalleDeOtro>
     */
    public function detallesDeOtro(Catalogo $catalogo, ?CarbonImmutable $desde = null): array
    {
        $todos = [];

        foreach (app(RegistroOtrosDeCatalogo::class)->de($catalogo) as $fuente) {
            $todos = [...$todos, ...app($fuente)->detallesDeOtro($catalogo, $desde)];
        }

        return MotivosService::juntar($todos);
    }

    /**
     * La etiqueta de un elemento para el selector, aunque esté desactivado (entonces con «(desactivado)»): lo que el
     * registro ya tiene se ve y el formulario se guarda (AGENTS.md). Nulo si no existe.
     */
    public function etiquetaDe(Catalogo $catalogo, int $id): ?string
    {
        $elemento = $this->consulta($catalogo)->find($id);

        if ($elemento === null) {
            return null;
        }

        $etiqueta = $this->etiquetaEnSelector($catalogo, $elemento);

        return $elemento->getAttribute($catalogo->modelo()::columnaActivo()) ? $etiqueta : "{$etiqueta} (desactivado)";
    }

    /**
     * Los elementos iguales o parecidos a un nombre, activos o no, para avisar antes de agregar con el «+» (plan 2.0,
     * D2; avisa, no bloquea): la misma normalización que las fichas de parties (sin acentos, mayúsculas ni signos) y
     * el mismo umbral de parecido (`parties.umbral_nombre_parecido`, con `pg_trgm`). «Feria» y «feria» son iguales.
     *
     * @return list<string> sus etiquetas, primero los iguales y los más parecidos
     */
    public function parecidos(Catalogo $catalogo, string $nombre, int $limite = 5): array
    {
        $normal = PosiblesDuplicados::normalizar($nombre);

        if ($normal === '') {
            return [];
        }

        $consulta = $this->consulta($catalogo);
        $columna = $consulta->getQuery()->getGrammar()->wrap($catalogo === Catalogo::Ubigeos ? 'distrito' : $catalogo->columnasBusqueda()[0]);
        // Como `PosiblesDuplicados::normalizar`, en SQL: puntos y comas se borran, los demás signos separan.
        $comparable = "btrim(regexp_replace(regexp_replace(lower(unaccent({$columna})), '[.,]', '', 'g'), '[^a-z0-9]+', ' ', 'g'))";
        $umbral = app(ParametrosService::class)->decimal('parties.umbral_nombre_parecido');

        $etiquetas = [];

        foreach ($consulta->whereRaw("({$comparable} = ? OR similarity({$comparable}, ?) >= ?)", [$normal, $normal, $umbral])
            ->orderByRaw("({$comparable} = ?) DESC, similarity({$comparable}, ?) DESC", [$normal, $normal])
            ->limit(max(1, $limite))
            ->get() as $elemento) {
            $etiqueta = $this->etiquetaEnSelector($catalogo, $elemento);
            $etiquetas[] = $elemento->getAttribute($catalogo->modelo()::columnaActivo()) ? $etiqueta : "{$etiqueta} (desactivado)";
        }

        return $etiquetas;
    }

    /** Cuántos activos tiene: un catálogo corto muestra su lista al abrir (plan 2.0, D1). */
    public function cuantosActivos(Catalogo $catalogo): int
    {
        return $this->activos($catalogo)->count();
    }

    /**
     * Ubigeos por niveles (plan 2.0, D1): los departamentos activos, por los dos primeros dígitos del código INEI.
     * La tabla solo tiene distritos; el nivel sale de sus columnas, sin tabla nueva.
     *
     * @return array<string, string> «14» → «Lambayeque»
     */
    public function departamentos(): array
    {
        return $this->activos(Catalogo::Ubigeos)
            ->selectRaw('substr(codigo, 1, 2) AS nivel, min(departamento) AS nombre')
            ->groupByRaw('substr(codigo, 1, 2)')
            ->orderBy('nivel')
            ->pluck('nombre', 'nivel')
            ->all();
    }

    /**
     * Las provincias activas de un departamento (dos dígitos).
     *
     * @return array<string, string> «1401» → «Chiclayo»
     */
    public function provincias(string $departamento): array
    {
        return $this->activos(Catalogo::Ubigeos)
            ->where('codigo', 'like', BusquedaSinAcentos::escaparComodines($departamento).'%')
            ->selectRaw('substr(codigo, 1, 4) AS nivel, min(provincia) AS nombre')
            ->groupByRaw('substr(codigo, 1, 4)')
            ->orderBy('nivel')
            ->pluck('nombre', 'nivel')
            ->all();
    }

    /**
     * Los distritos activos de una provincia (cuatro dígitos), con su ruta.
     *
     * @return array<int, string> id → «Chiclayo — Chiclayo, Lambayeque»
     */
    public function distritos(string $provincia): array
    {
        $distritos = [];

        foreach ($this->activos(Catalogo::Ubigeos)->where('codigo', 'like', BusquedaSinAcentos::escaparComodines($provincia).'%')->orderBy('codigo')->get() as $elemento) {
            $distritos[(int) $elemento->getKey()] = $this->etiquetaEnSelector(Catalogo::Ubigeos, $elemento);
        }

        return $distritos;
    }

    /** En ubigeos, la ruta; en los demás, la etiqueta del elemento. */
    private function etiquetaEnSelector(Catalogo $catalogo, Model&ElementoCatalogo $elemento): string
    {
        return $elemento instanceof Ubigeo ? $elemento->ruta() : $elemento->etiqueta();
    }

    /**
     * @return Builder<Model&ElementoCatalogo>
     */
    private function activos(Catalogo $catalogo): Builder
    {
        return $this->consulta($catalogo)->where($catalogo->modelo()::columnaActivo(), true);
    }

    /**
     * @return Builder<Model&ElementoCatalogo>
     */
    private function consulta(Catalogo $catalogo): Builder
    {
        return $catalogo->modelo()::query();
    }
}
