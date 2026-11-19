{{-- Día de la Madre · motivo · Ramo de flores con su lazo. --}}
<style>
    .tq-corre .tq-pz-ramo-flor { animation: tq-pz-rf 0.6s cubic-bezier(0.3, 1.5, 0.5, 1) both; transform-box: fill-box; transform-origin: center; }
    @keyframes tq-pz-rf { from { transform: scale(0); } to { transform: none; } }
</style>
<path d="M40 70l-6-20M40 70l6-20M40 70V48" stroke="#5E9E57" stroke-width="1.2"/>
@foreach ([[34, 48, '#E06C9F', 0], [46, 48, '#B9A3EC', 0.1], [40, 44, '#F2A7C3', 0.2], [36, 41, '#F6C944', 0.3], [44, 41, '#E06C9F', 0.4]] as [$x, $y, $c, $r])
    <circle class="tq-pz-ramo-flor" style="animation-delay: {{ $r }}s" cx="{{ $x }}" cy="{{ $y }}" r="4" fill="{{ $c }}"/>
@endforeach
<path d="M36 60l4 2 4-2-2 4h-4z" fill="#E8414F"/>
