{{-- Fiestas Patrias · motivo · La cantuta, flor nacional. --}}
<style>
    .tq-corre .tq-pz-cantuta { animation: tq-pz-ct 1s ease-out both; transform-box: fill-box; transform-origin: top center; }
    @keyframes tq-pz-ct { from { transform: scaleY(0.2); } to { transform: none; } }
</style>
<path d="M40 70V40M40 50l-8-6M40 46l8-6" stroke="#5E9E57" stroke-width="1.4" fill="none"/>
@foreach ([[32, 44, 0], [48, 40, 0.15], [40, 40, 0.3]] as [$x, $y, $r])
    <g class="tq-pz-cantuta" style="animation-delay: {{ $r }}s">
        <path d="M{{ $x - 1.4 }} {{ $y }}h2.8l1 10h-4.8z" fill="#E8414F"/>
        <path d="M{{ $x - 3 }} {{ $y + 10 }}l1.4 3h3.2l1.4-3z" fill="#F6C944"/>
    </g>
@endforeach
