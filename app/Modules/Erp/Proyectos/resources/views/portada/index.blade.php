{{--
    La portada de Proyectos con tarjetas (plan 2.61, D4), como el prototipo aprobado: filtros a la izquierda con la
    cantidad de cada opción, buscador y orden arriba, tarjetas con foto. Colores del sistema (D7): el estado con su
    color de Filament, el semáforo con success, warning y danger; solo el dibujo sin foto trae los suyos (D12).
--}}
@php
    $tono = fn (?string $t): string => match ($t) { 'danger' => 'var(--danger-600)', 'warning' => 'var(--warning-500)', 'success' => 'var(--success-600)', default => 'var(--gray-400)' };
@endphp
<x-filament-panels::page>
    <style>
        .tq-pp { display: grid; grid-template-columns: 15rem minmax(0, 1fr); gap: 1.5rem; align-items: start; }
        @media (max-width: 1023px) { .tq-pp { grid-template-columns: minmax(0, 1fr); } }
        .tq-pp-filtros { position: sticky; top: 5rem; display: flex; flex-direction: column; gap: 1.25rem; }
        .tq-pp-grupo h3 { margin-bottom: 0.4rem; font-size: 0.6875rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; color: var(--gray-500); }
        .tq-pp-op { display: flex; align-items: center; gap: 0.5rem; padding: 0.2rem 0; font-size: 0.875rem; cursor: pointer; }
        .tq-pp-op.tq-cero { color: var(--gray-400); }
        .tq-pp-op .tq-n { margin-inline-start: auto; font-size: 0.75rem; font-variant-numeric: tabular-nums; color: var(--gray-500); }
        .tq-pp-punto { width: 0.5rem; height: 0.5rem; flex: none; border-radius: 9999px; }
        .tq-pp-barra { display: flex; flex-wrap: wrap; gap: 0.75rem; align-items: center; margin-bottom: 1rem; }
        .tq-pp-barra .tq-pp-buscar { flex: 1 1 16rem; }
        .tq-pp-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(17rem, 1fr)); gap: 1rem; }
        .tq-pp-card { display: flex; flex-direction: column; overflow: hidden; border-radius: 0.75rem; background: white; box-shadow: 0 1px 2px rgb(0 0 0 / 0.06); outline: 1px solid rgb(3 7 18 / 0.06); transition: box-shadow 160ms ease, transform 160ms ease; }
        .dark .tq-pp-card { background: var(--gray-900); outline-color: rgb(255 255 255 / 0.08); }
        .tq-pp-card:hover, .tq-pp-card:focus-visible { box-shadow: 0 8px 20px -6px color-mix(in oklab, var(--primary-600) 35%, transparent); transform: translateY(-2px); }
        @media (prefers-reduced-motion: reduce) { .tq-pp-card { transition: none; transform: none !important; } }
        .tq-pp-foto { position: relative; aspect-ratio: 16 / 9; overflow: hidden; background: var(--gray-100); }
        .tq-pp-foto > img, .tq-pp-foto > svg { width: 100%; height: 100%; object-fit: cover; display: block; }
        .tq-pp-foto .tq-pp-estado { position: absolute; left: 0.6rem; top: 0.6rem; }
        .tq-pp-foto .tq-pp-sem { position: absolute; right: 0.6rem; top: 0.6rem; display: inline-flex; align-items: center; gap: 0.35rem; padding: 0.15rem 0.5rem; border-radius: 9999px; background: rgb(255 255 255 / 0.92); font-size: 0.75rem; font-weight: 600; color: var(--gray-700); }
        .tq-pp-cuerpo { display: flex; flex-direction: column; gap: 0.25rem; padding: 0.85rem 1rem 1rem; }
        .tq-pp-cod { font-family: var(--mono-font-family, ui-monospace, monospace); font-size: 0.75rem; color: var(--gray-500); }
        .tq-pp-nom { font-weight: 600; line-height: 1.3; }
        .tq-pp-cli { font-size: 0.8125rem; color: var(--gray-500); }
        .tq-pp-plazo { display: grid; grid-template-columns: auto 1fr auto; gap: 0.5rem; align-items: center; margin-top: 0.4rem; font-size: 0.75rem; color: var(--gray-500); font-variant-numeric: tabular-nums; }
        .tq-pp-track { height: 0.375rem; border-radius: 9999px; background: var(--gray-200); overflow: hidden; }
        .dark .tq-pp-track { background: var(--gray-700); }
        .tq-pp-track > i { display: block; height: 100%; background: var(--gray-400); }
        .tq-pp-pie { display: flex; align-items: center; gap: 0.5rem; margin-top: 0.6rem; padding-top: 0.6rem; border-top: 1px solid var(--gray-100); font-size: 0.8125rem; }
        .dark .tq-pp-pie { border-top-color: var(--gray-800); }
        .tq-pp-pie .tq-pp-dias { margin-inline-start: auto; color: var(--gray-500); }
        .tq-pp-chips { display: flex; flex-wrap: wrap; gap: 0.5rem; margin-bottom: 1rem; }
    </style>

    <div class="tq-pp">
        <aside class="tq-pp-filtros" aria-label="Filtros">
            @if ($opciones['salud'] !== [])
                <div class="tq-pp-grupo">
                    <h3>Salud</h3>
                    @foreach ($opciones['salud'] as $clave => $op)
                        <label @class(['tq-pp-op', 'tq-cero' => $op['cantidad'] === 0])>
                            <x-filament::input.checkbox wire:model.live="salud" value="{{ $clave }}" />
                            <span class="tq-pp-punto" style="background: {{ $tono($op['tono']) }}"></span>{{ $op['etiqueta'] }}<span class="tq-n">{{ $op['cantidad'] }}</span>
                        </label>
                    @endforeach
                </div>
            @endif
            <div class="tq-pp-grupo">
                <h3>Estado</h3>
                @foreach ($opciones['estados'] as $codigo => $op)
                    <label @class(['tq-pp-op', 'tq-cero' => $op['cantidad'] === 0])>
                        <x-filament::input.checkbox wire:model.live="estados" value="{{ $codigo }}" />{{ $op['etiqueta'] }}<span class="tq-n">{{ $op['cantidad'] }}</span>
                    </label>
                @endforeach
            </div>
            <div class="tq-pp-grupo">
                <h3>Jefe de proyecto</h3>
                <label class="tq-pp-op"><x-filament::input.checkbox wire:model.live="mios" />Solo los míos</label>
                @foreach ($opciones['jefes'] as $id => $op)
                    <label @class(['tq-pp-op', 'tq-cero' => $op['cantidad'] === 0])>
                        <x-filament::input.checkbox wire:model.live="jefes" value="{{ $id }}" />{{ $op['etiqueta'] }}<span class="tq-n">{{ $op['cantidad'] }}</span>
                    </label>
                @endforeach
            </div>
            @if ($conFiltros)
                <x-filament::link tag="button" wire:click="quitarFiltros" icon="heroicon-o-x-mark">Quitar todos los filtros</x-filament::link>
            @endif
        </aside>

        <section>
            <div class="tq-pp-barra">
                <div class="tq-pp-buscar">
                    <x-filament::input.wrapper prefix-icon="heroicon-o-magnifying-glass">
                        <x-filament::input type="search" wire:model.live.debounce.400ms="buscar" placeholder="Buscar por nombre, código o cliente…" aria-label="Buscar proyectos" />
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

            <p class="tq-pp-cli" style="margin-bottom: 0.75rem">{{ $pagina->total() }} de {{ $total }} proyectos</p>

            @if ($tarjetas === [])
                <x-filament::empty-state heading="Ningún proyecto cumple los filtros" icon="tq-proyecto-cohete" />
            @else
                <div class="tq-pp-grid">
                    @foreach ($tarjetas as $t)
                        <a href="{{ $t['url'] }}" wire:navigate class="tq-pp-card" wire:key="proyecto-{{ $t['id'] }}" data-proyecto="{{ $t['id'] }}">
                            <div class="tq-pp-foto">
                                @if ($t['foto'] !== null)
                                    <img src="{{ $t['foto'] }}" alt="Foto de {{ $t['nombre'] }}" loading="lazy">
                                @else
                                    {!! $t['dibujo'] !!}
                                @endif
                                <span class="tq-pp-estado"><x-filament::badge :color="$t['color_estado']">{{ $t['estado'] }}</x-filament::badge></span>
                                @if ($t['semaforo'] !== null)
                                    <span class="tq-pp-sem" data-semaforo="{{ $t['semaforo'] }}"><span class="tq-pp-punto" style="background: {{ $tono($t['semaforo']) }}"></span>Costo</span>
                                @endif
                            </div>
                            <div class="tq-pp-cuerpo">
                                <span class="tq-pp-cod">{{ $t['codigo'] }}</span>
                                <span class="tq-pp-nom">{{ $t['nombre'] }}</span>
                                <span class="tq-pp-cli">{{ $t['cliente'] }}</span>
                                @if ($t['plazo'] !== null)
                                    <span class="tq-pp-plazo">Plazo<span class="tq-pp-track"><i style="width: {{ $t['plazo'] }}%"></i></span>{{ $t['plazo'] }} %</span>
                                @endif
                                <span class="tq-pp-pie">
                                    <x-filament::icon icon="heroicon-o-user-circle" class="h-4 w-4" />{{ $t['jefe'] }}
                                    <span class="tq-pp-dias" @if ($t['tono_dias'] !== null) style="color: {{ $tono($t['tono_dias']) }}; font-weight: 600" @endif>{{ $t['dias'] }}</span>
                                </span>
                            </div>
                        </a>
                    @endforeach
                </div>
                <div style="margin-top: 1rem">
                    <x-filament::pagination :paginator="$pagina" />
                </div>
            @endif
        </section>
    </div>

    @include('nucleo::filament.parties.ultimos-cambios', ['cambios' => $cambios])
</x-filament-panels::page>
