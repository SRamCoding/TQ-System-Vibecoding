{{-- Otoño · motivo · Árbol dorado que suelta hojas. --}}
<style>
    .tq-corre .tq-pz-hoja-cae { animation: tq-pz-hc 2.4s ease-in both; }
    @keyframes tq-pz-hc { from { transform: none; opacity: 1; } to { transform: translate(-6px, 20px) rotate(160deg); opacity: 0; } }
</style>
<path d="M40 70V50M40 58l-7-7M40 55l6-6" stroke="#7A5240" stroke-width="3" stroke-linecap="round"/>
<circle cx="31" cy="43" r="10" fill="#E0A040"/><circle cx="49" cy="42" r="10" fill="#D9861F"/><circle cx="40" cy="34" r="11" fill="#F2B84E"/>
@foreach ([[32, 54, 0.2], [50, 52, 0.7], [44, 50, 1.2]] as [$x, $y, $r])
    <ellipse class="tq-pz-hoja-cae" style="animation-delay: {{ $r }}s" cx="{{ $x }}" cy="{{ $y }}" rx="1.8" ry="1" fill="#D9861F"/>
@endforeach
