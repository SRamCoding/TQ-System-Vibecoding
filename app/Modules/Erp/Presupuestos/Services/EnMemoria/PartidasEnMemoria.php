<?php

declare(strict_types=1);

namespace App\Modules\Erp\Presupuestos\Services\EnMemoria;

use App\Modules\Erp\Presupuestos\Services\PartidasService;
use App\Modules\Erp\Presupuestos\Services\ResumenPartida;
use App\Modules\Erp\Presupuestos\Services\SaldoPartida;
use App\Modules\Nucleo\Excepciones\ImputacionRechazada;
use App\Modules\Nucleo\Imputacion\DestinoImputacion;
use Illuminate\Support\Str;

/**
 * C1 en memoria (plan 2.1, D1; vive en la app desde el plan 2.2, D8, solo para pruebas y la página de modo desarrollo
 * del selector), para quien prueba su módulo sin armar árboles:
 * `$this->app->instance(PartidasService::class, $falsa = new PartidasEnMemoria)`, luego `$falsa->agregar(...)` y, para
 * simular costos ya imputados, `$falsa->imputar(...)`. Pasa las mismas pruebas del contrato que `PartidasEnBase`
 * (`tests/Modules/Erp/Presupuestos/ContratoPartidasTest.php`). Su `destino()` no se puede guardar: ver `DestinoEnMemoria`.
 */
final class PartidasEnMemoria implements PartidasService
{
    /** @var array<int, array{proyecto: int, codigo: string, nombre: string, padre: ?int, presupuestado: string, activa: bool, arbolAprobado: bool, pc99: bool, responsable: int}> */
    private array $partidas = [];

    /** @var array<int, array{comprometido: string, ejecutado: string, pagado: string}> */
    private array $imputado = [];

    /** @var array<int, true> proyectos que ya no aceptan costos */
    private array $sinCostos = [];

    public function agregar(
        int $proyectoId,
        string $codigo,
        string $nombre,
        ?int $padreId = null,
        string $presupuestado = '0.00',
        bool $activa = true,
        bool $arbolAprobado = true,
        int $responsableUserId = 1,
    ): int {
        $id = count($this->partidas) + 1;
        $this->partidas[$id] = [
            'proyecto' => $proyectoId, 'codigo' => $codigo, 'nombre' => $nombre, 'padre' => $padreId,
            'presupuestado' => bcadd($presupuestado, '0', 2), 'activa' => $activa, 'arbolAprobado' => $arbolAprobado,
            'pc99' => $codigo === 'PC-99', 'responsable' => $responsableUserId,
        ];

        return $id;
    }

    /** Lo que ya está imputado a una partida, en soles, como si lo hubieran hecho otros documentos. */
    public function imputar(int $partidaId, string $comprometido, string $ejecutado = '0', string $pagado = '0'): void
    {
        $actual = $this->imputado[$partidaId] ?? ['comprometido' => '0', 'ejecutado' => '0', 'pagado' => '0'];
        $this->imputado[$partidaId] = [
            'comprometido' => bcadd($actual['comprometido'], $comprometido, 2),
            'ejecutado' => bcadd($actual['ejecutado'], $ejecutado, 2),
            'pagado' => bcadd($actual['pagado'], $pagado, 2),
        ];
    }

    /** El proyecto se cerró o se anuló: ya no acepta costos. */
    public function proyectoSinCostos(int $proyectoId): void
    {
        $this->sinCostos[$proyectoId] = true;
    }

    public function buscar(int $proyectoId, string $texto = '', int $limite = 20): array
    {
        if (isset($this->sinCostos[$proyectoId])) {
            return [];
        }

        $buscado = self::normalizar($texto);
        $encontradas = [];

        foreach ($this->ordenadas() as $id => $p) {
            if ($p['proyecto'] !== $proyectoId || ! $p['arbolAprobado'] || ! $p['activa'] || $p['pc99'] || ! $this->esHoja($id)) {
                continue;
            }

            $coincide = $buscado === '' || array_filter(
                $this->linaje($id),
                fn (int $nodo): bool => str_contains(self::normalizar("{$this->partidas[$nodo]['codigo']} {$this->partidas[$nodo]['nombre']}"), $buscado),
            ) !== [];

            if ($coincide) {
                $encontradas[] = $this->resumir($id);
            }
        }

        return array_slice($encontradas, 0, max(1, min($limite, 50)));
    }

    public function resumen(int $partidaId): ?ResumenPartida
    {
        return isset($this->partidas[$partidaId]) ? $this->resumir($partidaId) : null;
    }

    public function saldos(array $partidaIds): array
    {
        $saldos = [];

        foreach ($partidaIds as $id) {
            if (isset($this->partidas[$id])) {
                $saldos[$id] = $this->saldo($id);
            }
        }

        return $saldos;
    }

