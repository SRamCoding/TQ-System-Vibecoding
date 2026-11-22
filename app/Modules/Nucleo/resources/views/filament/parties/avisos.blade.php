{{--
    Portada de Personas y empresas (plan 1.4m, D2 y D3): los avisos de fichas incompletas y de posibles duplicados,
    sobre la tabla. «Ver cuáles» filtra la tabla en el sitio, sin recargar; «Ver todas» quita ese filtro.
    Recibe `$incompletas` (motivo => [texto, cantidad]), `$totalIncompletas`, `$duplicados` y `$filtroCalidad`.
--}}
@php
    /** @var array<string, array{texto: string, cantidad: int}> $incompletas */
    $partes = array_map(fn (array $m): string => $m['cantidad'].' '.$m['texto'], $incompletas);
@endphp
<div class="tq-parties-avisos">
    <style>
        .tq-parties-avisos { display: flex; flex-direction: column; gap: 0.5rem; }
        .tq-parties-aviso { display: flex; flex-wrap: wrap; align-items: center; gap: 0.25rem 0.75rem; padding: 0.625rem 0.875rem; border-radius: 0.625rem; font-size: 0.875rem; border: 1px solid; }
        .tq-parties-aviso-incompletas { color: var(--warning-800); background: color-mix(in oklch, var(--tq-superficie) 85%, var(--warning-500)); border-color: color-mix(in oklch, var(--tq-borde) 50%, var(--warning-500)); }
        .dark .tq-parties-aviso-incompletas { color: color-mix(in oklch, var(--tq-borde) 50%, var(--warning-500)); background: color-mix(in oklch, var(--tq-superficie) 82%, var(--warning-500)); border-color: color-mix(in oklch, var(--tq-borde) 60%, var(--warning-400)); }
        .tq-parties-aviso-duplicados { color: var(--primary-700); background: color-mix(in oklch, var(--tq-superficie) 90%, var(--primary-500)); border-color: color-mix(in oklch, var(--tq-borde) 60%, var(--primary-400)); }
        .dark .tq-parties-aviso-duplicados { color: color-mix(in oklch, var(--tq-borde) 60%, var(--primary-400)); background: color-mix(in oklch, var(--tq-superficie) 80%, var(--primary-600)); border-color: color-mix(in oklch, var(--tq-borde) 60%, var(--primary-400)); }
        .tq-parties-aviso button { font-weight: 600; text-decoration: underline; text-underline-offset: 2px; }
    </style>

    @if ($totalIncompletas > 0)
        <div class="tq-parties-aviso tq-parties-aviso-incompletas" role="status">
            <span><strong>{{ $totalIncompletas }} {{ $totalIncompletas === 1 ? 'ficha incompleta' : 'fichas incompletas' }}</strong>: {{ implode(', ', $partes) }}.</span>
            @if ($filtroCalidad === 'incompletas')
                <button type="button" wire:click="verCalidad(null)">Ver todas</button>
            @else
                <button type="button" wire:click="verCalidad('incompletas')">Ver cuáles</button>
            @endif
        </div>
    @endif

    @if ($duplicados > 0)
        <div class="tq-parties-aviso tq-parties-aviso-duplicados" role="status">
            <span><strong>{{ $duplicados }} {{ $duplicados === 1 ? 'ficha podría estar duplicada' : 'fichas podrían estar duplicadas' }}</strong>: nombres muy parecidos o el mismo documento. Revísalas.</span>
            @if ($filtroCalidad === 'duplicados')
                <button type="button" wire:click="verCalidad(null)">Ver todas</button>
            @else
                <button type="button" wire:click="verCalidad('duplicados')">Ver cuáles</button>
            @endif
        </div>
    @endif
</div>
