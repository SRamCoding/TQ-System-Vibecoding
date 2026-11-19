{{-- Primavera · motivo · Rosal: un arbusto con rosas rojas. --}}
<style>
    .tq-corre .tq-pz-rosa { animation: tq-pz-rs 0.7s ease-out both; transform-box: fill-box; transform-origin: center; }
    @keyframes tq-pz-rs { from { transform: scale(0); } to { transform: none; } }
</style>
<ellipse cx="40" cy="58" rx="16" ry="11" fill="#5E9E57"/>
<ellipse cx="34" cy="54" rx="8" ry="6" fill="#6DB062"/>
<ellipse cx="47" cy="55" rx="8" ry="6" fill="#6DB062"/>
@foreach ([[33, 52, 0], [44, 50, 0.15], [40, 59, 0.3], [50, 58, 0.45], [29, 60, 0.6]] as [$x, $y, $r])
    <g class="tq-pz-rosa" style="animation-delay: {{ $r }}s">
        <circle cx="{{ $x }}" cy="{{ $y }}" r="3.2" fill="#E2455A"/>
        <path d="M{{ $x - 1.6 }} {{ $y }}a1.6 1.6 0 1 1 3.2 0" stroke="#B82F45" stroke-width="0.8" fill="none"/>
    </g>
@endforeach
