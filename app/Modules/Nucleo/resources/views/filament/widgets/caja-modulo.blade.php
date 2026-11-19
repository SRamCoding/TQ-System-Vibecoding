{{--
    Una caja del inicio: ícono arriba y nombre debajo, centrados. Toda caja se dibuja con esta vista: así todas miden lo
    mismo (docs/convenciones/interfaz.md). `fi-section` da el aspecto de tarjeta de Filament (fondo, borde, modo oscuro);
    el color del área lo hereda de su sección.
    Plan 1.4f, D10: en escritorio abre otra pestaña; en el celular, la misma (abrir módulos seguidos no deja pestañas
    sueltas). Se decide al tocarla, con el corte de BarraLateral; sin JavaScript, la misma pestaña. Con Ctrl, Cmd, Shift
    o el botón del medio, el navegador decide, como en cualquier enlace.
    Plan 1.4q: con preguntas, al dejar el mouse encima crece su celda (empuja a sus vecinas) y la caja las muestra una
    por una; al final, la animación de su ícono (`tqCajaConPreguntas`, en cajas-modulos). Sin preguntas, la
    descripción en el tooltip de Filament, como antes. En el celular, nada (D4).
--}}
@php /** @var \App\Modules\Nucleo\Navegacion\CajaModulo $caja */ @endphp
<div class="tq-celda-modulo">
    <a
        href="{{ $caja->url }}"
        rel="noopener"
        data-tq-pestana="nueva-en-escritorio"
        @if ($caja->preguntas !== [])
            x-data="tqCajaConPreguntas(@js($caja->preguntas))"
            x-on:mouseenter="empezar()"
            x-on:mouseleave="parar()"
            {{-- Con el teclado, sí; el foco que deja un clic, no (al volver de la pestaña nueva, la animación no arranca sola). --}}
            x-on:focus="$el.matches(':focus-visible') && empezar()"
            x-on:blur="parar()"
            x-bind:class="{ 'tq-activa': activa, 'tq-final': final }"
            data-tq-preguntas
            data-tq-animacion="{{ $caja->animacion->value }}"
        @else
            x-data
            @if ($caja->descripcion !== null)
                x-tooltip="{ content: @js($caja->descripcion), theme: $store.theme }"
            @endif
        @endif
        {{-- Plan 1.4q: el clic corta las preguntas; la caja vuelve a su tamaño mientras se abre el módulo. --}}
        x-on:click="$data.parar?.(); if (window.innerWidth >= @js(\App\Modules\Nucleo\Navegacion\BarraLateral::CORTE_ESCRITORIO) && $event.button === 0 && ! ($event.ctrlKey || $event.metaKey || $event.shiftKey || $event.altKey)) { $event.preventDefault(); window.open($el.href, '_blank', 'noopener') }"
        data-modulo="{{ $caja->clave }}"
        data-area="{{ $caja->area->value }}"
        class="fi-section tq-caja-modulo"
        style="--tq-trazo: url(#tq-trazo-{{ $caja->clave }})"
    >
        {{-- Plan 1.4r: el degradado del trazo del ícono (otoño, invierno), con un id por caja: hay varias en la página. Su
             sistema de coordenadas es el del ícono (24 × 24), así un trazo recto también lo toma. --}}
        <svg class="tq-caja-degradados" aria-hidden="true" focusable="false"><defs><linearGradient id="tq-trazo-{{ $caja->clave }}" gradientUnits="userSpaceOnUse" x1="2" y1="2" x2="22" y2="22"><stop offset="0" style="stop-color: var(--tq-e-icono1, currentColor)" /><stop offset="1" style="stop-color: var(--tq-e-icono2, currentColor)" /></linearGradient></defs></svg>
        <span class="tq-caja-modulo-marco"><x-filament::icon :icon="$caja->icono" class="tq-caja-modulo-icono" /></span>
        <x-filament::icon :icon="$caja->icono" class="tq-caja-marca-agua" aria-hidden="true" />
        {{-- Plan 1.4t: las hojas de otoño y la escarcha de invierno, distintas en cada caja; el CSS las muestra solo en su estación. --}}
        <span class="tq-caja-adornos" aria-hidden="true">{!! \App\Modules\Nucleo\Navegacion\AdornosDeEstacion::hojas($caja->clave) !!}{!! \App\Modules\Nucleo\Navegacion\AdornosDeEstacion::hielo($caja->clave) !!}</span>
        <span class="tq-caja-modulo-nombre">{{ $caja->nombre }}</span>
        @if ($caja->preguntas !== [])
            <span class="tq-caja-preguntas" x-show="activa && ! final" x-cloak>
                <span class="tq-caja-pregunta" x-bind:class="{ 'tq-visible': visible }" x-text="preguntas[indice] ?? ''"></span>
            </span>
            <span class="tq-caja-contador" x-show="activa && ! final" x-cloak aria-hidden="true">
                <template x-for="(pregunta, n) in preguntas" x-bind:key="n"><i x-bind:class="{ 'tq-hecho': n <= indice }"></i></template>
            </span>
        @endif
    </a>
</div>
