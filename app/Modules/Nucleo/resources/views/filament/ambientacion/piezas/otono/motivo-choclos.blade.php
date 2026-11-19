{{-- Otoño · motivo · Choclos de la cosecha. --}}
<style>
    .tq-corre .tq-pz-choclo { animation: tq-pz-ch 0.8s ease-out both; transform-box: fill-box; transform-origin: center bottom; }
    @keyframes tq-pz-ch { from { transform: rotate(-20deg) scale(0.5); opacity: 0; } to { transform: none; opacity: 1; } }
</style>
@foreach ([[34, -12, 0], [46, 12, 0.2]] as [$x, $g, $r])
    <g class="tq-pz-choclo" style="animation-delay: {{ $r }}s" transform="rotate({{ $g }} {{ $x }} 70)">
        <ellipse cx="{{ $x }}" cy="56" rx="4" ry="11" fill="#F2D57A"/>
        @foreach ([48, 52, 56, 60, 64] as $y)
            <path d="M{{ $x - 3 }} {{ $y }}h6" stroke="#E0B94E" stroke-width="0.7"/>
        @endforeach
        <path d="M{{ $x }} 70c-6-4-7-12-5-18M{{ $x }} 70c6-4 7-12 5-18" stroke="#8FB35A" stroke-width="2" fill="none"/>
    </g>
@endforeach
