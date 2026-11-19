{{-- Año Nuevo · motivo · Dos copas que brindan. --}}
<style>
    .tq-corre .tq-pz-copa-i { animation: tq-pz-ci2 1s ease-out both; transform-origin: 34px 68px; }
    .tq-corre .tq-pz-copa-d { animation: tq-pz-cd 1s ease-out both; transform-origin: 46px 68px; }
    @keyframes tq-pz-ci2 { from { transform: rotate(-20deg); } to { transform: none; } }
    @keyframes tq-pz-cd { from { transform: rotate(20deg); } to { transform: none; } }
</style>
@foreach ([['tq-pz-copa-i', 34, 8], ['tq-pz-copa-d', 46, -8]] as [$c, $x, $g])
    <g class="{{ $c }}"><g transform="rotate({{ $g }} {{ $x }} 68)">
        <path d="M{{ $x - 4 }} 44h8l-1 10a3 3 0 0 1-6 0z" fill="#F6D365" opacity="0.85" stroke="#D9A83A" stroke-width="0.6"/>
        <path d="M{{ $x }} 57v10M{{ $x - 3 }} 68h6" stroke="#D9A83A" stroke-width="1"/>
    </g></g>
@endforeach
<path d="M40 40l-1-4M42 40l2-3M38 40l-3-2" stroke="#F2C14E" stroke-width="0.9" stroke-linecap="round"/>
