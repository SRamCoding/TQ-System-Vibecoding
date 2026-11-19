{{--
    La pestaña «Partidas» del presupuesto (plan 2.10, D4) con el APU de una partida en un panel que se desliza desde la
    derecha encima de la tabla, que queda atenuada detrás, con las piezas de Catálogos (`cajas-con-panel`). `?apu=`
    lo abre directo. Al cerrar, primero se desliza afuera; la X o Esc lo cierran (Esc no, si se está escribiendo o hay
    otra ventana encima).
--}}
<div class="tq-catalogos">
    @include('nucleo::filament.partials.cajas-con-panel')

    <div @class(['tq-catalogos-detras' => $this->apu !== null])>
        {{ $this->table }}
    </div>

    @if ($this->apu !== null)
        <section
            class="tq-catalogo-panel"
            wire:key="apu-{{ $this->apu }}"
            aria-labelledby="tq-apu-titulo"
            data-apu-abierto="{{ $this->apu }}"
            x-data="{
                saliendo: false,
                cerrar() {
                    if (this.saliendo) return
                    this.saliendo = true
                    const quieto = window.matchMedia('(prefers-reduced-motion: reduce)').matches || document.documentElement.classList.contains('tq-sin-animaciones')
                    setTimeout(() => $wire.cerrarApu(), quieto ? 0 : 190)
                },
            }"
            x-bind:class="{ 'tq-saliendo': saliendo }"
            x-init="$nextTick(() => $el.querySelector('#tq-apu-titulo')?.focus())"
            x-on:keydown.escape.window="if (! $event.defaultPrevented && ! $event.target.closest('input, textarea, select, [contenteditable]') && ! [...document.querySelectorAll('.fi-modal-open, [aria-modal=true], .fi-dropdown-panel')].some((capa) => capa.getClientRects().length)) cerrar()"
        >
            <header class="tq-catalogo-panel-encabezado">
                <h2 id="tq-apu-titulo" class="tq-catalogo-panel-titulo" tabindex="-1">
                    <x-filament::icon icon="heroicon-o-beaker" class="h-6 w-6" />
                    Análisis de precios unitarios
                </h2>
                <x-filament::icon-button icon="heroicon-o-x-mark" color="gray" label="Cerrar y volver a las partidas" x-on:click="cerrar()" />
            </header>
            @livewire('presupuestos.apu', ['partidaId' => $this->apu], key('apu-'.$this->apu))
        </section>
    @endif

    <x-filament-actions::modals />
</div>
