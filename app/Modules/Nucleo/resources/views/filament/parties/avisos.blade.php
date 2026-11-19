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
        .tq-parties-aviso-incompletas { color: #92400E; background: #FFFBEB; border-color: #FCD34D; }
        .dark .tq-parties-aviso-incompletas { color: #FCD34D; background: rgb(146 64 14 / 0.18); border-color: rgb(252 211 77 / 0.35); }
        .tq-parties-aviso-duplicados { color: #1E3A8A; background: #EFF6FF; border-color: #BFDBFE; }
        .dark .tq-parties-aviso-duplicados { color: #BFDBFE; background: rgb(30 58 138 / 0.25); border-color: rgb(191 219 254 / 0.3); }
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
