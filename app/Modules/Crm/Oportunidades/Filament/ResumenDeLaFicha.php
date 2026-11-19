<?php

declare(strict_types=1);

namespace App\Modules\Crm\Oportunidades\Filament;

use App\Modules\Crm\Cuentas\Services\CuentasService;
use App\Modules\Crm\Oportunidades\Enums\EtapaAbierta;
use App\Modules\Crm\Oportunidades\Filament\Resources\Oportunidades\OportunidadResource;
use App\Modules\Crm\Oportunidades\Models\Oportunidad;
use App\Modules\Crm\Oportunidades\Models\OportunidadCompetidor;
use App\Modules\Crm\Oportunidades\Requisitos\RequisitosDeEtapa;
use App\Modules\Crm\Oportunidades\Services\CotizacionDeOportunidad;
use App\Modules\Crm\Oportunidades\Services\FotoOportunidadService;
use App\Modules\Crm\Oportunidades\Services\PortadaOportunidades;
use App\Modules\Crm\Oportunidades\Services\VersionDeCotizacion;
use App\Modules\Erp\Presupuestos\Services\PresupuestosService;
use App\Modules\Erp\Presupuestos\Services\ResumenPresupuesto;
use App\Modules\Nucleo\Models\LineaSolucion;
use App\Modules\Nucleo\Models\Party;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Seguridad\GrupoSensible;
use App\Modules\Nucleo\Services\EstadosService;
use App\Modules\Nucleo\Tiempo\Calendario;
use Carbon\CarbonImmutable;

/**
 * Lo que dibuja la ficha de una oportunidad (plan 2.21d, D10; prototipo aprobado), sin cuentas en la vista: la cabecera
 * (foto de fondo, monto, ponderado y la línea de etapas con la fecha de cada una) y el Resumen: el **recorrido de los
 * documentos** (costeo → cotizaciones con sus versiones → la aceptada → la copia en el proyecto → el proyecto; lo que
 * falta, punteado: la copia al ganar llega con la 2.12, D15), **qué falta para avanzar** (la compuerta), **cómo se fue
 * negociando** (el total de cada versión en un gráfico y el margen solo con `campos.ver-costos`), la **línea de tiempo**,
 * **atención**, la competencia y el decisor. Lee Cotizaciones (C3) y Presupuestos (C8) por sus servicios.
 */
