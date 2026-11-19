{{--
    La cabecera de la ficha de una oportunidad (plan 2.21d, D10; prototipo aprobado): la foto de referencia de fondo (o
    el dibujo de su línea de solución), número, línea, tipo de cliente, nombre, entidad, ejecutivo y fechas; a la derecha
    el monto y el ponderado; abajo, la línea de etapas con la fecha de cada una. Recibe `$c`
    (`ResumenDeLaFicha::cabecera`). El texto va sobre un velo oscuro para leerse con cualquier foto.
--}}
<div class="tq-op-hero">
    <style>
        .tq-op-hero { position: relative; overflow: hidden; border-radius: 0.75rem; color: white; isolation: isolate; }
        .tq-op-hero .tq-fondo { position: absolute; inset: -1.5rem; z-index: -2; filter: blur(12px) saturate(1.1); }
        .tq-op-hero .tq-fondo > img, .tq-op-hero .tq-fondo > svg { width: 100%; height: 100%; object-fit: cover; display: block; }
        .tq-op-hero::after { content: ''; position: absolute; inset: 0; z-index: -1; background: linear-gradient(90deg, rgb(3 7 18 / 0.75), rgb(3 7 18 / 0.4)); }
        .tq-op-hero .tq-dentro { display: flex; flex-wrap: wrap; gap: 1rem 2rem; align-items: flex-start; justify-content: space-between; padding: 1.25rem 1.5rem 0.75rem; }
        .tq-op-hero .tq-sub { font-size: 0.8125rem; opacity: 0.85; }
        .tq-op-hero h2 { font-size: 1.375rem; font-weight: 700; line-height: 1.25; margin: 0.15rem 0; }
        .tq-op-hero .tq-meta { display: flex; flex-wrap: wrap; gap: 0.25rem 1.25rem; margin-top: 0.5rem; font-size: 0.8125rem; }
        .tq-op-hero .tq-meta b { display: block; font-size: 0.6875rem; font-weight: 500; text-transform: uppercase; letter-spacing: 0.05em; opacity: 0.75; }
        .tq-op-hero .tq-grande { text-align: end; }
        .tq-op-hero .tq-grande .tq-m { font-size: 1.75rem; font-weight: 700; font-variant-numeric: tabular-nums; }
        .tq-op-hero .tq-grande .tq-p { font-size: 0.875rem; opacity: 0.9; }
        .tq-op-pasos { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: 0.5rem; padding: 0.75rem 1.5rem 1rem; }
        .tq-op-paso { display: flex; flex-direction: column; gap: 0.15rem; font-size: 0.75rem; opacity: 0.6; border-top: 3px solid rgb(255 255 255 / 0.3); padding-top: 0.4rem; }
        .tq-op-paso b { font-size: 0.8125rem; }
        .tq-op-paso.tq-hecho { opacity: 0.95; border-top-color: rgb(255 255 255 / 0.85); }
        .tq-op-paso.tq-ahora { opacity: 1; border-top-color: var(--morado-400); }
        .tq-op-paso.tq-perdida { opacity: 1; border-top-color: var(--danger-400); }
    </style>
    <div class="tq-fondo" aria-hidden="true">@if ($c['foto'] !== null)<img src="{{ $c['foto'] }}" alt="">@else{!! $c['dibujo'] !!}@endif</div>
    <div class="tq-dentro">
        <div>
            <div class="tq-sub">{{ $c['numero'] }} · {{ $c['linea'] }} · {{ $c['cliente'] }}</div>
            <h2>{{ $c['nombre'] }}</h2>
            <div class="tq-sub">{{ $c['entidad'] }}</div>
            <div class="tq-meta">
                <div><b>Ejecutivo</b>{{ $c['ejecutivo'] }}</div>
                <div><b>Abierta</b>{{ $c['abierta'] }}</div>
                <div><b>{{ $c['cerrada'] ? 'Cerrada' : 'Cierre esperado' }}</b>{{ $c['cierre'] }}</div>
            </div>
        </div>
        <div class="tq-grande">
            <div class="tq-m">{{ $c['monto'] }}</div>
            <div class="tq-p">{{ $c['detalle'] }}</div>
        </div>
    </div>
    <div class="tq-op-pasos" aria-label="Etapas">
        @foreach ($c['pasos'] as $paso)
            <div @class(['tq-op-paso', 'tq-hecho' => $paso['estado'] === 'hecho', 'tq-ahora' => $paso['estado'] === 'ahora', 'tq-perdida' => $paso['estado'] === 'perdida']) @if ($paso['estado'] === 'ahora') aria-current="step" @endif>
                <b>{{ $paso['nombre'] }}</b>
                <span>{{ $paso['fecha'] ?? '—' }}{{ $paso['dias'] !== null ? ' · '.$paso['dias'].' días' : '' }}</span>
            </div>
        @endforeach
    </div>
</div>
