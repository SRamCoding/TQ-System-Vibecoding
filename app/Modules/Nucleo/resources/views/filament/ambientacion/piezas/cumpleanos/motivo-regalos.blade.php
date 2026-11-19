{{-- Cumpleaños · motivo · Regalos de colores. --}}
<style>
    .tq-corre .tq-pz-regalo-c { animation: tq-pz-rc 0.7s cubic-bezier(0.3, 1.5, 0.5, 1) both; transform-box: fill-box; transform-origin: center bottom; }
    @keyframes tq-pz-rc { from { transform: scale(0.3); } to { transform: none; } }
</style>
@foreach ([[28, 56, 14, '#B9A3EC', '#F6C944', 0], [44, 58, 12, '#A6DAE5', '#E06C9F', 0.2]] as [$x, $y, $l, $c, $lz, $r])
    <g class="tq-pz-regalo-c" style="animation-delay: {{ $r }}s">
        <rect x="{{ $x }}" y="{{ $y }}" width="{{ $l }}" height="{{ 70 - $y }}" fill="{{ $c }}"/>
        <path d="M{{ $x + $l / 2 }} {{ $y }}v{{ 70 - $y }}M{{ $x }} {{ $y + 4 }}h{{ $l }}" stroke="{{ $lz }}" stroke-width="1.6"/>
        <path d="M{{ $x + $l / 2 }} {{ $y }}c-4-4-6 0-2 0M{{ $x + $l / 2 }} {{ $y }}c4-4 6 0 2 0" stroke="{{ $lz }}" stroke-width="1.2" fill="none"/>
    </g>
@endforeach
