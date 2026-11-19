{{-- La pila «+N más» de un lugar (D8): abre la tabla de ese lugar (`tq-faja-abrir-lugar`). Recibe `$pila`, `$lugar`, `$nombre` y `$z`. --}}
@php($z ??= 0)
<div class="tq-faja-abs" style="left: {{ $pila['x'] }}px; top: {{ $pila['y'] }}px" wire:key="faja-pila-{{ $lugar }}">
    <div class="tq-faja-cubo tq-faja-pila" style="left: {{ -$pila['ancho'] / 2 }}px; top: {{ -$pila['fondo'] / 2 }}px; width: {{ $pila['ancho'] }}px; height: {{ $pila['fondo'] }}px; --tq-z: {{ $z }}px"
        data-pila="{{ $lugar }}" tabindex="0" role="button"
        aria-label="+{{ $pila['cantidad'] }} más en {{ $nombre }}: ver la tabla"
        x-on:keydown.enter.prevent="abrirLugar('{{ $lugar }}')"
        x-on:keydown.space.prevent="abrirLugar('{{ $lugar }}')"
    >
        <div class="tq-cara tq-cara-arriba" style="width: {{ $pila['ancho'] }}px; height: {{ $pila['fondo'] }}px; transform: translateZ(72px)">+{{ $pila['cantidad'] }} más</div>
        <div class="tq-cara tq-cara-frente" style="top: {{ $pila['fondo'] - 72 }}px; width: {{ $pila['ancho'] }}px; height: 72px"></div>
        <div class="tq-cara tq-cara-atras" style="top: -72px; width: {{ $pila['ancho'] }}px; height: 72px"></div>
        <div class="tq-cara tq-cara-izq" style="left: -72px; width: 72px; height: {{ $pila['fondo'] }}px"></div>
        <div class="tq-cara tq-cara-der" style="left: {{ $pila['ancho'] }}px; width: 72px; height: {{ $pila['fondo'] }}px"></div>
    </div>
</div>
