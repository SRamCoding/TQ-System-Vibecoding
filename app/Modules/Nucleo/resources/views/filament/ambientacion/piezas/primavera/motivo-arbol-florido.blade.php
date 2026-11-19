{{-- Primavera · motivo · Árbol florido: copa verde con flores rosadas que aparecen. --}}
<style>
    .tq-corre .tq-pz-flores-arbol circle { animation: tq-pz-brota 0.6s ease-out both; transform-box: fill-box; transform-origin: center; }
    @keyframes tq-pz-brota { from { transform: scale(0); } to { transform: none; } }
</style>
<path d="M40 70V50M40 58l-6-6M40 55l5-5" stroke="#8A6143" stroke-width="3" stroke-linecap="round"/>
<circle cx="32" cy="42" r="10" fill="#79B86B"/>
<circle cx="48" cy="41" r="10" fill="#6DAE60"/>
<circle cx="40" cy="34" r="11" fill="#86C478"/>
<g class="tq-pz-flores-arbol" fill="#F7B6CF">
    @foreach ([[33, 37, 0.1], [44, 31, 0.25], [50, 40, 0.4], [38, 44, 0.55], [28, 45, 0.7], [42, 39, 0.85]] as [$x, $y, $r])
        <circle cx="{{ $x }}" cy="{{ $y }}" r="1.9" style="animation-delay: {{ $r }}s"/>
    @endforeach
</g>