final readonly class ResumenDeLaFicha
{
    /** @return array<string, mixed> */
    public function cabecera(Oportunidad $o): array
    {
        $linea = LineaSolucion::query()->find($o->linea_solucion_id);
        $cuenta = app(CuentasService::class)->resumen($o->cuenta_id);
        $codigo = $o->estado->codigo;
        $abierta = EtapaAbierta::tryFrom($codigo);
        $portada = app(PortadaOportunidades::class);
        $fechas = $this->fechasDeEtapas($o);
        $pasos = [];

        foreach (EtapaAbierta::cases() as $i => $etapa) {
            $indice = $abierta === null ? 99 : array_search($abierta, EtapaAbierta::cases(), true);
            $pasos[] = [
                'nombre' => $etapa->etiqueta(),
                'fecha' => $fechas[$etapa->etiqueta()] ?? null,
                'estado' => $i < $indice ? 'hecho' : ($i === $indice ? 'ahora' : ''),
                'dias' => $i === $indice ? $portada->diasEnEtapa($o) : null,
            ];
        }

        $pasos[] = [
            'nombre' => $codigo === 'perdida' ? 'Perdida' : 'Ganada',
            'fecha' => in_array($codigo, ['ganada', 'perdida'], true) ? $o->fecha_cierre_real?->format('d/m/Y') : null,
            'estado' => $codigo === 'ganada' ? 'hecho' : ($codigo === 'perdida' ? 'perdida' : ''),
            'dias' => null,
        ];

        return [
            'numero' => $o->numero,
            'nombre' => $o->nombre,
            'linea' => $linea->nombre ?? '—',
            'entidad' => $cuenta->etiqueta,
            'cliente' => ! $cuenta->esPublica ? 'Cliente privado' : ($cuenta->nivelGobiernoNombre === null ? 'Entidad pública' : "Gobierno {$cuenta->nivelGobiernoNombre}"),
            'ejecutivo' => $o->ejecutivo->name,
            'abierta' => $o->created_at->format('d/m/Y'),
            'cierre' => ($o->fecha_cierre_real ?? $o->fecha_cierre_estimada)->format('d/m/Y'),
            'cerrada' => $abierta === null,
            'monto' => OportunidadResource::monto($o),
            'detalle' => match (true) {
                $codigo === 'ganada' => 'Ganada',
                $codigo === 'perdida' => 'Perdida',
                $abierta !== null => 'Ponderado '.OportunidadResource::ponderado($o).' · '.$portada->probabilidad($abierta).' %',
                default => $o->estado->nombre,
            },
            'foto' => FotoOportunidadService::url($o),
            'dibujo' => DibujoDeLinea::svg($o->id, $linea->codigo ?? '', $o->linea_solucion_id),
            'pasos' => $pasos,
        ];
    }

    /** @return array<string, mixed> */
    public function resumen(Oportunidad $o, User $quien): array
    {
        $versiones = app(CotizacionDeOportunidad::class)->versiones($o->id);
        $veCostos = $quien->can(GrupoSensible::Costos->permiso());

        return [
            'recorrido' => $this->recorrido($o, $versiones),
            'compuerta' => $this->compuerta($o),
            'negociacion' => $this->negociacion($versiones, $veCostos),
            'tiempo' => $this->lineaDeTiempo($o, $versiones),
            'atencion' => $this->atencion($o, $versiones, $veCostos),
            'competencia' => OportunidadCompetidor::query()->where('oportunidad_id', $o->id)->get()
                ->map(fn (OportunidadCompetidor $c): array => [
                    'nombre' => (string) Party::query()->whereKey($c->party_id)->value('razon_social'),
                    'nota' => trim(($c->es_ganador ? 'Ganó · ' : '').($c->fortalezas ?? ''), ' ·') ?: '—',
                ])->values()->all(),
            'decisor' => $o->decisor === null ? null : ['nombre' => $o->decisor->nombreCompleto(), 'cargo' => $o->decisor->cargo],
            'veCostos' => $veCostos,
        ];
    }

    /** @return array<string, string> fecha de la primera entrada a cada etapa, por su nombre */
    private function fechasDeEtapas(Oportunidad $o): array
    {
        $fechas = [EtapaAbierta::Prospecto->etiqueta() => $o->created_at->format('d/m/Y')];

        foreach (app(EstadosService::class)->historialDe($o) as $fila) {
            $fechas[$fila->hacia] ??= $fila->fecha->format('d/m/Y');
        }

        return $fechas;
    }

    /**
     * @param  list<VersionDeCotizacion>  $versiones
     * @return array{nodos: list<array<string, mixed>>, completo: bool}
     */
    private function recorrido(Oportunidad $o, array $versiones): array
    {
        $ganada = $o->estado->codigo === 'ganada';
        $costeo = app(PresupuestosService::class)->deOportunidad($o->id);
        $nodos = [];

        $nodos[] = $costeo === []
            ? ['tipo' => 'futuro', 'titulo' => 'Costeo', 'codigo' => 'Por armar', 'nota' => 'El presupuesto de la oportunidad, con APU']
            : ['tipo' => 'erp', 'titulo' => 'Costeo', 'codigo' => $costeo[0]->numero, 'nota' => count($costeo).' '.(count($costeo) === 1 ? 'versión' : 'versiones'),
                'versiones' => array_map(fn (ResumenPresupuesto $p): string => "v{$p->numeroVersion}", array_reverse($costeo))];

        $porNumero = [];

        foreach ($versiones as $v) {
            $porNumero[$v->numero][] = $v;
        }

        if ($porNumero === []) {
            $nodos[] = ['tipo' => 'futuro', 'titulo' => 'Cotización', 'codigo' => 'Sin emitir', 'nota' => 'Se emite desde el costeo'];
        }

        foreach ($porNumero as $numero => $lista) {
            $ultima = $lista[count($lista) - 1];
            $nodos[] = [
                'tipo' => 'crm', 'titulo' => 'Cotización', 'codigo' => $numero, 'nota' => "Última {$ultima->moneda} ".number_format((float) $ultima->total, 2),
                'versiones' => array_map(fn (VersionDeCotizacion $v): string => "v{$v->numeroVersion}".($v->esAceptada ? ' ✓' : ''), $lista),
                'aceptada' => array_filter($lista, fn (VersionDeCotizacion $v): bool => $v->esAceptada) !== [],
                'url' => $ultima->url,
            ];
        }

        // D15: la copia al ganar (ADR 0023) y el proyecto llegan con la 2.12; mientras, punteados.
        $nodos[] = ['tipo' => 'futuro', 'titulo' => $ganada ? 'Copia al ganar' : 'Al ganar', 'codigo' => 'Llega con la copia al ganar', 'nota' => 'Presupuesto del proyecto'];
        $nodos[] = ['tipo' => 'futuro', 'titulo' => 'Proyecto', 'codigo' => $o->estado->codigo === 'perdida' ? 'No llegó' : 'Nace al ganar', 'nota' => '—'];

        return ['nodos' => $nodos, 'completo' => false];
    }

    /** @return ?array<string, mixed> null en una cerrada */
    private function compuerta(Oportunidad $o): ?array
    {
        $etapa = EtapaAbierta::tryFrom($o->estado->codigo);

        if ($etapa === null) {
            return null;
        }

        $portada = app(PortadaOportunidades::class);
        $siguiente = $etapa->siguiente();
        $destino = $siguiente === null ? 'ganada' : $siguiente->value;

        return [
            'hacia' => $siguiente?->etiqueta() ?? 'Ganada',
            'requisitos' => app(RequisitosDeEtapa::class)->revisar($o->id, $destino),
            'dias' => $portada->diasEnEtapa($o),
            'maximo' => $portada->diasMaximos($etapa),
            'atascada' => $portada->pasaElMaximo($o, $etapa->value),
            'siAvanza' => $siguiente === null ? 100 : $portada->probabilidad($siguiente),
        ];
    }

    /**
     * El total de cada versión de la cotización principal (la primera) en un gráfico, y todas en la tabla.
     *
     * @param  list<VersionDeCotizacion>  $versiones
     * @return array<string, mixed>
     */
    private function negociacion(array $versiones, bool $veCostos): array
    {
        $principal = array_values(array_filter($versiones, fn (VersionDeCotizacion $v): bool => $v->numero === ($versiones[0]->numero ?? '')));
        $puntos = [];

        if (count($principal) > 1) {
            $totales = array_map(fn (VersionDeCotizacion $v): float => (float) $v->total, $principal);
            [$ancho, $alto, $margen] = [520, 140, 60];
            $max = max($totales) * 1.02;
            $min = min($totales) * 0.97;

            foreach ($principal as $i => $v) {
                $puntos[] = [
                    'x' => round($margen + $i * ($ancho - 2 * $margen) / (count($principal) - 1), 1),
                    'y' => round($alto - 40 - ((float) $v->total - $min) / (($max - $min) ?: 1) * ($alto - 70), 1),
                    'total' => $v->moneda.' '.number_format((float) $v->total, 2),
                    'etiqueta' => "v{$v->numeroVersion}".($v->fechaEmision === null ? '' : ' · '.$v->fechaEmision->format('d/m')).($veCostos && $v->margenPorcentaje !== null ? ' · margen '.$v->margenPorcentaje.' %' : ''),
                ];
            }
        }

        return [
            'puntos' => $puntos,
            'filas' => array_map(fn (VersionDeCotizacion $v): array => [
                'identificador' => $v->identificador,
                'fecha' => $v->fechaEmision?->format('d/m/Y') ?? 'Sin emitir',
                'total' => $v->moneda.' '.number_format((float) $v->total, 2),
                // Solo con permiso de costos: sin él ni se manda (ADR 0016).
                'margen' => $veCostos ? $v->margenPorcentaje : null,
                'bajo' => $veCostos && $v->margenPorcentaje !== null && $v->margenMinimo !== null && bccomp($v->margenPorcentaje, $v->margenMinimo, 2) < 0,
                'aceptada' => $v->esAceptada,
                'url' => $v->url,
            ], array_reverse($versiones)),
        ];
    }

    /**
     * @param  list<VersionDeCotizacion>  $versiones
     * @return list<array{fecha: string, texto: string, tono: string}>
     */
    private function lineaDeTiempo(Oportunidad $o, array $versiones): array
    {
        $eventos = [['cuando' => $o->created_at, 'texto' => 'Creada en Prospecto', 'tono' => 'gray']];

        foreach (app(EstadosService::class)->historialDe($o) as $fila) {
            $eventos[] = ['cuando' => $fila->fecha, 'texto' => ($fila->transicion ?? 'Cambio').": entró a {$fila->hacia}", 'tono' => match ($fila->hacia) {
                'Ganada' => 'success', 'Perdida' => 'danger', default => 'morado'
            }];
        }

        foreach ($versiones as $v) {
            if ($v->fechaEmision !== null) {
                $eventos[] = ['cuando' => $v->fechaEmision, 'texto' => "Se emitió {$v->identificador} por {$v->moneda} ".number_format((float) $v->total, 2), 'tono' => 'morado'];
            }
        }

        usort($eventos, fn (array $a, array $b): int => $b['cuando'] <=> $a['cuando']);

        return array_map(fn (array $e): array => ['fecha' => CarbonImmutable::instance($e['cuando'])->format('d/m/Y'), 'texto' => $e['texto'], 'tono' => $e['tono']], $eventos);
    }

    /**
     * Próxima acción vencida (CRM-05, D14) y buena pro cerca (sin fecha todavía) quedan para cuando existan sus datos.
     *
     * @param  list<VersionDeCotizacion>  $versiones
     * @return list<array{tono: string, titulo: string, detalle: string}>
     */
    private function atencion(Oportunidad $o, array $versiones, bool $veCostos): array
    {
        $etapa = EtapaAbierta::tryFrom($o->estado->codigo);

        if ($etapa === null) {
            return [];
        }

        $portada = app(PortadaOportunidades::class);
        $avisos = [];

        if ($portada->pasaElMaximo($o, $etapa->value)) {
            $avisos[] = ['tono' => 'warning', 'titulo' => "Atascada en {$etapa->etiqueta()}", 'detalle' => $portada->diasEnEtapa($o).' días; el plazo de la etapa es '.$portada->diasMaximos($etapa)];
        }

        if ($o->fecha_cierre_estimada->lt(CarbonImmutable::parse(Calendario::hoy()))) {
            $avisos[] = ['tono' => 'danger', 'titulo' => 'Cierre estimado vencido', 'detalle' => 'Era el '.$o->fecha_cierre_estimada->format('d/m/Y').': actualízalo o ciérrala'];
        }

        $ultima = $versiones === [] ? null : $versiones[count($versiones) - 1];

        if ($veCostos && $ultima?->margenPorcentaje !== null && $ultima->margenMinimo !== null && bccomp($ultima->margenPorcentaje, $ultima->margenMinimo, 2) < 0) {
            $avisos[] = ['tono' => 'warning', 'titulo' => "Margen por debajo de {$ultima->margenMinimo} %", 'detalle' => "{$ultima->identificador}: {$ultima->margenPorcentaje} %"];
        }

        return $avisos;
    }
}
