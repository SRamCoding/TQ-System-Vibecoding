{{-- Invierno · motivo · Guantes de lana colgados. --}}
<style>
    .tq-corre .tq-pz-guante { animation: tq-pz-gu 1.6s ease-in-out both; transform-box: fill-box; transform-origin: top center; }
    @keyframes tq-pz-gu { 0%, 100% { transform: none; } 40% { transform: rotate(8deg); } 70% { transform: rotate(-6deg); } }
</style>
<path d="M22 40c12 4 24 4 36 0" stroke="#8A6143" stroke-width="0.8" fill="none"/>
@foreach ([[34, '#4FA8C8', 0], [46, '#E8414F', 0.2]] as [$x, $c, $r])
    <g class="tq-pz-guante" style="animation-delay: {{ $r }}s">
        <path d="M{{ $x - 4 }} 44h8v12a4 4 0 0 1-8 0z" fill="{{ $c }}"/>
        <path d="M{{ $x + 4 }} 50c3 0 3 4 0 5" stroke="{{ $c }}" stroke-width="2.4" stroke-linecap="round" fill="none"/>
        <path d="M{{ $x - 4 }} 47h8" stroke="#FFFFFF" stroke-width="1.4"/>
    </g>
@endforeach
