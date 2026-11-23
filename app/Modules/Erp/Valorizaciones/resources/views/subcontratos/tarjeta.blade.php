{{-- Una tarjeta de subcontrato: un clic abre el panel con el resumen; doble clic, la ficha. --}}
@php $C = \App\Modules\Erp\Valorizaciones\Filament\Pages\Subcontratos::class; @endphp
<div class="tq-sc-tarjeta" role="button" tabindex="0" data-tq-fila="sub-{{ $e['id'] }}" wire:key="sub-{{ $e['id'] }}"
    @if ($e['alarma']) data-alarma @endif @if ($e['terminado']) data-terminado @endif
    aria-label="{{ $e['codigo'] }} · {{ $e['subcontratista'] }}: abrir el resumen (doble clic, la ficha)"
    x-data="{ t: null }"
    x-on:click="clearTimeout(t); t = setTimeout(() => $wire.mountAction('resumen', { id: {{ $e['id'] }} }), 240)"
    x-on:dblclick="clearTimeout(t); Livewire.navigate(@js($C::urlFicha($e['id'])))"
    x-on:keydown.enter.prevent="$wire.mountAction('resumen', { id: {{ $e['id'] }} })">
    <div class="tq-sc-cabeza">
        <span class="tq-sc-avatar" aria-hidden="true">{{ $e['iniciales'] }}</span>
        <div style="flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 1px">
            <span class="tq-sc-corta" style="font-size: 15px; font-weight: 700">{{ $e['subcontratista'] }}</span>
            <span class="tq-sc-corta tq-sc-suave" style="font-size: 13px">{{ $e['objeto'] }}</span>
            <span class="tq-sc-suave" style="font-size: 12px">{{ $e['codigo'] }} · <span title="{{ $e['proyecto_nombre'] }}">{{ $e['proyecto'] }}</span></span>
        </div>
        <div style="display: flex; flex-direction: column; align-items: flex-end; gap: 4px">
            <span class="tq-sc-num" style="font-size: 16px; font-weight: 800">{{ $C::soles($e['monto']) }}</span>
            @if ($e['terminado'])
                <span class="tq-sc-insignia">Terminado</span>
            @elseif ($e['pendientes'] !== [])
                <span class="tq-sc-insignia" data-tono="por_aprobar">Por aprobar</span>
            @elseif ($e['listo_liberar'])
                <span class="tq-sc-insignia" data-tono="liberar">Listo para liberar</span>
            @endif
        </div>
    </div>

    @include('valorizaciones::subcontratos.barra', ['e' => $e])

    <div class="tq-sc-datos">
        <span><span class="tq-sc-suave">Avance</span><b class="tq-sc-num">{{ $C::pct($e['avance']) }}</b></span>
        <span><span class="tq-sc-suave">Adelanto por amortizar</span><b class="tq-sc-num">{{ $C::soles($e['por_amortizar']) }}</b></span>
        <span><span class="tq-sc-suave">Retención acumulada</span><b class="tq-sc-num">{{ $C::soles($e['retencion']) }}</b></span>
    </div>

    <div style="display: flex; flex-wrap: wrap; justify-content: space-between; gap: 6px 12px; align-items: center; min-height: 18px">
        @include('valorizaciones::subcontratos.aviso', ['e' => $e])
        @if (bccomp($e['por_pagar'], '0', 2) > 0)
            <span style="font-size: 12px; font-weight: 600">Por pagar <span class="tq-sc-num">{{ $C::soles($e['por_pagar']) }}</span></span>
        @elseif ($e['terminado'] && $e['retencion_pagada'])
            <span class="tq-sc-suave" style="font-size: 12px">Retención devuelta {{ $C::soles($e['retencion_liberada']) }}</span>
        @endif
    </div>
</div>
