{{--
    El panel deslizante de plantillas (ADR 0022; plan 2.11, paso 5), igual al de Catálogos o al del APU: se pone dentro
    del `.tq-catalogos` de la pantalla, que atenúa lo de detrás. Recibe:
      $tipo          la clave del tipo de plantilla
      $documentoId   el documento abierto (o null), del que se guarda o actualiza
      $ofrecerUsar   si se ofrece «Usar» (la pantalla escucha `plantilla-elegida`)
      $alCerrar      el método de la pantalla que lo cierra
    La X o Esc lo cierran (Esc no, si se está escribiendo o hay otra ventana encima).
--}}
<section
    class="tq-catalogo-panel"
    wire:key="plantillas-{{ $tipo }}"
    aria-labelledby="tq-plantillas-titulo"
    data-plantillas-abierto="{{ $tipo }}"
    x-data="{
        saliendo: false,
        cerrar() {
            if (this.saliendo) return
            this.saliendo = true
            const quieto = window.matchMedia('(prefers-reduced-motion: reduce)').matches || document.documentElement.classList.contains('tq-sin-animaciones')
            setTimeout(() => $wire.{{ $alCerrar }}(), quieto ? 0 : 190)
        },
    }"
    x-bind:class="{ 'tq-saliendo': saliendo }"
    x-init="$nextTick(() => $el.querySelector('#tq-plantillas-titulo')?.focus())"
    x-on:keydown.escape.window="if (! $event.defaultPrevented && ! $event.target.closest('input, textarea, select, [contenteditable]') && ! [...document.querySelectorAll('.fi-modal-open, [aria-modal=true], .fi-dropdown-panel')].some((capa) => capa.getClientRects().length)) cerrar()"
>
    <header class="tq-catalogo-panel-encabezado">
        <h2 id="tq-plantillas-titulo" class="tq-catalogo-panel-titulo" tabindex="-1">
            <x-filament::icon icon="heroicon-o-square-2-stack" class="h-6 w-6" />
            Plantillas
        </h2>
        <x-filament::icon-button icon="heroicon-o-x-mark" color="gray" label="Cerrar las plantillas" x-on:click="cerrar()" />
    </header>
    @livewire('nucleo.plantillas', ['tipo' => $tipo, 'documentoId' => $documentoId ?? null, 'ofrecerUsar' => $ofrecerUsar ?? true], key('plantillas-'.$tipo.'-'.($documentoId ?? 'nuevo')))
</section>
