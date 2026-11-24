{{--
    Observación 9.4 (lienzo `campanias-vista/Canales.dc.html`): cómo rinde cada canal en el periodo. Una tarjeta por canal
    con leads, calificados, ganados, ganado, margen, gasto y retorno; clic lleva a Mis oportunidades con Etapa = Por
    calificar y Origen = ese canal. Recibe `$canales` (CampaniasService::canales), `$soles` y `$veces`.
--}}
@php
    $c = $canales['cifras'];
    $periodos = \App\Modules\Crm\Campanias\Services\CampaniasService::PERIODOS;
    $textoPeriodo = ['mes' => 'del mes', 'trimestre' => 'del trimestre', 'anio' => 'del año'][$this->periodo] ?? 'del mes';
@endphp
<style>
    .tq-can-barra { display: flex; justify-content: space-between; align-items: center; gap: 8px; flex-wrap: wrap; }
    .tq-can-tarjeta { padding: 16px 18px; display: flex; flex-direction: column; gap: 10px; text-align: start; color: inherit; transition: box-shadow .16s ease, transform .16s ease; }
    .tq-can-tarjeta:hover, .tq-can-tarjeta:focus-visible { box-shadow: 0 8px 20px -6px color-mix(in oklab, var(--c-turquesa) 35%, transparent); transform: translateY(-2px); }
    @media (prefers-reduced-motion: reduce) { .tq-can-tarjeta { transition: none; transform: none !important; } }
    .tq-can-icono { width: 34px; height: 34px; border-radius: 10px; background: color-mix(in oklab, var(--c-turquesa) 12%, var(--c-sup)); color: var(--c-turquesa); display: inline-flex; align-items: center; justify-content: center; flex: none; }
    .tq-can-tres { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; text-align: center; }
    .tq-can-tres b { display: block; font-size: 22px; font-weight: 700; font-variant-numeric: tabular-nums; }
    .tq-can-tres span { font-size: 11px; color: var(--c-suave); white-space: nowrap; }
    .tq-can-detalle { display: grid; grid-template-columns: 1fr auto; gap: 4px 12px; font-size: 13px; border-top: 1px solid var(--c-linea); padding-top: 10px; font-variant-numeric: tabular-nums; }
    .tq-can-detalle span:nth-child(odd) { color: var(--c-suave); }
    .tq-can-detalle span:nth-child(even) { text-align: right; font-weight: 600; }
    .tq-can-ir { font-size: 12px; color: var(--c-turquesa); font-weight: 600; }
    .tq-can-retorno { font-size: 12px; font-weight: 600; border-radius: 6px; padding: 2px 8px; margin-inline-start: auto; white-space: nowrap; }
    .tq-can-orden { border: 1px solid var(--c-borde); border-radius: 999px; background: var(--c-sup); font-size: 13px; height: 30px; padding: 0 2rem 0 12px; color: inherit; }
</style>

<section class="tq-camp-cifras" aria-label="Resumen de los canales">
    <div class="tq-camp-card tq-camp-cifra"><div class="t">Leads {{ $textoPeriodo }}</div><div class="v">{{ $c['leads'] }}</div><div class="d">de {{ $c['canales'] }} {{ $c['canales'] === 1 ? 'canal' : 'canales' }}</div></div>
    <div class="tq-camp-card tq-camp-cifra"><div class="t">Calificados</div><div class="v">{{ $c['calificados'] }}</div><div class="d">{{ $c['tasa'] }} % de los leads</div></div>
    <div class="tq-camp-card tq-camp-cifra"><div class="t">Trae más</div><div class="v">{{ $c['masLeads']['nombre'] ?? '—' }}</div><div class="d">{{ $c['masLeads'] === null ? 'sin leads' : $c['masLeads']['leads'].' leads' }}</div></div>
    <div class="tq-camp-card tq-camp-cifra"><div class="t">Rinde más</div><div class="v" style="color: var(--c-verde)">{{ $c['rinde']['nombre'] ?? '—' }}</div><div class="d">{{ $c['rinde'] === null ? 'ningún canal con gasto' : $veces($c['rinde']['retorno']).' de retorno' }}</div></div>
</section>

<div class="tq-can-barra">
    <x-filament::link tag="a" href="/entrada/prueba" target="_blank" icon="heroicon-o-globe-alt" size="sm">Probar la entrada de la página web</x-filament::link>
    <div style="display: flex; gap: 8px; flex-wrap: wrap; align-items: center">
        @foreach ($periodos as $clave => $etiqueta)
            <button type="button" class="tq-camp-chip" aria-pressed="{{ $this->periodo === $clave ? 'true' : 'false' }}" wire:click="$set('periodo', '{{ $clave }}')">{{ $etiqueta }}</button>
        @endforeach
        <select class="tq-can-orden" wire:model.live="ordenCanales" aria-label="Ordenar los canales">
            <option value="leads">Ordenar: leads</option>
            <option value="ganado">Ordenar: ganado</option>
            <option value="retorno">Ordenar: retorno</option>
        </select>
    </div>
</div>

@if ($canales['tarjetas'] === [])
    <div class="tq-camp-card" style="padding: 2rem; text-align: center; color: var(--c-suave)">Ningún lead llegó {{ $textoPeriodo === 'del año' ? 'este año' : ($textoPeriodo === 'del trimestre' ? 'este trimestre' : 'este mes') }}.</div>
@else
    <div class="tq-camp-grid" style="grid-template-columns: repeat(auto-fill, minmax(18rem, 1fr))">
        @foreach ($canales['tarjetas'] as $t)
            <a class="tq-camp-card tq-can-tarjeta" wire:key="canal-{{ $t['id'] }}" wire:navigate
               href="/oportunidades?etapa[0]=por_calificar&origen[0]={{ $t['codigo'] }}" title="Ver sus leads en Oportunidades">
                <div style="display: flex; align-items: center; gap: 10px">
                    <span class="tq-can-icono"><x-filament::icon :icon="$t['icono']" class="h-5 w-5" /></span>
                    <span style="font-size: 15px; font-weight: 700">{{ $t['nombre'] }}</span>
                    <span style="margin-inline-start: auto">@include('campanias::retorno', ['r' => $t['retorno'], 'e' => $t['esperado'], 'ganadas' => $t['ganadas'], 'detalle' => $t['esperadoDetalle'], 'gastado' => $t['gastado'], 'tamano' => 15])</span>
                </div>
                {{-- Observación 9.9: contactos nuevos o ya registrados, y barras «N de X». --}}
                @include('campanias::embudo-contactos', ['t' => ['contactos' => $t['contactos'], 'nuevos' => $t['nuevos'], 'yaRegistrados' => $t['yaRegistrados'], 'listaRegistrados' => $t['listaRegistrados'], 'calificados' => $t['calificados'], 'ganadas' => $t['ganadas']]])
                                <div class="tq-can-detalle">
                    <span>Ganado</span><span>{{ $soles($t['ganado']) }}</span>
                    <span>Margen de lo ganado{{ $t['margenPrevisto'] && $t['ganadas'] > 0 ? ' (previsto)' : '' }}</span><span>{{ $soles($t['margen']) }}</span>
                    <span title="{{ $t['porContactoNuevo'] === null ? '' : 'Por contacto nuevo: '.$soles($t['porContactoNuevo']) }}">Gastado · por lead</span><span>{{ $soles($t['gastado']) }}{{ $t['porLead'] !== null ? ' · '.$soles($t['porLead']) : '' }}</span>
                </div>
                <span class="tq-can-ir">Ver sus leads en Oportunidades →</span>
            </a>
        @endforeach
    </div>
@endif
