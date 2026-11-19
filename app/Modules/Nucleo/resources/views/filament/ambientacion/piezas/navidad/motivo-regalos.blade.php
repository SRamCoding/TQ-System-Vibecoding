{{-- Navidad · motivo · Regalos con lazos. --}}
<style>
    .tq-corre .tq-pz-regalo { animation: tq-pz-rg 0.7s cubic-bezier(0.3, 1.5, 0.5, 1) both; transform-box: fill-box; transform-origin: center bottom; }
    @keyframes tq-pz-rg { from { transform: scale(0.3); } to { transform: none; } }
</style>
@foreach ([[30, 56, 12, '#E8414F', '#F6C944', 0], [44, 60, 10, '#2F7D4F', '#E8414F', 0.2], [38, 46, 8, '#4FA8C8', '#FFFFFF', 0.4]] as [$x, $y, $l, $c, $lz, $r])
    <g class="tq-pz-regalo" style="animation-delay: {{ $r }}s">
        <rect x="{{ $x }}" y="{{ $y }}" width="{{ $l }}" height="{{ 70 - $y > $l ? $l : 70 - $y }}" fill="{{ $c }}"/>
        <path d="M{{ $x + $l / 2 }} {{ $y }}v{{ 70 - $y > $l ? $l : 70 - $y }}M{{ $x }} {{ $y + 3 }}h{{ $l }}" stroke="{{ $lz }}" stroke-width="1.4"/>
    </g>
@endforeach
