{{--
    Configuración → Catálogos (plan 1.4j). Las cajas, agrupadas por tema, con un buscador que las filtra sin acentos ni
    mayúsculas (esconde los grupos que quedan vacíos). Con un catálogo abierto, su tabla en un panel que se desliza
    desde la derecha **encima** de las cajas, que siguen detrás, atenuadas (D1; una que asome se puede presionar para
    cambiar de catálogo); la lista de Configuración sigue a la vista. **Sin recargar la página**: abrir y cerrar pasan
    por Livewire, y al cerrar el panel primero se desliza afuera. La X o Esc lo cierran (Esc no, si se está escribiendo
    o hay otra cosa abierta encima). Cada caja sigue siendo un enlace (`?catalogo=…`): con Ctrl o el botón del medio,
    otra pestaña. Las cajas miden lo mismo que las del inicio.
--}}
@php
    /** @var list<\App\Modules\Nucleo\Enums\GrupoCatalogo> $grupos */
    /** @var \App\Modules\Nucleo\Enums\Catalogo|null $abierto */
    $normalizar = fn (string $texto): string => \Illuminate\Support\Str::lower(\Illuminate\Support\Str::ascii($texto));
@endphp
<div class="tq-catalogos">
    @include('nucleo::filament.partials.cajas-con-panel')

    <div @class(['tq-catalogos-detras' => $abierto !== null]) x-data="{ q: '', normalizar: (t) => t.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().trim() }">
        <x-filament::input.wrapper class="tq-catalogos-buscar" prefix-icon="heroicon-o-magnifying-glass">
            <x-filament::input type="search" x-model="q" placeholder="Buscar catálogo…" aria-label="Buscar catálogo" />
        </x-filament::input.wrapper>

        @foreach ($grupos as $grupo)
            <section class="tq-catalogos-grupo" data-tq-grupo="{{ $grupo->value }}" x-show="[...$el.querySelectorAll('[data-buscar]')].some((c) => c.dataset.buscar.includes(normalizar(q)))">
                <h2 class="tq-catalogos-grupo-titulo">{{ $grupo->etiqueta() }}</h2>
                <div class="tq-catalogos-cajas">
                    @foreach ($grupo->catalogos() as $catalogo)
                        <a
                            href="{{ \App\Modules\Nucleo\Filament\Pages\Catalogos::getUrl(['catalogo' => $catalogo->value]) }}"
                            class="fi-section tq-caja-catalogo tq-caja-sub"
                            data-catalogo="{{ $catalogo->value }}"
                            data-tq-abre-aqui
                            data-buscar="{{ $normalizar($catalogo->titulo().' '.$catalogo->descripcion()) }}"
                            x-show="$el.dataset.buscar.includes(normalizar(q))"
                            {{-- Un clic normal abre el panel sin recargar; con Ctrl, Shift o el botón del medio, el enlace. --}}
                            x-on:click="if ($event.button === 0 && ! ($event.ctrlKey || $event.metaKey || $event.shiftKey || $event.altKey)) { $event.preventDefault(); $wire.abrir(@js($catalogo->value)) }"
                            x-tooltip="{ content: @js($catalogo->descripcion()), theme: $store.theme }"
                            aria-description="{{ $catalogo->descripcion() }}"
                        >
                            <x-filament::icon :icon="$catalogo->icono()" class="tq-caja-catalogo-icono tq-caja-sub-icono" />
                            <span class="tq-caja-catalogo-nombre">{{ $catalogo->titulo() }}</span>
                        </a>
                    @endforeach
                </div>
            </section>
        @endforeach

        <p class="tq-catalogos-nada" role="status" x-cloak x-show="! [...$root.querySelectorAll('[data-buscar]')].some((c) => c.dataset.buscar.includes(normalizar(q)))">
            Ningún catálogo coincide.
        </p>
    </div>

    @if ($abierto !== null)
        <section
            class="tq-catalogo-panel"
            wire:key="panel-{{ $abierto->value }}"
            aria-labelledby="tq-catalogo-panel-titulo"
            data-catalogo-abierto="{{ $abierto->value }}"
            x-data="{
                saliendo: false,
                {{-- Primero se desliza afuera; recién entonces Livewire lo quita. Sin animaciones, de inmediato. --}}
                cerrar() {
                    if (this.saliendo) return
                    this.saliendo = true
                    const quieto = window.matchMedia('(prefers-reduced-motion: reduce)').matches || document.documentElement.classList.contains('tq-sin-animaciones')
                    setTimeout(() => $wire.cerrar(), quieto ? 0 : 190)
                },
            }"
            x-bind:class="{ 'tq-saliendo': saliendo }"
            x-init="$nextTick(() => $el.querySelector('#tq-catalogo-panel-titulo')?.focus())"
            {{-- Esc cierra el panel solo si no se está escribiendo ni hay otra cosa abierta encima (una ventana, un menú,
                 el modo desarrollo, el gran momento): esa se cierra primero. --}}
            x-on:keydown.escape.window="if (! $event.defaultPrevented && ! $event.target.closest('input, textarea, select, [contenteditable]') && ! [...document.querySelectorAll('.fi-modal-open, [aria-modal=true], .fi-dropdown-panel')].some((capa) => capa.getClientRects().length)) cerrar()"
        >
            <header class="tq-catalogo-panel-encabezado">
                <h2 id="tq-catalogo-panel-titulo" class="tq-catalogo-panel-titulo" tabindex="-1">
                    <x-filament::icon :icon="$abierto->icono()" class="h-6 w-6" />
                    {{ $abierto->titulo() }}
                </h2>
                <x-filament::icon-button icon="heroicon-o-x-mark" color="gray" label="Cerrar y volver a los catálogos" x-on:click="cerrar()" />
            </header>
            {{ $this->table }}
        </section>
    @endif
</div>
