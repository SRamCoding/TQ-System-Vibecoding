{{-- Fechas · acompañante común · Corazoncitos que suben. --}}
<style>
    .tq-corre .tq-pz-corazoncito { animation: tq-pz-cz 1.8s ease-out both; }
    @keyframes tq-pz-cz { from { transform: translateY(10px); opacity: 0; } to { transform: none; opacity: 1; } }
</style>
@foreach ([[16, 30, 0], [24, 22, 0.3], [66, 34, 0.6]] as [$x, $y, $r])
    <path class="tq-pz-corazoncito" style="animation-delay: {{ $r }}s" d="M{{ $x }} {{ $y + 2 }}c-3-2-4-4-2-5.4 1-.7 2 0 2 .6 0-.6 1-1.3 2-.6 2 1.4 1 3.4-2 5.4z" fill="#F29BBF"/>
@endforeach
