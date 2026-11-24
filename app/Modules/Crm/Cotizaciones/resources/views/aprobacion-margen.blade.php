{{-- Observación 9.5 (2), lienzo `oportunidades-mejoras/Aprobacion.dc.html`: la cotización con el margen bajo el mínimo. --}}
@php
    $m = fn ($v) => $a['moneda'].' '.number_format((float) $v, 2);
    $esperando = $a['estado'] === 'por_aprobar';
@endphp
<div style="border: 1px solid color-mix(in oklab, #D99A2B 45%, var(--tq-borde, #E5E0D6)); background: color-mix(in oklab, #D99A2B 9%, var(--tq-superficie, white)); border-radius: 14px; padding: 14px 18px; display: flex; flex-direction: column; gap: 10px">
    <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap">
        <x-filament::icon icon="heroicon-o-hand-raised" class="h-5 w-5" style="color: color-mix(in oklch, #9A6A0E calc(100% - var(--tq-tinte-leve, 0%)), var(--tq-tinte, #9A6A0E))" />
        <strong>{{ $esperando ? 'Esperando aprobación' : 'Necesita aprobación' }}</strong>
        @if ($esperando && $a['pide'])<span style="font-size: 13px; color: var(--gray-500)">· la pidió {{ $a['pide'] }} el {{ $a['pedida'] }}</span>@endif
    </div>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(8rem, 1fr)); gap: 10px; font-variant-numeric: tabular-nums">
        <div><div style="font-size: 12px; color: var(--gray-500)">Costo</div><div style="font-weight: 600">{{ $m($a['costo']) }}</div></div>
        <div><div style="font-size: 12px; color: var(--gray-500)">Precio (sin IGV)</div><div style="font-weight: 600">{{ $m($a['precio']) }}</div></div>
        <div><div style="font-size: 12px; color: var(--gray-500)">Descuento</div><div style="font-weight: 600">{{ $a['descuento'] === null ? 'sin descuento' : $a['descuento'].' %' }}</div></div>
        <div><div style="font-size: 12px; color: var(--gray-500)">Margen</div><div style="font-weight: 700; color: color-mix(in oklch, #C2453C calc(100% - var(--tq-tinte-leve, 0%)), var(--tq-tinte, #C2453C))">{{ $a['margen'] }} %</div></div>
    </div>
    <div style="font-size: 13px">
        El margen queda por debajo del mínimo de <strong>{{ rtrim(rtrim($a['minimo'], '0'), '.') }} %</strong>.
        @if ($esperando)
            No se puede emitir ni enviar hasta que alguien con permiso la apruebe o la rechace (Mien → «Por aprobar»).
        @else
            Para emitirla hace falta el visto bueno: «Solicitar aprobación», arriba, con el motivo. Si vuelves a un precio con margen suficiente, no hace falta.
        @endif
    </div>
    @if ($esperando && $a['motivo'])
        <div style="font-size: 13px"><span style="color: var(--gray-500)">Motivo del descuento:</span> «{{ $a['motivo'] }}»</div>
    @endif
</div>
