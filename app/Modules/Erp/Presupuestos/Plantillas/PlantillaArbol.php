<?php

declare(strict_types=1);

namespace App\Modules\Erp\Presupuestos\Plantillas;

use App\Modules\Erp\Presupuestos\Models\ArbolPartidas;
use App\Modules\Erp\Presupuestos\Models\PartidaControl;
use App\Modules\Erp\Presupuestos\Services\ConsultaArbol;
use App\Modules\Erp\Presupuestos\Services\NodoPropuesto;
use App\Modules\Erp\Presupuestos\Services\PropuestaArbol;
use App\Modules\Nucleo\Excepciones\PlantillaRechazada;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Plantillas\DefinicionPlantilla;

/**
 * El primer tipo de plantilla: el árbol de partidas de control (ADR 0022; plan 2.11, D4, D6 y paso 6). Guarda los
 * códigos, los nombres, la jerarquía y cuál es la de gastos generales; nunca montos ni responsables, ni la PC-99, que
 * crea el sistema al aprobar. Aplicarla arma la misma propuesta editable que «desde el presupuesto» (plan 2.9b), con la
 * misma comprobación de «el árbol se puede llenar» (D7).
 *
 * Formato 1: `{"partidas": [{"codigo": "PC-01", "nombre": "Cámaras", "padre": null, "gastos_generales": false}, …]}`,
 * en orden de código; una partida es hoja si ninguna otra la tiene de padre.
 */
final class PlantillaArbol extends DefinicionPlantilla
{
    public const string CLAVE = 'erp.presupuestos.arbol';

    /** D2: lo comparten los tipos de plantilla del área 1. */
    public const string PERMISO_PUBLICAR = 'erp.presupuestos.plantillas.publicar';

    private const string CODIGO = '/^PC-\d{2}(\.\d{2})*$/';

    private const int MAXIMO = 500;

    public function clave(): string
    {
        return self::CLAVE;
    }

    public function nombre(): string
    {
        return 'Árbol de partidas de control';
    }

    public function permisoUsar(): string
    {
        return 'erp.presupuestos.arbol.editar';
    }

    public function permisoPublicar(): string
    {
        return self::PERMISO_PUBLICAR;
    }

    public function formatoActual(): int
    {
        return 1;
    }

    public function validar(array $contenido, int $formato): array
    {
        if ($formato !== 1) {
            return ["el formato {$formato} no existe para el árbol"];
        }

        $partidas = $contenido['partidas'] ?? null;

        if (! is_array($partidas) || ! array_is_list($partidas) || $partidas === []) {
            return ['no tiene partidas'];
        }

        if (count($partidas) > self::MAXIMO) {
            return ['tiene más de '.self::MAXIMO.' partidas'];
        }

        $problemas = [];
        $vistos = [];
        $padres = [];
        $gastosGenerales = [];

        foreach ($partidas as $i => $p) {
            $codigo = is_array($p) ? ($p['codigo'] ?? null) : null;

            if (! is_string($codigo) || preg_match(self::CODIGO, $codigo) !== 1) {
                $problemas[] = 'la partida '.($i + 1).' no tiene un código como «PC-01» o «PC-01.02»';

                continue;
            }

            $nombre = $p['nombre'] ?? null;
            $padre = $p['padre'] ?? null;
            $esperado = str_contains($codigo, '.') ? substr($codigo, 0, (int) strrpos($codigo, '.')) : null;

            $problema = match (true) {
                $codigo === PartidaControl::CODIGO_NO_PRESUPUESTADA => 'la PC-99 la crea el sistema al aprobar: no va en la plantilla',
                isset($vistos[$codigo]) => "{$codigo} está repetida",
                ! is_string($nombre) || trim($nombre) === '' || mb_strlen($nombre) > 200 => "{$codigo} necesita un nombre de hasta 200 caracteres",
                $padre !== $esperado => "{$codigo} debe colgar de ".($esperado ?? 'la raíz'),
                $esperado !== null && ! isset($vistos[$esperado]) => "{$codigo} cuelga de {$esperado}, que no está antes",
                ! is_bool($p['gastos_generales'] ?? null) => "{$codigo} no dice si es la de gastos generales",
                default => null,
            };

            if ($problema !== null) {
                $problemas[] = $problema;
            }

            $vistos[$codigo] = true;

            if ($esperado !== null) {
                $padres[$esperado] = true;
            }

            if (($p['gastos_generales'] ?? false) === true) {
                $gastosGenerales[] = $codigo;
            }
        }

        if (count($gastosGenerales) > 1) {
            $problemas[] = 'solo una partida es la de gastos generales';
        }

        foreach ($gastosGenerales as $codigo) {
            if (isset($padres[$codigo])) {
                $problemas[] = "{$codigo}, la de gastos generales, no puede tener partidas debajo";
            }
        }

        return $problemas;
    }

    /** Las partidas activas del árbol, sin la PC-99, en orden de código: sin montos ni responsables (D6). */
    public function capturar(int $documentoId, User $usuario): array
    {
        $arbol = ArbolPartidas::query()->find($documentoId) ?? throw PlantillaRechazada::conMotivo('Ese árbol de partidas no existe.');
        $partidas = PartidaControl::query()
            ->where('proyecto_id', $arbol->proyecto_id)
            ->where('es_activa', true)
            ->where('es_no_presupuestado', false)
            ->orderBy('codigo')
            ->get();

        if ($partidas->isEmpty()) {
            throw PlantillaRechazada::conMotivo('Este árbol todavía no tiene partidas: agrégale alguna antes de guardarlo como plantilla.');
        }

        $codigos = $partidas->pluck('codigo', 'id');

        return ['partidas' => $partidas->map(fn (PartidaControl $p): array => [
            'codigo' => $p->codigo,
            'nombre' => $p->nombre,
            'padre' => $p->padre_id === null ? null : $codigos[$p->padre_id],
            'gastos_generales' => $p->es_gastos_generales,
        ])->values()->all()];
    }

    /**
     * La propuesta del árbol del proyecto `$contexto['proyecto_id']`: las partidas de la plantilla sin monto (D6), el
     * responsable por omisión de la 2.9b y el costo sugerido (D8). Rechaza si el árbol no se puede llenar (D7).
     */
    public function proponer(array $contenido, int $formato, array $contexto, User $usuario): PropuestaArbol
    {
        $codigosPadre = array_filter(array_column($contenido['partidas'], 'padre'));
        $nodos = array_map(fn (array $p): NodoPropuesto => new NodoPropuesto(
            codigo: $p['codigo'],
            nombre: $p['nombre'],
            padreCodigo: $p['padre'],
            esHoja: ! in_array($p['codigo'], $codigosPadre, true),
            monto: null,
            esGastosGenerales: $p['gastos_generales'],
        ), $contenido['partidas']);

        return app(ConsultaArbol::class)->propuestaConPartidas((int) ($contexto['proyecto_id'] ?? 0), $nodos);
    }
}
