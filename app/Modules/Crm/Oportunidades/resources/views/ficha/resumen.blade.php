{{--
    El Resumen de la ficha de una oportunidad (plan 2.21d, D10; prototipo aprobado). A la izquierda: el recorrido de los
    documentos, qué falta para avanzar (la compuerta, con la forma de cumplir lo que da su módulo), cómo se fue negociando
    (gráfico del total por versión; el margen solo con permiso de costos, que sin él ni llega) y la línea de tiempo. A la
    derecha: atención, competencia y decisor. Recibe `$r` (`ResumenDeLaFicha::resumen`) y `$oportunidadId`. Colores del
    sistema por nombre.
--}}
@php
    $tono = fn (string $t): string => match ($t) { 'danger' => 'var(--danger-600)', 'warning' => 'var(--warning-600)', 'success' => 'var(--success-600)', 'morado' => 'var(--morado-600)', default => 'var(--gray-400)' };
@endphp
<div class="tq-op-res">
    <style>
        .tq-op-res { display: grid; grid-template-columns: minmax(0, 1.6fr) minmax(0, 1fr); gap: 1rem; align-items: start; }
        @media (max-width: 1023px) { .tq-op-res { grid-template-columns: minmax(0, 1fr); } }
        .tq-op-col { display: flex; flex-direction: column; gap: 1rem; }
        .tq-op-exp { font-size: 0.8125rem; color: var(--gray-500); margin-bottom: 0.6rem; }
        .tq-op-reco { display: flex; flex-wrap: wrap; align-items: stretch; gap: 0.4rem; }
        .tq-op-nodo { min-width: 8.5rem; flex: 1 1 8.5rem; padding: 0.6rem 0.7rem; border-radius: 0.6rem; border: 1px solid var(--gray-200); background: white; font-size: 0.8125rem; }
        .dark .tq-op-nodo { background: var(--gray-900); border-color: var(--gray-700); }
        .tq-op-nodo[data-tipo="futuro"] { border-style: dashed; background: transparent; color: var(--gray-500); }
        .tq-op-nodo[data-tipo="crm"] { border-color: color-mix(in oklab, var(--morado-600) 40%, transparent); }
        .tq-op-nodo[data-tipo="erp"] { border-color: color-mix(in oklab, var(--info-600) 40%, transparent); }
        .tq-op-nodo .tq-t { font-size: 0.6875rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--gray-500); }
        .tq-op-nodo .tq-c { font-weight: 600; }
        .tq-op-nodo .tq-v { display: flex; flex-wrap: wrap; gap: 0.25rem; margin-top: 0.3rem; }
        .tq-op-nodo .tq-v i { font-style: normal; font-size: 0.6875rem; padding: 0 0.35rem; border-radius: 9999px; background: var(--gray-100); }
        .dark .tq-op-nodo .tq-v i { background: var(--gray-800); }
        .tq-op-flecha { align-self: center; color: var(--gray-400); }
        .tq-op-req { display: flex; flex-wrap: wrap; gap: 0.5rem; align-items: baseline; padding: 0.35rem 0; font-size: 0.875rem; }
        .tq-op-kpis { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 0.5rem; margin-top: 0.75rem; }
        .tq-op-kpi { padding: 0.5rem 0.6rem; border-radius: 0.5rem; background: var(--gray-50); font-size: 0.75rem; color: var(--gray-500); }
        .dark .tq-op-kpi { background: rgb(255 255 255 / 0.04); }
        .tq-op-kpi b { display: block; font-size: 1.125rem; color: var(--gray-950); font-variant-numeric: tabular-nums; }
        .dark .tq-op-kpi b { color: white; }
        .tq-op-tabla { width: 100%; border-collapse: collapse; font-size: 0.8125rem; margin-top: 0.5rem; }
        .tq-op-tabla th { text-align: start; font-weight: 500; color: var(--gray-500); padding: 0.3rem 0.4rem; border-bottom: 1px solid var(--gray-200); }
        .tq-op-tabla td { padding: 0.35rem 0.4rem; border-bottom: 1px solid var(--gray-100); }
        .tq-op-tabla .tq-n { text-align: end; font-variant-numeric: tabular-nums; }
        .tq-op-vl { display: flex; flex-direction: column; gap: 0.35rem; border-inline-start: 2px solid var(--gray-200); padding-inline-start: 0.75rem; font-size: 0.8125rem; }
        .tq-op-vl div { position: relative; }
        .tq-op-vl div::before { content: ''; position: absolute; inset-inline-start: -1.1rem; top: 0.35rem; width: 0.55rem; height: 0.55rem; border-radius: 9999px; background: var(--c); }
        .tq-op-alerta { display: flex; gap: 0.6rem; padding: 0.5rem 0.6rem; border-radius: 0.5rem; background: color-mix(in oklab, var(--c) 10%, transparent); font-size: 0.8125rem; margin-bottom: 0.4rem; }
        .tq-op-alerta b { display: block; color: var(--c); }
    </style>

    <div class="tq-op-col">
        <x-filament::section heading="Recorrido de los documentos" compact data-seccion="recorrido">
            <p class="tq-op-exp">De dónde salen los números y adónde van. Los cuadros punteados son lo que todavía no pasa.</p>
            <div class="tq-op-reco">
                @foreach ($r['recorrido']['nodos'] as $n)
                    @if (! $loop->first)<span class="tq-op-flecha" aria-hidden="true">→</span>@endif
                    <div class="tq-op-nodo" data-tipo="{{ $n['tipo'] }}">
                        <div class="tq-t">{{ $n['titulo'] }}</div>
                        <div class="tq-c">@if (isset($n['url']))<a href="{{ $n['url'] }}" wire:navigate>{{ $n['codigo'] }}</a>@else{{ $n['codigo'] }}@endif</div>
                        <div>{{ $n['nota'] }}</div>
                        @if (! empty($n['versiones']))<div class="tq-v">@foreach ($n['versiones'] as $v)<i>{{ $v }}</i>@endforeach</div>@endif
                    </div>
                @endforeach
            </div>
        </x-filament::section>

        @if ($r['compuerta'] !== null)
            @php $g = $r['compuerta']; @endphp
            <x-filament::section heading="¿Qué falta para avanzar?" compact data-seccion="compuerta">
                <p class="tq-op-exp">Para pasar a {{ $g['hacia'] }} el sistema exige esto. Lo que falta se cumple con «Avanzar etapa» o con su botón.</p>
                @forelse ($g['requisitos'] as $req)
                    <div class="tq-op-req" data-requisito="{{ $req['cumple'] ? 'cumple' : 'falta' }}">
                        <span style="color: {{ $req['cumple'] ? 'var(--success-600)' : 'var(--warning-600)' }}; font-weight: 600">{{ $req['cumple'] ? '✓ Cumplido' : '• Falta' }}</span>
                        <span>{{ $req['texto'] }}</span>
                        @if (! $req['cumple'] && $req['forma'] !== null)
                            @if ($req['forma']->accion !== null)
                                <x-filament::link tag="button" size="sm" icon="heroicon-m-bolt" wire:click="cumplirRequisito(@js($req['requisito']), {{ $oportunidadId }})">{{ $req['forma']->etiqueta }}</x-filament::link>
                            @else
                                <x-filament::link :href="$req['forma']->url" size="sm" icon="heroicon-m-arrow-top-right-on-square">{{ $req['forma']->etiqueta }}</x-filament::link>
                            @endif
                        @endif
                    </div>
                @empty
                    <p class="tq-op-exp">Nada propio de esta etapa.</p>
                @endforelse
                <div class="tq-op-kpis">
                    <div class="tq-op-kpi">Días en la etapa<b @if ($g['atascada']) style="color: var(--warning-600)" @endif>{{ $g['dias'] }}</b>{{ $g['maximo'] > 0 ? 'Plazo: '.$g['maximo'].' días' : 'Sin plazo' }}</div>
                    {{-- D14: la próxima acción llega con CRM-05; su lugar queda aquí. --}}
                    <div class="tq-op-kpi" data-proxima-accion>Próxima acción<b>—</b>Llega con las actividades</div>
                    <div class="tq-op-kpi">Si avanza<b>{{ $g['siAvanza'] }} %</b>de probabilidad</div>
                </div>
            </x-filament::section>
        @endif

        <x-filament::section heading="¿Cómo se fue negociando?" compact data-seccion="negociacion">
            @if ($r['negociacion']['filas'] === [])
                <p class="tq-op-exp">Todavía no hay cotizaciones. Se emiten desde el costeo de la oportunidad.</p>
            @else
                <p class="tq-op-exp">Cada versión conserva su número.@unless ($r['veCostos']) El margen lo ve solo quien tiene permiso de costos.@endunless</p>
                @if ($r['negociacion']['puntos'] !== [])
                    <svg viewBox="0 0 520 140" style="width: 100%; max-width: 40rem" role="img" aria-label="Total de cada versión de la cotización">
                        <polyline points="{{ collect($r['negociacion']['puntos'])->map(fn ($p) => $p['x'].','.$p['y'])->implode(' ') }}" fill="none" stroke="var(--morado-600)" stroke-width="2.5"/>
                        @foreach ($r['negociacion']['puntos'] as $p)
                            <circle cx="{{ $p['x'] }}" cy="{{ $p['y'] }}" r="5" fill="white" stroke="var(--morado-600)" stroke-width="2.5"/>
                            <text x="{{ $p['x'] }}" y="{{ $p['y'] - 10 }}" font-size="11" text-anchor="middle" font-weight="600" fill="currentColor">{{ $p['total'] }}</text>
                            <text x="{{ $p['x'] }}" y="134" font-size="10" text-anchor="middle" fill="var(--gray-500)">{{ $p['etiqueta'] }}</text>
                        @endforeach
                    </svg>
                @endif
                <table class="tq-op-tabla">
                    <thead><tr><th>Cotización</th><th>Emitida</th><th class="tq-n">Total</th>@if ($r['veCostos'])<th class="tq-n">Margen</th>@endif<th></th></tr></thead>
                    <tbody>
                        @foreach ($r['negociacion']['filas'] as $f)
                            <tr>
                                <td><a href="{{ $f['url'] }}" wire:navigate><b>{{ $f['identificador'] }}</b></a></td>
                                <td>{{ $f['fecha'] }}</td>
                                <td class="tq-n">{{ $f['total'] }}</td>
                                @if ($r['veCostos'])<td class="tq-n" @if ($f['bajo']) style="color: var(--warning-600); font-weight: 600" @endif>{{ $f['margen'] !== null ? $f['margen'].' %' : '—' }}</td>@endif
                                <td>@if ($f['aceptada'])<x-filament::badge color="success">aceptada</x-filament::badge>@endif</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </x-filament::section>

        <x-filament::section heading="Línea de tiempo" compact data-seccion="tiempo">
            <div class="tq-op-vl">
                @foreach ($r['tiempo'] as $e)
                    <div style="--c: {{ $tono($e['tono']) }}"><small class="text-gray-500">{{ $e['fecha'] }}</small> · {{ $e['texto'] }}</div>
                @endforeach
            </div>
        </x-filament::section>
    </div>

    <div class="tq-op-col">
        <x-filament::section heading="¿Qué necesita atención?" compact data-seccion="atencion">
            @forelse ($r['atencion'] as $a)
                <div class="tq-op-alerta" style="--c: {{ $tono($a['tono']) }}"><div><b>{{ $a['titulo'] }}</b><small>{{ $a['detalle'] }}</small></div></div>
            @empty
                <p class="tq-op-exp">Nada pendiente.</p>
            @endforelse
        </x-filament::section>
        <x-filament::section heading="Competencia" compact data-seccion="competencia">
            @forelse ($r['competencia'] as $c)
                <div class="tq-op-req"><b>{{ $c['nombre'] }}</b><small class="text-gray-500">{{ $c['nota'] }}</small></div>
            @empty
                <p class="tq-op-exp">Sin competidores registrados.</p>
            @endforelse
        </x-filament::section>
        <x-filament::section heading="Contacto decisor" compact data-seccion="decisor">
            @if ($r['decisor'] !== null)
                <div class="tq-op-req"><b>{{ $r['decisor']['nombre'] }}</b><small class="text-gray-500">{{ $r['decisor']['cargo'] ?? 'Decide la compra' }}</small></div>
            @else
                <p class="tq-op-exp">Sin identificar: se elige o registra al avanzar.</p>
            @endif
        </x-filament::section>
    </div>
</div>
