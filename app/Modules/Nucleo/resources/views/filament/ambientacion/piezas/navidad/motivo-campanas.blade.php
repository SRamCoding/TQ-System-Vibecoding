{{-- Navidad · motivo · Campanas con su lazo. --}}
<style>
    .tq-corre .tq-pz-campana { animation: tq-pz-cm3 1.4s ease-in-out both; transform-box: fill-box; transform-origin: top center; }
    @keyframes tq-pz-cm3 { 0%, 100% { transform: none; } 25% { transform: rotate(15deg); } 75% { transform: rotate(-15deg); } }
</style>
@foreach ([[34, 0], [46, 0.1]] as [$x, $r])
    <g class="tq-pz-campana" style="animation-delay: {{ $r }}s">
        <path d="M{{ $x - 7 }} 58c0-10 3-16 7-16s7 6 7 16z" fill="#F2C14E"/>
        <rect x="{{ $x - 8 }}" y="57" width="16" height="2.4" rx="1" fill="#E0A82C"/>
        <circle cx="{{ $x }}" cy="61" r="1.6" fill="#C48A1E"/>
    </g>
@endforeach
<path d="M34 42c3-4 9-4 12 0M40 40l-4-4h8z" fill="#E8414F" stroke="#E8414F" stroke-width="1.4"/>
