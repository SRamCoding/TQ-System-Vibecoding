{{-- Verano · motivo · Sandía: dos tajadas sobre la arena. --}}
<style>
    .tq-corre .tq-pz-tajada { animation: tq-pz-tj 0.7s ease-out both; transform-box: fill-box; transform-origin: center bottom; }
    @keyframes tq-pz-tj { from { transform: rotate(-20deg) scale(0.5); opacity: 0; } to { transform: none; opacity: 1; } }
</style>
@foreach ([[34, 0, 0], [48, 8, 0.2]] as [$x, $giro, $r])
    <g class="tq-pz-tajada" style="animation-delay: {{ $r }}s" transform="rotate({{ $giro }} {{ $x }} 66)">
        <path d="M{{ $x - 9 }} 66a9 9 0 0 0 18 0z" fill="#4E8F48"/>
        <path d="M{{ $x - 8 }} 66a8 8 0 0 0 16 0z" fill="#F2F5E6"/>
        <path d="M{{ $x - 7 }} 66a7 7 0 0 0 14 0z" fill="#E8414F"/>
        <circle cx="{{ $x - 3 }}" cy="68.5" r="0.7" fill="#2E2E2E"/><circle cx="{{ $x + 2 }}" cy="70" r="0.7" fill="#2E2E2E"/>
    </g>
@endforeach
