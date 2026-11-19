{{-- Fechas · acompañante común · Estrellitas que brillan. --}}
<style>
    .tq-corre .tq-pz-estrellita { animation: tq-pz-et 1s ease-in-out 2 alternate both; transform-box: fill-box; transform-origin: center; }
    @keyframes tq-pz-et { from { transform: scale(0.4); opacity: 0.4; } to { transform: none; opacity: 1; } }
</style>
@foreach ([[14, 22, 0], [26, 14, 0.3], [70, 40, 0.6]] as [$x, $y, $r])
    <path class="tq-pz-estrellita" style="animation-delay: {{ $r }}s" d="M{{ $x }} {{ $y - 3 }}l.9 2.1 2.1.9-2.1.9-.9 2.1-.9-2.1-2.1-.9 2.1-.9z" fill="#F6C944"/>
@endforeach
