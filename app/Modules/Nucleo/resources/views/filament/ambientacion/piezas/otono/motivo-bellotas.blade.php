{{-- Otoño · motivo · Bellotas en una ramita. --}}
<style>
    .tq-corre .tq-pz-bellota { animation: tq-pz-bt 1.4s ease-in-out both; transform-box: fill-box; transform-origin: top center; }
    @keyframes tq-pz-bt { 0%, 100% { transform: none; } 40% { transform: rotate(10deg); } 70% { transform: rotate(-8deg); } }
</style>
<path d="M22 40c10 2 22 4 36 2" stroke="#7A5240" stroke-width="2" stroke-linecap="round" fill="none"/>
<path d="M30 41c-3-4-1-8 3-8 1 4-1 6-3 8zM50 42c3-4 7-4 8-1-3 2-5 2-8 1z" fill="#C2964E"/>
@foreach ([[36, 0], [46, 0.2]] as [$x, $r])
    <g class="tq-pz-bellota" style="animation-delay: {{ $r }}s">
        <path d="M{{ $x }} 42v3" stroke="#7A5240" stroke-width="1"/>
        <path d="M{{ $x - 4 }} 47a4 3 0 0 1 8 0z" fill="#8A6143"/>
        <path d="M{{ $x - 3.4 }} 47c0 5 2 8 3.4 8s3.4-3 3.4-8z" fill="#C48A4E"/>
    </g>
@endforeach
