{{--
    La vista «Tarjetas» de Oportunidades (plan 2.21d, D1 y D11), como el prototipo aprobado y la portada de Proyectos
    (2.61): filtros con su cantidad a la izquierda; buscador y orden; tarjetas con la foto o el dibujo de la línea de
    solución, la línea y la etapa encima, el anillo con la probabilidad, monto y ponderado, días en la etapa contra su
    plazo y el ejecutivo. Sin barra de etapas (D1). Tocar una tarjeta lleva a la ficha completa (pedido del
    desarrollador, 2026-11-16: sin el panel lateral).
    Colores del sistema por nombre: el anillo en el morado del CRM, verde ganada, rojo perdida.
--}}
@php
    $grupos = ['atencion' => 'Atención', 'tipos' => 'Tipo', 'etapas' => 'Etapa', 'origenes' => 'Origen', 'ejecutivos' => 'Ejecutivo', 'lineas' => 'Línea de solución', 'clientes' => 'Cliente'];
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
        .tq-op-filtros { position: sticky; top: 5rem; display: flex; flex-direction: column; gap: 0.9rem; }
        /* Observación 8.3: el panel baja por su cuenta; la barra, invisible hasta que se usa. */
        .tq-op-filtros { max-height: calc(100vh - 6rem); overflow-y: auto; overscroll-behavior: contain; padding-inline-end: 0.35rem; scrollbar-width: thin; scrollbar-color: transparent transparent; }
        .tq-op-filtros:hover, .tq-op-filtros:focus-within { scrollbar-color: color-mix(in oklab, var(--tq-texto, #000) 25%, transparent) transparent; }
        @media (max-width: 1023px) { .tq-op-filtros { position: static; max-height: none; } }
        .tq-op-grupo-tit { display: flex; align-items: center; gap: 0.35rem; width: 100%; font-size: 0.6875rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; color: var(--gray-500); padding: 0.15rem 0; }
        .tq-op-grupo-tit .tq-activos { text-transform: none; letter-spacing: 0; color: var(--primary-600); }
        .tq-op-grupo-tit svg { margin-inline-start: auto; transition: transform 150ms ease; }
        .tq-op-grupo-tit[aria-expanded="false"] svg { transform: rotate(-90deg); }
        .tq-op-mini-buscar { width: 100%; margin: 0.25rem 0 0.35rem; padding: 0.25rem 0.5rem; font-size: 0.8125rem; border: 1px solid var(--tq-borde, var(--gray-200)); border-radius: 0.4rem; background: transparent; }
        .tq-op-filtros h3 { margin-bottom: 0.4rem; font-size: 0.6875rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; color: var(--gray-500); }
        .tq-op-opcion { display: flex; align-items: center; gap: 0.5rem; padding: 0.2rem 0; font-size: 0.875rem; cursor: pointer; }
        .tq-op-opcion.tq-cero { color: var(--gray-400); }
        .tq-op-opcion .tq-n { margin-inline-start: auto; font-size: 0.75rem; font-variant-numeric: tabular-nums; color: var(--gray-500); }
        .tq-op-barra { display: flex; flex-wrap: wrap; gap: 0.75rem; align-items: center; margin-bottom: 1rem; }
        .tq-op-barra .tq-op-buscar { flex: 1 1 16rem; }
        /* Observación 8.1: 3 columnas como máximo (3 en anchas, 2 en medianas, 1 en angostas); las tarjetas crecen. */
        .tq-canal { display: inline-flex; align-items: center; gap: 0.3rem; padding: 0.05rem 0.5rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; line-height: 1.4; background: var(--tq-superficie, white); border: 1px solid var(--tq-borde, var(--gray-200)); color: var(--gray-700); white-space: nowrap; }
        .dark .tq-canal { color: var(--gray-200); }
        .tq-op-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(17rem, 1fr)); gap: 1rem; }
        .tq-op-card { display: flex; flex-direction: column; overflow: hidden; border-radius: 0.75rem; background: var(--tq-superficie, white); box-shadow: 0 1px 2px rgb(0 0 0 / 0.06); outline: 1px solid rgb(3 7 18 / 0.06); text-align: start; cursor: pointer; transition: box-shadow 160ms ease, transform 160ms ease; }
        .dark .tq-op-card { background: var(--tq-superficie, var(--gray-900)); outline-color: rgb(255 255 255 / 0.08); }
        .tq-op-card:hover, .tq-op-card:focus-visible { box-shadow: 0 8px 20px -6px color-mix(in oklab, var(--primary-600) 35%, transparent); transform: translateY(-2px); }
        @media (prefers-reduced-motion: reduce) { .tq-op-card { transition: none; transform: none !important; } }
        html.tq-sin-animaciones .tq-op-card { transition: none; transform: none !important; }
        .tq-op-foto { position: relative; aspect-ratio: 16 / 6; overflow: hidden; background: var(--gray-100); }
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
        /* Observación 7.1: el plazo de la licitación (coral a menos de 5 días) y las tarjetas por calificar. */
        .tq-op-plazo { display: inline-flex; align-items: center; gap: 0.3rem; font-size: 0.75rem; font-weight: 600; color: var(--gray-600); }
        .tq-op-plazo.tq-urgente { color: color-mix(in oklch, #E8604C calc(100% - var(--tq-tinte-leve, 0%)), var(--tq-tinte, #E8604C)); }
        .dark .tq-op-plazo { color: var(--gray-300); }
        .dark .tq-op-plazo.tq-urgente { color: color-mix(in oklch, #FF8A75 calc(100% - var(--tq-tinte-leve, 0%)), var(--tq-tinte, #FF8A75)); }
        .tq-op-card.tq-pc { cursor: default; background: color-mix(in oklab, var(--tq-superficie, white) 92%, #8C857F); }
        .dark .tq-op-card.tq-pc { background: color-mix(in oklab, var(--gray-900) 90%, #8C857F); }
        .tq-pc-tope { display: flex; gap: 0.75rem; align-items: flex-start; padding: 0.9rem 1rem 0.35rem; }
        .tq-pc-clase { display: inline-flex; align-items: center; gap: 0.3rem; font-size: 0.6875rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: var(--gray-500); }
        .tq-pc-detalle { font-size: 0.8125rem; color: var(--gray-600); display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
        .dark .tq-pc-detalle { color: var(--gray-400); }
        .tq-pc-botones { display: flex; gap: 0.5rem; margin-top: 0.4rem; padding-top: 0.6rem; border-top: 1px solid var(--gray-200); }
        .dark .tq-pc-botones { border-top-color: var(--gray-800); }
    </style>

    <aside class="tq-op-filtros" aria-label="Filtros" x-data x-on:tq-remoto-filtros-abiertos.window="$wire.set('abiertos', $event.detail.abiertos ?? [])">
        {{-- Observación 8.3: «Quitar filtros» arriba; grupos plegables (recordados por persona); buscador si hay más de 6. --}}
        @if ($conFiltros)
            <x-filament::link tag="button" wire:click="quitarFiltros" icon="heroicon-o-x-mark">Quitar filtros</x-filament::link>
        @endif
        @foreach ($grupos as $grupo => $titulo)
            @if ($opciones[$grupo] !== [])
                @php
                    $abierto = in_array($grupo, $this->abiertos, true);
                    $activos = count((array) $this->{$grupo});
                    $muchas = in_array($grupo, ['ejecutivos', 'clientes'], true) && count($opciones[$grupo]) > 6;
                    $orden = $opciones[$grupo];
                    if ($muchas) { uasort($orden, fn ($a, $b) => $b['cantidad'] <=> $a['cantidad']); }
                @endphp
                <div wire:key="grupo-{{ $grupo }}" x-data="{ q: '', todos: false }">
                    <button type="button" class="tq-op-grupo-tit" aria-expanded="{{ $abierto ? 'true' : 'false' }}" wire:click="plegar('{{ $grupo }}')">
                        {{ $titulo }}@if ($activos > 0)<span class="tq-activos">· {{ $activos }}</span>@endif
                        <x-filament::icon icon="heroicon-m-chevron-down" class="h-4 w-4" />
                    </button>
                    @if ($abierto)
                        @if ($muchas)
                            <input type="search" class="tq-op-mini-buscar" x-model="q" placeholder="Buscar {{ mb_strtolower($titulo) }}…" aria-label="Buscar en {{ $titulo }}">
                        @endif
                        @foreach ($orden as $valor => $op)
                            <label @class(['tq-op-opcion', 'tq-cero' => $op['cantidad'] === 0])
                                @if ($muchas) x-show="q !== '' ? {{ \Illuminate\Support\Js::from(mb_strtolower($op['etiqueta'])) }}.includes(q.toLowerCase()) : (todos || {{ $loop->index < 6 || in_array((string) $valor, array_map('strval', (array) $this->{$grupo}), true) ? 'true' : 'false' }})" @endif>
                                <x-filament::input.checkbox wire:model.live="{{ $grupo }}" value="{{ $valor }}" />@if (isset($op['icono']))<x-filament::icon :icon="$op['icono']" class="h-4 w-4" style="color: var(--gray-500); flex: none" />@endif{{ $op['etiqueta'] }}<span class="tq-n">{{ $op['cantidad'] }}</span>
                            </label>
                        @endforeach
                        @if ($muchas)
                            <x-filament::link tag="button" size="sm" x-show="q === ''" x-on:click="todos = ! todos"><span x-text="todos ? 'Ver menos' : 'Ver todos ({{ count($orden) }})'"></span></x-filament::link>
                        @endif
                    @endif
                </div>
            @endif
        @endforeach
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

        @if ($tarjetas === [] && $porCalificar === [])
            <x-filament::empty-state heading="Ninguna oportunidad cumple los filtros" icon="heroicon-o-funnel" />
        @else
            <div class="tq-op-grid">
                {{-- Observación 7.1: «Por calificar», la primera etapa: leads y licitaciones por decidir, con sus dos botones. --}}
                @foreach ($porCalificar as $p)
                    <article class="tq-op-card tq-pc" wire:key="pc-{{ $p['clave'] }}" data-por-calificar="{{ $p['clave'] }}">
                        <span class="tq-pc-tope">
                            <x-oportunidades::gema talla="en_roca" :tamano="44" decorativa />
                            <span style="min-width: 0">
                                <span class="tq-pc-clase">Por calificar · {{ $p['clase'] === 'lead' ? 'Lead' : 'Licitación' }}</span>
                                <span class="tq-op-nom" style="display: block">{{ \Illuminate\Support\Str::limit($p['nombre'], 90) }}</span>
                                <span class="tq-op-ent" style="display: block">{{ $p['entidad'] }}</span>
                            </span>
                        </span>
                        <span class="tq-op-cuerpo">
                            @if ($p['montoTexto'] !== null)
                                <span class="tq-op-monto">{{ $p['montoTexto'] }}<small>{{ $p['montoEtiqueta'] }}</small></span>
                            @endif
                            @if ($p['detalle'] !== null)
                                <span class="tq-pc-detalle">«{{ $p['detalle'] }}»</span>
                            @endif
                            @if (($p['canal'] ?? null) !== null)
                                {{-- Observación 9.3: el canal del lead, con su ícono. --}}
                                <span><span class="tq-canal" title="Canal por el que llegó"><x-filament::icon :icon="$p['canal']['icono']" class="h-3.5 w-3.5" />{{ $p['canal']['nombre'] }}</span></span>
                            @endif
                            <span class="tq-op-etl">
                                <span>{{ $p['origen'] }}</span>
                                @if ($p['plazo'] !== null)
                                    <span @class(['tq-op-plazo', 'tq-urgente' => $p['plazo']['urgente']]) title="{{ $p['plazo']['titulo'] }}">{{ $p['plazo']['texto'] }}</span>
                                @elseif ($p['clase'] === 'lead')
                                    <span @class(['tq-op-atascada' => $p['sinAtender']])>hace {{ $p['dias'] }} {{ $p['dias'] === 1 ? 'día' : 'días' }}{{ $p['sinAtender'] ? ' · sin atender' : '' }}</span>
                                @endif
                            </span>
                            <span class="tq-pc-botones">
                                @if ($p['clase'] === 'lead' && $puedeLeads)
                                    <x-filament::button size="sm" color="success" icon="heroicon-o-arrow-right-circle" wire:click="decidir('calificarLead', {{ $p['id'] }})">Calificar</x-filament::button>
                                    <x-filament::button size="sm" color="gray" icon="heroicon-o-x-mark" wire:click="decidir('descartarLead', {{ $p['id'] }})">Descartar</x-filament::button>
                                @elseif ($p['clase'] === 'licitacion' && $puedeLicitaciones)
                                    <x-filament::button size="sm" color="success" icon="heroicon-o-check-circle" wire:click="decidir('participar', {{ $p['id'] }})">Participar</x-filament::button>
                                    <x-filament::button size="sm" color="gray" icon="heroicon-o-x-circle" wire:click="decidir('noParticipar', {{ $p['id'] }})">No participar</x-filament::button>
                                    <x-filament::link :href="'/licitaciones/'.$p['id']" size="sm" style="margin-inline-start: auto">Ver</x-filament::link>
                                @endif
                            </span>
                        </span>
                    </article>
                @endforeach
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
                                    @if ($t['plazo'] !== null)
                                        <span @class(['tq-op-plazo', 'tq-urgente' => $t['plazo']['urgente']]) title="{{ $t['plazo']['titulo'] }}"><x-filament::icon icon="heroicon-o-building-library" class="h-3.5 w-3.5" />{{ $t['plazo']['texto'] }}</span>
                                    @else
                                        <span>En la etapa desde {{ $t['desde'] }}</span>
                                    @endif
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
            @if ($tarjetas !== [])
                <div style="margin-top: 1rem"><x-filament::pagination :paginator="$pagina" /></div>
            @endif
        @endif
    </section>
</div>
