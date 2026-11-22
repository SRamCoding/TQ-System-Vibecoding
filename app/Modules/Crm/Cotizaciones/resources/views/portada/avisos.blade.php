{{--
    Portada de Cotizaciones (D19), como la de Presupuestos: lo que hay que revisar, sobre la tabla. «Ver cuáles» pone el
    filtro «Revisar» en el sitio, sin recargar; «Ver todas» lo quita. Recibe `$avisos` y `$filtro`.
--}}
@php
    $textos = [
        'por-vencer' => [1 => 'cotización emitida por vencer', 0 => 'cotizaciones emitidas por vencer', 'mas' => 'Conviene llamar al cliente antes de que venza su validez.'],
    ];
@endphp
<div class="tq-cotizaciones-avisos">
    <style>
        .tq-cotizaciones-avisos { display: flex; flex-direction: column; gap: 0.5rem; }
        .tq-cotizaciones-aviso { display: flex; flex-wrap: wrap; align-items: center; gap: 0.25rem 0.75rem; padding: 0.625rem 0.875rem; border-radius: 0.625rem; font-size: 0.875rem; border: 1px solid; color: var(--primary-700); background: color-mix(in oklch, var(--tq-superficie) 90%, var(--primary-500)); border-color: color-mix(in oklch, var(--tq-borde) 60%, var(--primary-400)); }
        .dark .tq-cotizaciones-aviso { color: color-mix(in oklch, var(--tq-borde) 60%, var(--primary-400)); background: color-mix(in oklch, var(--tq-superficie) 80%, var(--primary-600)); border-color: color-mix(in oklch, var(--tq-borde) 60%, var(--primary-400)); }
        .tq-cotizaciones-aviso button { font-weight: 600; text-decoration: underline; text-underline-offset: 2px; }
    </style>

    @foreach ($avisos as $clave => $cantidad)
        <div class="tq-cotizaciones-aviso" role="status" data-aviso="{{ $clave }}">
            <span><strong>{{ $cantidad }} {{ $textos[$clave][$cantidad === 1 ? 1 : 0] }}</strong>. {{ $textos[$clave]['mas'] }}</span>
            @if ($filtro === $clave)
                <button type="button" wire:click="verAviso(null)">Ver todas</button>
            @else
                <button type="button" wire:click="verAviso('{{ $clave }}')">Ver cuáles</button>
            @endif
        </div>
    @endforeach
</div>