    /** Sin base no hay filas que bloquear: `$bloquear` no cambia nada. */
    public function saldo(int $partidaId, bool $bloquear = false): SaldoPartida
    {
        if (! isset($this->partidas[$partidaId])) {
            throw ImputacionRechazada::conMotivo('Esa partida no existe.');
        }

        $totales = ['presupuestado' => '0', 'comprometido' => '0', 'ejecutado' => '0', 'pagado' => '0'];

        foreach ($this->rama($partidaId) as $id) {
            if ($this->partidas[$id]['arbolAprobado']) {
                $totales['presupuestado'] = bcadd($totales['presupuestado'], $this->partidas[$id]['presupuestado'], 2);
            }

            foreach (['comprometido', 'ejecutado', 'pagado'] as $momento) {
                $totales[$momento] = bcadd($totales[$momento], $this->imputado[$id][$momento] ?? '0', 2);
            }
        }

        return new SaldoPartida(
            presupuestado: bcadd($totales['presupuestado'], '0', 2),
            comprometido: bcadd($totales['comprometido'], '0', 2),
            ejecutado: bcadd($totales['ejecutado'], '0', 2),
            pagado: bcadd($totales['pagado'], '0', 2),
            disponible: bcsub($totales['presupuestado'], $totales['comprometido'], 2),
        );
    }

    public function destino(int $partidaId, ?int $motivoId = null, ?string $comentario = null, ?int $centroCostoId = null): DestinoImputacion
    {
        $p = $this->partidas[$partidaId] ?? throw ImputacionRechazada::conMotivo('Esa partida no existe.');

        if (! $p['activa']) {
            throw ImputacionRechazada::conMotivo("La partida {$p['codigo']} está desactivada: no recibe costos.");
        }

        if (! $this->esHoja($partidaId)) {
            throw ImputacionRechazada::conMotivo("La partida {$p['codigo']} no es una hoja: el costo va a una de las partidas de abajo.");
        }

        if (! $p['arbolAprobado']) {
            throw ImputacionRechazada::conMotivo('El árbol de este proyecto todavía no está aprobado.');
        }

        if (isset($this->sinCostos[$p['proyecto']])) {
            throw ImputacionRechazada::conMotivo('El proyecto ya no acepta costos.');
        }

        if ($p['pc99'] && ($motivoId === null || trim((string) $comentario) === '')) {
            throw ImputacionRechazada::conMotivo('Un gasto no presupuestado (PC-99) lleva su motivo y un comentario.');
        }

        if (! $p['pc99'] && $centroCostoId !== null) {
            throw ImputacionRechazada::conMotivo('El centro de costo de referencia es solo para la PC-99.');
        }

        return new DestinoEnMemoria($partidaId, $p['proyecto'], $p['codigo'], $motivoId, $comentario, $centroCostoId);
    }

    public function noPresupuestada(int $proyectoId): ?ResumenPartida
    {
        foreach ($this->partidas as $id => $p) {
            if ($p['proyecto'] === $proyectoId && $p['pc99'] && $p['arbolAprobado']) {
                return $this->resumir($id);
            }
        }

        return null;
    }

    private function esHoja(int $id): bool
    {
        foreach ($this->partidas as $p) {
            if ($p['padre'] === $id && $p['activa']) {
                return false;
            }
        }

        return true;
    }

    /** @return list<int> la partida y todas sus descendientes */
    private function rama(int $id): array
    {
        $rama = [$id];

        foreach ($this->partidas as $hija => $p) {
            if ($p['padre'] === $id) {
                $rama = [...$rama, ...$this->rama($hija)];
            }
        }

        return $rama;
    }

    /** @return list<int> de la raíz a la partida */
    private function linaje(int $id): array
    {
        $linaje = [$id];

        while (($padre = $this->partidas[$id]['padre']) !== null) {
            array_unshift($linaje, $padre);
            $id = $padre;
        }

        return $linaje;
    }

    /** @return array<int, array{proyecto: int, codigo: string, nombre: string, padre: ?int, presupuestado: string, activa: bool, arbolAprobado: bool, pc99: bool, responsable: int}> */
    private function ordenadas(): array
    {
        $partidas = $this->partidas;
        uasort($partidas, fn (array $a, array $b): int => strcmp($a['codigo'], $b['codigo']));

        return $partidas;
    }

    private function resumir(int $id): ResumenPartida
    {
        $p = $this->partidas[$id];
        $padres = array_map(fn (int $nodo): string => $this->partidas[$nodo]['codigo'], array_slice($this->linaje($id), 0, -1));

        return new ResumenPartida($id, $p['proyecto'], $p['codigo'], $p['nombre'], implode(' › ', [...$padres, "{$p['codigo']} {$p['nombre']}"]), $p['pc99'], $p['responsable'], $p['activa']);
    }

    private static function normalizar(string $texto): string
    {
        return mb_strtolower(Str::ascii(trim($texto)));
    }
}
