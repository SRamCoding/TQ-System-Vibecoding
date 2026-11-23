{{-- El resumen de un subcontrato: el del panel deslizante y el de la cabecera de su ficha. --}}
@php $C = \App\Modules\Erp\Valorizaciones\Filament\Pages\Subcontratos::class; @endphp
<div style="display: flex; flex-direction: column; gap: 16px">
    <div class="tq-sc-cabeza">
        <span class="tq-sc-avatar" aria-hidden="true">{{ $e['iniciales'] }}</span>
        <div style="flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 1px">
            <span style="font-size: 15px; font-weight: 700">{{ $e['subcontratista'] }}</span>
            <span class="tq-sc-suave" style="font-size: 13px">{{ $e['objeto'] }}</span>
            <span class="tq-sc-suave" style="font-size: 12px">{{ $e['codigo'] }} · {{ $e['proyecto'] }} · {{ \Illuminate\Support\Str::limit($e['proyecto_nombre'], 70) }}</span>
        </div>
        <div style="display: flex; flex-direction: column; align-items: flex-end; gap: 4px">
            <span class="tq-sc-num" style="font-size: 17px; font-weight: 800">{{ $C::soles($e['monto']) }}</span>
            @if ($e['terminado'])
                <span class="tq-sc-insignia">Terminado</span>
            @elseif ($e['pendientes'] !== [])
                <span class="tq-sc-insignia" data-tono="por_aprobar">Por aprobar</span>
            @elseif ($e['listo_liberar'])
                <span class="tq-sc-insignia" data-tono="liberar">Listo para liberar</span>
            @endif
        </div>
    </div>

    @include('valorizaciones::subcontratos.barra', ['e' => $e, 'fija' => true])

    <div class="tq-sc-datos">
        <span><span class="tq-sc-suave">Le pagas (avance)</span><b class="tq-sc-num">{{ $C::pct($e['avance']) }}</b></span>
        <span><span class="tq-sc-suave">Cobraste al cliente</span><b class="tq-sc-num">{{ $e['cliente'] === null ? 'Sin valorizar' : $C::pct($e['cliente']) }}</b></span>
        <span><span class="tq-sc-suave">Por pagar</span><b class="tq-sc-num">{{ $C::soles($e['por_pagar']) }}</b></span>
        <span><span class="tq-sc-suave">Adelanto ({{ $C::pct($e['adelanto_pct']) }})</span><b class="tq-sc-num">{{ $C::soles($e['adelanto']) }}</b></span>
        <span><span class="tq-sc-suave">Por amortizar</span><b class="tq-sc-num">{{ $C::soles($e['por_amortizar']) }}</b></span>
        <span><span class="tq-sc-suave">Retención acumulada ({{ $C::pct($e['retencion_pct']) }})</span><b class="tq-sc-num">{{ $C::soles($e['retencion']) }}</b></span>
    </div>

    @include('valorizaciones::subcontratos.aviso', ['e' => $e, 'completo' => true])

    @if ($e['terminado'])
        <div style="font-size: 13px">
            Conformidad del {{ \Carbon\CarbonImmutable::parse($e['conformidad_fecha'])->format('d/m/Y') }} · retención liberada {{ $C::soles($e['retencion_liberada']) }}, {{ $e['retencion_pagada'] ? 'devuelta' : 'por pagar' }}
            @if ($e['conformidad_nota'])
                <span class="tq-sc-suave" style="display: block">«{{ $e['conformidad_nota'] }}»</span>
            @endif
        </div>
    @endif
</div>
