{{--
    Una caja de vidrio de la faja (plan 2.21b, pasos 3 y 5), en una zona o en una sala. Recibe `$caja` (de
    `FajaDeVidrio`), `$lugar`, `$modo` y, en las salas, `$tono` ('ganada' o 'perdida') y `$z` (sobre el pallet).
--}}
@php
    $ancho = $caja['ancho'];
    $fondo = $caja['fondo'];
    $alto = $caja['alto'];
    $tono ??= null;
    $z ??= 0;
@endphp
<div class="tq-faja-abs" style="left: {{ $caja['x'] }}px; top: {{ $caja['y'] }}px" wire:key="faja-caja-{{ $caja['id'] }}" data-sitio>
    {{-- Lo que mueve el arrastre (paso 4): Livewire no toca su estilo; la faja lo limpia al recargar. --}}
    <div class="tq-faja-abs" data-desplazo wire:ignore.self>
        <div @class(['tq-faja-cubo', 'tq-faja-caja', 'tq-faja-modo-'.($modo === 'completas' ? 'completa' : 'codigo'), 'tq-faja-'.$tono => $tono !== null, 'tq-faja-atascada' => $caja['atascada']])
            style="left: {{ -$ancho / 2 }}px; top: {{ -$fondo / 2 }}px; width: {{ $ancho }}px; height: {{ $fondo }}px; --tq-z: {{ $z }}px; --tq-giro: {{ $caja['giro'] }}deg; --tq-tumbo: {{ $caja['tumbo'] }}deg"
            data-caja="{{ $caja['id'] }}" data-lugar="{{ $lugar }}" data-version="{{ $caja['version'] }}" data-url="{{ $caja['url'] }}"
            tabindex="0" role="button"
            aria-label="{{ $caja['numero'] }}, {{ $caja['nombre'] }}, {{ $caja['cuenta'] }}, {{ $caja['montoCompleto'] }}{{ $caja['atascada'] ? ', atascada' : '' }}"
            x-on:keydown.enter.prevent="abrirCaja({{ $caja['id'] }})"
            x-on:keydown.space.prevent="abrirCaja({{ $caja['id'] }})"
        >
            <div class="tq-cara tq-cara-arriba" style="width: {{ $ancho }}px; height: {{ $fondo }}px; transform: translateZ({{ $alto }}px)">
                @if ($caja['avisoProximaAccion'])
                    <div class="tq-faja-pulso"></div>
                @endif
            </div>
            <div class="tq-cara tq-cara-frente" style="top: {{ $fondo - $alto }}px; width: {{ $ancho }}px; height: {{ $alto }}px">
                @if ($caja['atascada'])
                    <svg class="tq-faja-grieta" viewBox="0 0 100 100" preserveAspectRatio="none" aria-hidden="true"><polyline points="62,0 55,22 66,38 50,60 58,78 48,100" stroke-width="1.6"/><polyline points="55,22 40,30" stroke-width="1"/></svg>
                @endif
            </div>
            <div class="tq-cara tq-cara-atras" style="top: {{ -$alto }}px; width: {{ $ancho }}px; height: {{ $alto }}px"></div>
            <div class="tq-cara tq-cara-izq" style="left: {{ -$alto }}px; width: {{ $alto }}px; height: {{ $fondo }}px"></div>
            <div class="tq-cara tq-cara-der" style="left: {{ $ancho }}px; width: {{ $alto }}px; height: {{ $fondo }}px"></div>
            <div class="tq-cara tq-cara-frente tq-cara-rotulo" style="top: {{ $fondo - $alto }}px; width: {{ $ancho }}px; height: {{ $alto }}px" aria-hidden="true">
                <div class="tq-faja-grabado">
                    @if ($modo === 'completas')
                        <b>{{ $caja['nombre'] }}</b><span title="{{ $caja['montoCompleto'] }}">{{ $caja['monto'] }}</span><span>{{ $caja['cuenta'] }}</span>
                    @else
                        <b>{{ $caja['numero'] }}</b>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
