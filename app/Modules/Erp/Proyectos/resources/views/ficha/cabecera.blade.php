{{--
    La cabecera de la ficha del proyecto (plan 2.61, D5): la foto de fondo difuminada (o el dibujo genérico, D12), con
    código, nombre, cliente, estado, jefe y plazo encima. El texto va sobre un velo oscuro para leerse con cualquier foto.
--}}
@php /** @var array<string, mixed> $cabecera */ @endphp
<div class="tq-cabecera-proyecto">
    <style>
        .tq-cabecera-proyecto { position: relative; overflow: hidden; border-radius: 0.75rem; min-height: 9rem; color: white; isolation: isolate; }
        .tq-cabecera-proyecto .tq-fondo { position: absolute; inset: -1.5rem; z-index: -2; filter: blur(14px) saturate(1.1); }
        .tq-cabecera-proyecto .tq-fondo > img, .tq-cabecera-proyecto .tq-fondo > svg { width: 100%; height: 100%; object-fit: cover; display: block; }
        .tq-cabecera-proyecto::after { content: ''; position: absolute; inset: 0; z-index: -1; background: linear-gradient(90deg, rgb(3 7 18 / 0.72), rgb(3 7 18 / 0.35)); }
        .tq-cabecera-proyecto .tq-contenido { display: flex; gap: 1.25rem; align-items: center; padding: 1.25rem 1.5rem; }
        .tq-cabecera-proyecto .tq-miniatura { flex: none; width: 10rem; aspect-ratio: 16 / 9; border-radius: 0.5rem; overflow: hidden; box-shadow: 0 4px 14px rgb(0 0 0 / 0.35); }
        .tq-cabecera-proyecto .tq-miniatura > img, .tq-cabecera-proyecto .tq-miniatura > svg { width: 100%; height: 100%; object-fit: cover; display: block; }
        @media (max-width: 639px) { .tq-cabecera-proyecto .tq-miniatura { display: none; } }
        .tq-cabecera-proyecto .tq-codigo { font-family: var(--mono-font-family, ui-monospace, monospace); font-size: 0.8125rem; opacity: 0.85; }
        .tq-cabecera-proyecto .tq-nombre { font-size: 1.375rem; font-weight: 700; line-height: 1.25; }
        .tq-cabecera-proyecto .tq-datos { display: flex; flex-wrap: wrap; gap: 0.35rem 1rem; margin-top: 0.4rem; font-size: 0.875rem; opacity: 0.92; align-items: center; }
    </style>
    <div class="tq-fondo" aria-hidden="true">
        @if ($cabecera['foto'] !== null)<img src="{{ $cabecera['foto'] }}" alt="">@else{!! $cabecera['dibujo'] !!}@endif
    </div>
    <div class="tq-contenido">
        <div class="tq-miniatura">
            @if ($cabecera['foto'] !== null)<img src="{{ $cabecera['foto'] }}" alt="Foto de {{ $cabecera['nombre'] }}">@else{!! $cabecera['dibujo'] !!}@endif
        </div>
        <div>
            <div class="tq-codigo">{{ $cabecera['codigo'] }}</div>
            <div class="tq-nombre">{{ $cabecera['nombre'] }}</div>
            <div class="tq-datos">
                <x-filament::badge :color="$cabecera['color_estado']">{{ $cabecera['estado'] }}</x-filament::badge>
                <span>{{ $cabecera['cliente'] }}</span>
                <span>Jefe: {{ $cabecera['jefe'] }}</span>
                <span>Del {{ $cabecera['inicio'] }} al {{ $cabecera['fin'] }}</span>
            </div>
        </div>
    </div>
</div>
