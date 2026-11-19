{{--
    Un dibujo de ambientación. `$animado`: corre al aparecer (también al refrescar la página) y otra vez en cada cambio
    entre modo claro y oscuro (pedido del desarrollador, 2026-10-26); si no, queda quieto. Siempre vuelve a correr al
    pasar el mouse (o al tocarlo en el celular): quitar y poner `.tq-corre` reinicia las animaciones de CSS.
    Recibe `$variante` (VarianteAmbientacion), `$animado` (bool) y `$tamano` (px).
--}}
<span
    class="tq-amb @if ($animado) tq-corre @endif"
    style="width: {{ (int) $tamano }}px; height: {{ (int) $tamano }}px"
    role="img"
    aria-label="{{ $variante->nombre }}"
    data-ambientacion="{{ $variante->tema }}-{{ $variante->clave }}"
    x-data="{ correr() { $el.classList.remove('tq-corre'); void $el.offsetWidth; $el.classList.add('tq-corre') } }"
    @if ($animado)
        x-on:theme-changed.window="correr()"
    @endif
    x-on:mouseenter="correr()"
    x-on:click="correr()"
>
    @include($variante->vista)
</span>
