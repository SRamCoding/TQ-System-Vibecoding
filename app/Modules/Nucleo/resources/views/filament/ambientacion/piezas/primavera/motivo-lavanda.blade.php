{{-- Primavera · motivo · Lavanda: un manojo de espigas moradas. --}}
<style>
    .tq-corre .tq-pz-espiga { animation: tq-pz-es 1.6s ease-in-out both; transform-box: fill-box; transform-origin: center bottom; }
    @keyframes tq-pz-es { 0%, 100% { transform: none; } 40% { transform: rotate(-5deg); } 70% { transform: rotate(4deg); } }
</style>
@foreach ([[28, 46, -6, 0], [34, 40, -3, 0.1], [40, 37, 0, 0.2], [46, 40, 3, 0.3], [52, 46, 6, 0.4]] as [$x, $y, $inc, $r])
    <g class="tq-pz-espiga" style="animation-delay: {{ $r }}s">
        <path d="M40 70L{{ $x }} {{ $y + 8 }}" stroke="#7FA868" stroke-width="1.2"/>
        @foreach ([0, 3, 6, 9] as $p)
            <ellipse cx="{{ $x + $inc * 0.1 }}" cy="{{ $y + $p }}" rx="1.6" ry="1.9" fill="#9B7BD9"/>
        @endforeach
    </g>
@endforeach
