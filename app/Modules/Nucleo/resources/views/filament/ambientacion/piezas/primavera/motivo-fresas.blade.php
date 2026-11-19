{{-- Primavera · motivo · Fresas: una mata con fresas rojas. --}}
<style>
    .tq-corre .tq-pz-fresa { animation: tq-pz-fr 0.8s cubic-bezier(0.3, 1.4, 0.5, 1) both; transform-box: fill-box; transform-origin: top center; }
    @keyframes tq-pz-fr { from { transform: scale(0); } to { transform: none; } }
</style>
<ellipse cx="34" cy="62" rx="7" ry="4" fill="#5E9E57"/>
<ellipse cx="46" cy="61" rx="7" ry="4" fill="#6DB062"/>
<ellipse cx="40" cy="57" rx="7" ry="4" fill="#7CC36C"/>
@foreach ([[31, 66, 0], [41, 65, 0.15], [50, 66, 0.3]] as [$x, $y, $r])
    <g class="tq-pz-fresa" style="animation-delay: {{ $r }}s">
        <path d="M{{ $x - 3 }} {{ $y }}c0 4 2 6 3 6s3-2 3-6z" fill="#E2455A"/>
        <path d="M{{ $x - 3 }} {{ $y }}l1.5-1.6 1.5 1 1.5-1 1.5 1.6z" fill="#4E8F48"/>
        <circle cx="{{ $x - 1 }}" cy="{{ $y + 2 }}" r="0.4" fill="#FCE79A"/><circle cx="{{ $x + 1 }}" cy="{{ $y + 3 }}" r="0.4" fill="#FCE79A"/>
    </g>
@endforeach
