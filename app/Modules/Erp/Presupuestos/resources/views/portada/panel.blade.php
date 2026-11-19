{{--
    El panel de la lista de Presupuestos (plan 2.10, D2): el catálogo de insumos, deslizándose desde la derecha encima de
    la lista, que queda atenuada detrás, con las mismas piezas que Catálogos (`cajas-con-panel`). Al cerrar, primero se
    desliza afuera y recién entonces Livewire lo quita. La X o Esc lo cierran (Esc no, si se está escribiendo o hay
    otra ventana encima). Recibe `$panel` (hoy, «insumos» o nulo).
--}}
<div>
    @include('nucleo::filament.partials.cajas-con-panel')

    @if ($panel === 'insumos')
        <section
            class="tq-catalogo-panel"
            wire:key="panel-insumos"
            aria-labelledby="tq-presupuestos-panel-titulo"
            data-panel-abierto="insumos"
            x-data="{
                saliendo: false,
                cerrar() {
                    if (this.saliendo) return
                    this.saliendo = true
                    const quieto = window.matchMedia('(prefers-reduced-motion: reduce)').matches || document.documentElement.classList.contains('tq-sin-animaciones')
                    setTimeout(() => $wire.cerrarPanel(), quieto ? 0 : 190)
                },
            }"
            x-bind:class="{ 'tq-saliendo': saliendo }"
            x-init="$nextTick(() => $el.querySelector('#tq-presupuestos-panel-titulo')?.focus())"
            x-on:keydown.escape.window="if (! $event.defaultPrevented && ! $event.target.closest('input, textarea, select, [contenteditable]') && ! [...document.querySelectorAll('.fi-modal-open, [aria-modal=true], .fi-dropdown-panel')].some((capa) => capa.getClientRects().length)) cerrar()"
        >
            <header class="tq-catalogo-panel-encabezado">
                <h2 id="tq-presupuestos-panel-titulo" class="tq-catalogo-panel-titulo" tabindex="-1">
                    <x-filament::icon icon="heroicon-o-cube" class="h-6 w-6" />
                    Insumos
                </h2>
                <x-filament::icon-button icon="heroicon-o-x-mark" color="gray" label="Cerrar y volver a los presupuestos" x-on:click="cerrar()" />
            </header>
            @livewire('presupuestos.insumos', key('presupuestos-insumos'))
        </section>
    @endif
</div>
