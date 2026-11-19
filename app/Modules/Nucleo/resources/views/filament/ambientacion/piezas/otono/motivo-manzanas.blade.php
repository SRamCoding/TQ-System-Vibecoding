{{-- Otoño · motivo · Manzanas rojas. --}}
<style>
    .tq-corre .tq-pz-manzana { animation: tq-pz-mz 0.9s cubic-bezier(0.3, 1.5, 0.5, 1) both; transform-box: fill-box; transform-origin: center bottom; }
    @keyframes tq-pz-mz { from { transform: translateY(-14px); opacity: 0; } to { transform: none; opacity: 1; } }
</style>
@foreach ([[34, 64, 0], [46, 64, 0.2], [40, 56, 0.4]] as [$x, $y, $r])
    <g class="tq-pz-manzana" style="animation-delay: {{ $r }}s">
        <path d="M{{ $x }} {{ $y - 4 }}c-4-2-7 1-7 4 0 4 3 6 7 6s7-2 7-6c0-3-3-6-7-4z" fill="#D8443D"/>
        <path d="M{{ $x }} {{ $y - 4 }}v-2" stroke="#6B4A34" stroke-width="0.9"/>
        <path d="M{{ $x }} {{ $y - 5 }}c2-2 4-1 4 0-2 1-3 1-4 0z" fill="#6DB062"/>
    </g>
@endforeach
