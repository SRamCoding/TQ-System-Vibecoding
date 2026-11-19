{{-- Otoño · motivo · Remolino de hojas que gira con el viento. --}}
<style>
    .tq-corre .tq-pz-remolino { animation: tq-pz-rm 2s ease-out both; transform-origin: 40px 52px; }
    @keyframes tq-pz-rm { from { transform: rotate(-200deg) scale(0.4); opacity: 0; } to { transform: none; opacity: 1; } }
</style>
<path d="M28 60c4 6 18 6 22-2s-6-14-14-8-2 12 6 8" stroke="#D8CDB8" stroke-width="1" fill="none"/>
<g class="tq-pz-remolino">
    @foreach ([[30, 58, '#D9861F', 20], [48, 56, '#C0602B', -30], [40, 46, '#E0A040', 60], [36, 54, '#C0602B', 10], [46, 50, '#F2B84E', -50]] as [$x, $y, $c, $g])
        <ellipse cx="{{ $x }}" cy="{{ $y }}" rx="2.6" ry="1.4" fill="{{ $c }}" transform="rotate({{ $g }} {{ $x }} {{ $y }})"/>
    @endforeach
</g>
