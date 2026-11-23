{{-- La alarma «le pagas más avance del que cobras» (más de 10 puntos: coral) o «sin valorizar al cliente» (gris). --}}
@php $C = \App\Modules\Erp\Valorizaciones\Filament\Pages\Subcontratos::class; @endphp
@if ($e['terminado'])
@elseif ($e['cliente'] === null)
    <span class="tq-sc-aviso" data-gris>Sin valorizar al cliente</span>
@elseif ($e['alarma'])
    <span class="tq-sc-aviso"><x-filament::icon icon="heroicon-m-exclamation-triangle" class="h-4 w-4" />Le pagas {{ $C::pct($e['avance']) }}, cobraste {{ $C::pct($e['cliente']) }}</span>
@elseif ($completo ?? false)
    <span class="tq-sc-aviso" data-bien>Le pagas {{ $C::pct($e['avance']) }}, cobraste {{ $C::pct($e['cliente']) }}</span>
@endif
