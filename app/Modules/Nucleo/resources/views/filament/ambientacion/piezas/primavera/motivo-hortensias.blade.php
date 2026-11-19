{{-- Primavera · motivo · Hortensias: un arbusto con bolas de flores celestes y rosadas. --}}
<style>
    .tq-corre .tq-pz-hortensia { animation: tq-pz-ht 0.8s ease-out both; transform-box: fill-box; transform-origin: center; }
    @keyframes tq-pz-ht { from { transform: scale(0.3); opacity: 0; } to { transform: none; opacity: 1; } }
</style>
<ellipse cx="40" cy="62" rx="17" ry="8" fill="#5E9E57"/>
@foreach ([[32, 54, '#9EC3EC', 0], [46, 52, '#F3B3CF', 0.15], [40, 58, '#B9A3EC', 0.3]] as [$x, $y, $c, $r])
    <g class="tq-pz-hortensia" style="animation-delay: {{ $r }}s">
        <circle cx="{{ $x }}" cy="{{ $y }}" r="6.5" fill="{{ $c }}"/>
        @foreach ([[-2.5, -2], [2, -2.5], [0, 1.5], [-3, 2], [3, 1.8]] as [$dx, $dy])
            <circle cx="{{ $x + $dx }}" cy="{{ $y + $dy }}" r="1.3" fill="#FFFFFF" opacity="0.45"/>
        @endforeach
    </g>
@endforeach
