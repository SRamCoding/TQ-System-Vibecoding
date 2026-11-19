{{-- Otoño · motivo · Granadas, una abierta. --}}
<style>
    .tq-corre .tq-pz-granada { animation: tq-pz-gn 0.8s cubic-bezier(0.3, 1.5, 0.5, 1) both; transform-box: fill-box; transform-origin: center bottom; }
    @keyframes tq-pz-gn { from { transform: scale(0.4); } to { transform: none; } }
</style>
<g class="tq-pz-granada"><circle cx="34" cy="62" r="7" fill="#B8323E"/><path d="M32 55l2-3 2 3" stroke="#8A2530" stroke-width="1.2" fill="none"/></g>
<g class="tq-pz-granada" style="animation-delay: 0.2s">
    <circle cx="48" cy="64" r="6" fill="#B8323E"/><circle cx="48" cy="64" r="4.4" fill="#F2E6D8"/>
    @foreach ([[46.5, 62.5], [49, 62], [47, 65], [49.5, 65.5], [48, 67]] as [$x, $y])
        <circle cx="{{ $x }}" cy="{{ $y }}" r="0.9" fill="#E8414F"/>
    @endforeach
</g>
