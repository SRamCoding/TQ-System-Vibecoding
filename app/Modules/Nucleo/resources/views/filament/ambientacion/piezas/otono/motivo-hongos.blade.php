{{-- Otoño · motivo · Hongos que asoman entre las hojas. --}}
<style>
    .tq-corre .tq-pz-hongo { animation: tq-pz-hg 0.8s cubic-bezier(0.3, 1.5, 0.5, 1) both; transform-box: fill-box; transform-origin: center bottom; }
    @keyframes tq-pz-hg { from { transform: scaleY(0.2); } to { transform: none; } }
</style>
@foreach ([[34, 9, 0], [46, 7, 0.2]] as [$x, $r, $d])
    <g class="tq-pz-hongo" style="animation-delay: {{ $d }}s">
        <rect x="{{ $x - 2 }}" y="{{ 70 - $r }}" width="4" height="{{ $r }}" rx="1.5" fill="#F2E6D8"/>
        <path d="M{{ $x - $r * 0.8 }} {{ 70 - $r }}a{{ $r * 0.8 }} {{ $r * 0.6 }} 0 0 1 {{ $r * 1.6 }} 0z" fill="#C0602B"/>
        <circle cx="{{ $x - 2 }}" cy="{{ 70 - $r - 2 }}" r="0.9" fill="#F2E6D8"/><circle cx="{{ $x + 2 }}" cy="{{ 70 - $r - 3 }}" r="0.8" fill="#F2E6D8"/>
    </g>
@endforeach
