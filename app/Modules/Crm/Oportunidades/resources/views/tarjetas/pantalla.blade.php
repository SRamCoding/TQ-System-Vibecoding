{{--
    La vista «Tarjetas» de Oportunidades (plan 2.21d, D1 y D11), como el prototipo aprobado y la portada de Proyectos
    (2.61): filtros con su cantidad a la izquierda; buscador y orden; tarjetas con la foto o el dibujo de la línea de
    solución, la línea y la etapa encima, el anillo con la probabilidad, monto y ponderado, días en la etapa contra su
    plazo y el ejecutivo. Sin barra de etapas (D1). Tocar una tarjeta lleva a la ficha completa (pedido del
    desarrollador, 2026-11-16: sin el panel lateral).
    Colores del sistema por nombre: el anillo en el morado del CRM, verde ganada, rojo perdida.
--}}
@php
    $grupos = ['atencion' => 'Atención', 'etapas' => 'Etapa', 'ejecutivos' => 'Ejecutivo', 'lineas' => 'Línea de solución', 'clientes' => 'Cliente'];
    $anillo = function (array $t): string {
        $p = $t['probabilidad'] / 100;
        $r = 21;
        $c = 2 * M_PI * $r;
        $color = match ($t['codigo_estado']) { 'ganada' => 'var(--success-600)', 'perdida' => 'var(--danger-600)', default => 'var(--morado-600)' };
        $texto = match ($t['codigo_estado']) { 'ganada' => '✓', 'perdida' => '✕', default => $t['probabilidad'].'%' };

        return '<span class="tq-op-anillo" style="color: '.$color.'"><svg viewBox="0 0 52 52" aria-hidden="true"><circle cx="26" cy="26" r="'.$r.'" fill="none" stroke="var(--gray-100)" stroke-width="6"/>'
            .'<circle cx="26" cy="26" r="'.$r.'" fill="none" stroke="currentColor" stroke-width="6" stroke-linecap="round" stroke-dasharray="'.round($c * $p, 2).' '.round($c, 2).'" transform="rotate(-90 26 26)"/></svg><b>'.e($texto).'</b></span>';
    };
