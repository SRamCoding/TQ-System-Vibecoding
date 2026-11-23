{{-- Observaciones 6, punto 12: el panel deslizante de un subcontrato (un clic en su tarjeta), con sus botones. --}}
@php $C = \App\Modules\Erp\Valorizaciones\Filament\Pages\Subcontratos::class; @endphp
<div class="tq-sc" style="display: flex; flex-direction: column; gap: 20px">
    @include('valorizaciones::subcontratos.estilos')
    @if ($e === null)
        <p class="tq-sc-suave">Ese subcontrato ya no existe.</p>
    @else
        @include('valorizaciones::subcontratos.resumen', ['e' => $e])

        @if ($e['pendientes'] !== [])
            @php $v = $e['pendientes'][0]; @endphp
            <div class="tq-sc-caja" style="padding: 12px 14px; font-size: 13px">
                <b>Val. {{ $v['numero'] }} · {{ $v['periodo'] }}</b> · avance {{ $C::pct($v['avance_pct']) }}
                <div class="tq-sc-num">{{ $C::soles($v['monto']) }} − {{ $C::soles($v['amortizacion']) }} − {{ $C::soles($v['retencion']) }} = <b>{{ $C::soles($v['neto']) }}</b></div>
            </div>
        @endif

        <div style="display: flex; flex-wrap: wrap; gap: 8px">
            @if ($C::puedeGestionar() && ! $e['terminado'])
                <x-filament::button color="gray" icon="heroicon-o-plus" wire:click="replaceMountedAction('valorizar', { sc: {{ $e['id'] }} })">Valorización del subcontratista</x-filament::button>
            @endif
            @if ($C::puedeGestionar() && $e['pendientes'] !== [])
                <x-filament::button color="success" icon="heroicon-o-check" wire:click="replaceMountedAction('aprobar', { id: {{ $e['pendientes'][0]['id'] }} })">Aprobar</x-filament::button>
            @endif
            @if ($e['listo_liberar'])
                <x-filament::button icon="heroicon-o-lock-open" wire:click="replaceMountedAction('erpValorizacionesLiberarRetencion', { id: {{ $e['id'] }} })">Liberar retención</x-filament::button>
            @endif
            <x-filament::button color="gray" tag="a" :href="$C::urlFicha($e['id'])" wire:navigate icon="heroicon-o-arrow-top-right-on-square">Abrir ficha</x-filament::button>
        </div>
    @endif
</div>
