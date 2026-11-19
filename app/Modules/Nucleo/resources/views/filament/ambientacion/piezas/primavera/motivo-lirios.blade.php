{{-- Primavera · motivo · Lirios: dos lirios morados de hojas largas. --}}
<style>
    .tq-corre .tq-pz-lirio { animation: tq-pz-li 0.9s ease-out both; transform-origin: center bottom; transform-box: fill-box; }
    @keyframes tq-pz-li { from { transform: scaleY(0.3); opacity: 0; } to { transform: none; opacity: 1; } }
</style>
@foreach ([[34, 44, 0], [47, 40, 0.2]] as [$x, $y, $r])
    <g class="tq-pz-lirio" style="animation-delay: {{ $r }}s">
        <path d="M{{ $x }} 70V{{ $y + 6 }}" stroke="#4E8F48" stroke-width="1.8" stroke-linecap="round"/>
        <path d="M{{ $x - 4 }} 70c0-10 2-16 4-20M{{ $x + 4 }} 70c0-9-1-15-3-19" stroke="#6DB062" stroke-width="2" stroke-linecap="round" fill="none"/>
        <path d="M{{ $x }} {{ $y + 6 }}c-6-2-7-7-5-10 2 3 4 5 5 10zM{{ $x }} {{ $y + 6 }}c6-2 7-7 5-10-2 3-4 5-5 10z" fill="#8B6FD6"/>
        <path d="M{{ $x }} {{ $y + 6 }}c-2-5-1-9 0-11 1 2 2 6 0 11z" fill="#B9A3EC"/>
        <path d="M{{ $x - 1 }} {{ $y + 3 }}h2" stroke="#F6CF4E" stroke-width="1"/>
    </g>
@endforeach
