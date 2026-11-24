{{--
    Observación 9.5 (lienzos `oportunidades-mejoras/Perdidas.dc.html` y `Pronostico.dc.html`): la vista «Resultados»,
    con ¿por qué perdemos? y contra quién, el pronóstico de cierres y las metas por ejecutivo. Colores del ambiente.
--}}
@php
    $soles = fn ($m) => 'S/ '.number_format((float) $m, 2);
    $corto = fn ($m) => (float) $m >= 1000000 ? 'S/ '.rtrim(rtrim(number_format((float) $m / 1000000, 2), '0'), '.').'M' : ((float) $m >= 1000 ? 'S/ '.number_format((float) $m / 1000, 0).'k' : 'S/ '.number_format((float) $m, 0));
    $maxPron = max(1, ...array_map(fn ($m) => (float) $m['total'], $pronostico));
    $tono = fn (string $c) => 'color-mix(in oklch, '.$c.' calc(100% - var(--tq-tinte-leve, 0%)), var(--tq-tinte, '.$c.'))';
    $colores = ['verde' => $tono('#2F7D4F'), 'ambar' => $tono('#C98A12'), 'coral' => $tono('#D9534A')];
@endphp
<div class="tq-res" style="display: flex; flex-direction: column; gap: 16px">
    <style>
        .tq-res-card { background: var(--tq-superficie, white); border: 1px solid var(--tq-borde, #E5E0D6); border-radius: 14px; padding: 16px 18px; }
        .dark .tq-res-card { background: var(--tq-superficie, var(--gray-900)); border-color: var(--tq-borde, rgb(255 255 255 / .1)); }
        .tq-res-chip { display: inline-flex; align-items: center; height: 30px; padding: 0 12px; border-radius: 999px; border: 1px solid var(--tq-borde, #E5E0D6); background: var(--tq-superficie, white); font-size: 13px; }
        .tq-res-chip[aria-pressed="true"] { background: color-mix(in oklab, var(--primary-500) 12%, var(--tq-superficie, white)); border-color: color-mix(in oklab, var(--primary-500) 35%, transparent); color: var(--primary-700); font-weight: 600; }
        .dark .tq-res-chip[aria-pressed="true"] { color: var(--primary-300); }
        .tq-res-sub { font-size: 12px; color: var(--gray-500); }
        .tq-res-barra { height: 10px; border-radius: 99px; background: color-mix(in oklab, var(--gray-400) 20%, transparent); overflow: hidden; }
        .tq-res-barra > span { display: block; height: 100%; border-radius: 99px; }
        .tq-res-tabla { width: 100%; font-size: 13px; border-collapse: collapse; font-variant-numeric: tabular-nums; }
        .tq-res-tabla th { text-align: start; font-weight: 600; color: var(--gray-500); font-size: 12px; padding: 6px 4px; border-bottom: 1px solid var(--tq-borde, #EFEBE3); }
        .tq-res-tabla td { padding: 8px 4px; border-bottom: 1px solid var(--tq-borde, #EFEBE3); }
        .tq-res-dos { display: grid; grid-template-columns: repeat(auto-fit, minmax(22rem, 1fr)); gap: 16px; }
    </style>

    <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap">
        @foreach ($periodos as $clave => $etiqueta)
            <button type="button" class="tq-res-chip" aria-pressed="{{ $this->periodo === $clave ? 'true' : 'false' }}" wire:click="$set('periodo', '{{ $clave }}')">{{ $etiqueta }}</button>
        @endforeach
        <span class="tq-res-sub">· {{ $rango }}</span>
        <span style="flex: 1"></span>
        {{ $this->metasAction }}
    </div>

    <div class="tq-res-dos">
        {{-- ¿Por qué perdemos? --}}
        <section class="tq-res-card" style="display: flex; flex-direction: column; gap: 12px">
            <div><strong>¿Por qué perdemos?</strong> <span class="tq-res-sub">· {{ $perdemos['total'] }} {{ $perdemos['total'] === 1 ? 'perdida' : 'perdidas' }} · {{ $corto($perdemos['monto']) }}</span></div>
            @forelse ($perdemos['motivos'] as $m)
                <div style="display: grid; grid-template-columns: 8.5rem 1fr 3rem; gap: 10px; align-items: center; font-size: 13px" title="{{ $m['cantidad'] }} · {{ $soles($m['monto']) }}">
                    <span>{{ $m['nombre'] }}</span>
                    <span class="tq-res-barra"><span style="width: {{ max(3, $m['porcentaje']) }}%; background: {{ $loop->first ? $colores['coral'] : 'color-mix(in oklab, var(--primary-500) 55%, transparent)' }}"></span></span>
                    <b style="text-align: end">{{ $m['porcentaje'] }} %</b>
                </div>
            @empty
                <div class="tq-res-sub">Ninguna perdida en este periodo.</div>
            @endforelse
            <div style="margin-top: 6px"><strong style="font-size: 14px">Contra quién perdemos</strong></div>
            @if ($perdemos['contra'] === [])
                <div class="tq-res-sub">Sin ganadores registrados al marcar perdida.</div>
            @else
                <table class="tq-res-tabla">
                    <thead><tr><th>Competidor</th><th style="text-align: end">Veces</th><th style="text-align: end">Más barato en promedio</th></tr></thead>
                    <tbody>
                        @foreach ($perdemos['contra'] as $c)
                            <tr><td>{{ $c['nombre'] }}</td><td style="text-align: end">{{ $c['veces'] }}</td>
                                <td style="text-align: end; color: {{ $c['masBarato'] !== null && (float) $c['masBarato'] < 0 ? $colores['coral'] : 'inherit' }}">{{ $c['masBarato'] === null ? 'precio desconocido' : ((float) $c['masBarato'] > 0 ? '+'.$c['masBarato'].' % (más caro)' : str_replace('-', '−', $c['masBarato']).' %') }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </section>

        {{-- Metas por ejecutivo --}}
        <section class="tq-res-card" style="display: flex; flex-direction: column; gap: 14px">
            <div><strong>Metas {{ $this->periodo === 'anio' ? 'del año' : ($this->periodo === 'mes' ? 'del mes' : 'del trimestre') }}</strong> <span class="tq-res-sub">· ganado contra meta</span></div>
            @forelse ($metas as $m)
                <div style="display: flex; gap: 12px; align-items: center">
                    <span style="flex: none; width: 34px; height: 34px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font: 700 12px Inter, sans-serif; background: var(--gray-100); color: var(--gray-700)">{{ $m['iniciales'] }}</span>
                    <div style="flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 4px">
                        <div style="display: flex; justify-content: space-between; font-size: 13px; gap: 8px"><span style="font-weight: 600">{{ $m['nombre'] }}</span><span style="font-variant-numeric: tabular-nums">{{ $corto($m['ganado']) }} de {{ $corto($m['meta']) }}</span></div>
                        <span class="tq-res-barra"><span style="width: {{ min(100, max(2, $m['porcentaje'])) }}%; background: {{ $colores[$m['tono']] }}"></span></span>
                        <div class="tq-res-sub" style="color: {{ $colores[$m['tono']] }}">{{ $m['porcentaje'] }} % · {{ $m['porcentaje'] >= 100 ? 'meta cumplida' : 'faltan '.$corto($m['falta']) }}</div>
                    </div>
                </div>
            @empty
                <div class="tq-res-sub">Sin metas cargadas: ponlas en «Metas», arriba.</div>
            @endforelse
        </section>
    </div>

    {{-- Pronóstico de cierres --}}
    <section class="tq-res-card" style="display: flex; flex-direction: column; gap: 12px">
        <div style="display: flex; gap: 16px; align-items: center; flex-wrap: wrap">
            <strong>Pronóstico de cierres</strong><span class="tq-res-sub">por fecha de cierre esperada · las abiertas</span>
            <span style="flex: 1"></span>
            <span class="tq-res-sub" style="display: inline-flex; gap: 6px; align-items: center"><span style="width: 12px; height: 12px; border-radius: 3px; background: var(--primary-500)"></span>Ponderado (monto × probabilidad)</span>
            <span class="tq-res-sub" style="display: inline-flex; gap: 6px; align-items: center"><span style="width: 12px; height: 12px; border-radius: 3px; background: color-mix(in oklab, var(--primary-500) 22%, transparent)"></span>Si se ganara todo</span>
        </div>
        <div style="display: grid; grid-template-columns: repeat(6, 1fr); gap: 14px; align-items: end; height: 13rem">
            @foreach ($pronostico as $m)
                <div style="display: flex; flex-direction: column; align-items: center; gap: 6px; height: 100%; justify-content: flex-end" title="{{ $m['cantidad'] }} {{ $m['cantidad'] === 1 ? 'oportunidad' : 'oportunidades' }} · ponderado {{ $soles($m['ponderado']) }} · todo {{ $soles($m['total']) }}">
                    <span style="font-size: 12px; font-weight: 700; font-variant-numeric: tabular-nums">{{ $corto($m['ponderado']) }}</span>
                    <div style="position: relative; width: 70%; max-width: 4.5rem; height: {{ max(2, round((float) $m['total'] * 100 / $maxPron)) }}%; border-radius: 8px 8px 3px 3px; background: color-mix(in oklab, var(--primary-500) 22%, transparent)">
                        <div style="position: absolute; inset: auto 0 0 0; height: {{ (float) $m['total'] > 0 ? round((float) $m['ponderado'] * 100 / (float) $m['total']) : 0 }}%; border-radius: 6px 6px 3px 3px; background: var(--primary-500)"></div>
                    </div>
                    <span class="tq-res-sub">{{ $m['mes'] }}</span>
                </div>
            @endforeach
        </div>
    </section>
</div>
