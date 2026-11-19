{{-- Navidad · motivo · Árbol de Navidad con su estrella. --}}
<style>
    .tq-corre .tq-pz-esfera { animation: tq-pz-ef 0.6s ease-out both; transform-box: fill-box; transform-origin: center; }
    @keyframes tq-pz-ef { from { transform: scale(0); } to { transform: none; } }
</style>
<path d="M40 70v-4" stroke="#6B4A34" stroke-width="3"/>
<path d="M40 30l-13 16h6l-9 11h8l-8 9h32l-8-9h8l-9-11h6z" fill="#2F7D4F"/>
<path d="M40 26l1.2 2.6 2.8.4-2 2 .5 2.8-2.5-1.3-2.5 1.3.5-2.8-2-2 2.8-.4z" fill="#F6C944"/>
@foreach ([[36, 44, '#E8414F', 0], [44, 50, '#F6C944', 0.1], [34, 56, '#4FA8C8', 0.2], [46, 60, '#E8414F', 0.3], [40, 62, '#F6C944', 0.4]] as [$x, $y, $c, $r])
    <circle class="tq-pz-esfera" style="animation-delay: {{ $r }}s" cx="{{ $x }}" cy="{{ $y }}" r="1.6" fill="{{ $c }}"/>
@endforeach