@endphp
<div @class(['tq-op-vitrina', 'tq-tablero-detras' => $fichaAbierta !== null])>
    <style>
        .tq-op-vitrina { display: grid; grid-template-columns: 15rem minmax(0, 1fr); gap: 1.5rem; align-items: start; }
        @media (max-width: 1023px) { .tq-op-vitrina { grid-template-columns: minmax(0, 1fr); } }
        .tq-op-filtros { position: sticky; top: 5rem; display: flex; flex-direction: column; gap: 1.25rem; }
        .tq-op-filtros h3 { margin-bottom: 0.4rem; font-size: 0.6875rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; color: var(--gray-500); }
        .tq-op-opcion { display: flex; align-items: center; gap: 0.5rem; padding: 0.2rem 0; font-size: 0.875rem; cursor: pointer; }
        .tq-op-opcion.tq-cero { color: var(--gray-400); }
        .tq-op-opcion .tq-n { margin-inline-start: auto; font-size: 0.75rem; font-variant-numeric: tabular-nums; color: var(--gray-500); }
        .tq-op-barra { display: flex; flex-wrap: wrap; gap: 0.75rem; align-items: center; margin-bottom: 1rem; }
        .tq-op-barra .tq-op-buscar { flex: 1 1 16rem; }
        .tq-op-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(17.5rem, 1fr)); gap: 1rem; }
        .tq-op-card { display: flex; flex-direction: column; overflow: hidden; border-radius: 0.75rem; background: white; box-shadow: 0 1px 2px rgb(0 0 0 / 0.06); outline: 1px solid rgb(3 7 18 / 0.06); text-align: start; cursor: pointer; transition: box-shadow 160ms ease, transform 160ms ease; }
        .dark .tq-op-card { background: var(--gray-900); outline-color: rgb(255 255 255 / 0.08); }
        .tq-op-card:hover, .tq-op-card:focus-visible { box-shadow: 0 8px 20px -6px color-mix(in oklab, var(--morado-600) 35%, transparent); transform: translateY(-2px); }
        @media (prefers-reduced-motion: reduce) { .tq-op-card { transition: none; transform: none !important; } }
        html.tq-sin-animaciones .tq-op-card { transition: none; transform: none !important; }
        .tq-op-foto { position: relative; height: 7.5rem; overflow: hidden; background: var(--gray-100); }
        .tq-op-foto > img, .tq-op-foto > svg { width: 100%; height: 100%; object-fit: cover; display: block; }
        .tq-op-foto .tq-op-linea { position: absolute; left: 0.6rem; bottom: 0.5rem; padding: 0.1rem 0.5rem; border-radius: 9999px; background: rgb(255 255 255 / 0.9); font-size: 0.75rem; font-weight: 600; color: var(--gray-700); }
        .tq-op-foto .tq-op-etapa { position: absolute; right: 0.6rem; top: 0.6rem; }
        .tq-op-tope { display: flex; gap: 0.75rem; align-items: center; padding: 0.85rem 1rem 0.25rem; }
        .tq-op-anillo { position: relative; flex: none; width: 3.25rem; height: 3.25rem; }
        .tq-op-anillo svg { width: 100%; height: 100%; }
        .tq-op-anillo b { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; font-size: 0.75rem; }
        .tq-op-cod { font-family: var(--mono-font-family, ui-monospace, monospace); font-size: 0.75rem; color: var(--gray-500); }
        .tq-op-nom { font-weight: 600; line-height: 1.3; }
        .tq-op-ent { font-size: 0.8125rem; color: var(--gray-500); }
        .tq-op-cuerpo { display: flex; flex-direction: column; gap: 0.35rem; padding: 0 1rem 1rem; }
        .tq-op-monto { font-size: 1.125rem; font-weight: 700; font-variant-numeric: tabular-nums; }
        .tq-op-monto small { margin-inline-start: 0.4rem; font-size: 0.75rem; font-weight: 500; color: var(--gray-500); }
        .tq-op-etl { display: flex; justify-content: space-between; gap: 0.5rem; font-size: 0.75rem; color: var(--gray-500); }
        .tq-op-atascada { color: var(--warning-600); font-weight: 600; }
        .tq-op-pie { display: flex; align-items: center; gap: 0.5rem; margin-top: 0.3rem; padding-top: 0.6rem; border-top: 1px solid var(--gray-100); font-size: 0.8125rem; }
        .dark .tq-op-pie { border-top-color: var(--gray-800); }
    </style>

    <aside class="tq-op-filtros" aria-label="Filtros">
        @foreach ($grupos as $grupo => $titulo)
            @if ($opciones[$grupo] !== [])
                <div>
                    <h3>{{ $titulo }}</h3>
                    @foreach ($opciones[$grupo] as $valor => $op)
                        <label @class(['tq-op-opcion', 'tq-cero' => $op['cantidad'] === 0])>
                            <x-filament::input.checkbox wire:model.live="{{ $grupo }}" value="{{ $valor }}" />{{ $op['etiqueta'] }}<span class="tq-n">{{ $op['cantidad'] }}</span>
                        </label>
                    @endforeach
                </div>
            @endif
        @endforeach
        @if ($conFiltros)
            <x-filament::link tag="button" wire:click="quitarFiltros" icon="heroicon-o-x-mark">Quitar todos los filtros</x-filament::link>
        @endif
    </aside>

    <section>
        <div class="tq-op-barra">
            <div class="tq-op-buscar">
                <x-filament::input.wrapper prefix-icon="heroicon-o-magnifying-glass">
                    <x-filament::input type="search" wire:model.live.debounce.400ms="buscar" placeholder="Buscar por nombre, código o entidad…" aria-label="Buscar oportunidades" />
                </x-filament::input.wrapper>
            </div>
            <x-filament::input.wrapper>
                <x-filament::input.select wire:model.live="orden" aria-label="Orden">
                    @foreach ($ordenes as $valor => $etiqueta)
                        <option value="{{ $valor }}">{{ $etiqueta }}</option>
                    @endforeach
                </x-filament::input.select>
            </x-filament::input.wrapper>
        </div>

        @if ($tarjetas === [])
            <x-filament::empty-state heading="Ninguna oportunidad cumple los filtros" icon="heroicon-o-funnel" />
        @else
            <div class="tq-op-grid">
                @foreach ($tarjetas as $t)
                    <a href="{{ $t['url'] }}" wire:navigate class="tq-op-card" wire:key="op-{{ $t['id'] }}" data-oportunidad="{{ $t['id'] }}">
                        <span class="tq-op-foto">
                            @if ($t['foto'] !== null)
                                <img src="{{ $t['foto'] }}" alt="Foto de {{ $t['nombre'] }}" loading="lazy">
                            @else
                                {!! $t['dibujo'] !!}
                            @endif
                            <span class="tq-op-linea">{{ $t['linea'] }}</span>
                            <span class="tq-op-etapa"><x-filament::badge :color="match ($t['codigo_estado']) { 'ganada' => 'success', 'perdida' => 'danger', default => 'morado' }">{{ $t['estado'] }}</x-filament::badge></span>
                        </span>
                        <span class="tq-op-tope">
                            {!! $anillo($t) !!}
                            <span style="min-width: 0">
                                <span class="tq-op-cod" style="display: block">{{ $t['numero'] }}</span>
                                <span class="tq-op-nom" style="display: block">{{ $t['nombre'] }}</span>
                                <span class="tq-op-ent" style="display: block">{{ $t['entidad'] }}</span>
                            </span>
                        </span>
                        <span class="tq-op-cuerpo">
                            <span class="tq-op-monto">{{ $t['monto'] }}@if ($t['ponderado'] !== null)<small>pond. {{ $t['ponderado'] }}</small>@endif</span>
                            <span class="tq-op-etl">
                                @if ($t['dias'] !== null)
                                    <span>En la etapa desde {{ $t['desde'] }}</span>
                                    <span @class(['tq-op-atascada' => $t['atascada']])>{{ $t['dias'] }}@if ($t['maximo'] > 0) de {{ $t['maximo'] }}@endif días{{ $t['atascada'] ? ' · atascada' : '' }}</span>
                                @else
                                    <span>{{ $t['codigo_estado'] === 'ganada' ? 'Ganada' : 'Perdida' }} el {{ $t['cierre'] }}</span>
                                @endif
                            </span>
                            <span class="tq-op-pie"><x-filament::icon icon="heroicon-o-user-circle" class="h-4 w-4" />{{ $t['ejecutivo'] }}<span style="margin-inline-start: auto; color: var(--gray-500)">Cierre {{ $t['cierre'] }}</span></span>
                        </span>
                    </a>
                @endforeach
            </div>
            <div style="margin-top: 1rem"><x-filament::pagination :paginator="$pagina" /></div>
        @endif
    </section>
</div>
