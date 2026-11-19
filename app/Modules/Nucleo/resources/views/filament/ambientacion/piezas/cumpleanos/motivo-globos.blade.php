{{-- Cumpleaños · motivo · Globos que suben. --}}
<style>
    .tq-corre .tq-pz-globo { animation: tq-pz-gb 1.6s ease-out both; }
    @keyframes tq-pz-gb { from { transform: translateY(16px); opacity: 0; } to { transform: none; opacity: 1; } }
</style>
@foreach ([[32, 42, '#E06C9F', 0], [42, 36, '#4FA8C8', 0.2], [50, 44, '#F6C944', 0.4]] as [$x, $y, $c, $r])
    <g class="tq-pz-globo" style="animation-delay: {{ $r }}s">
        <path d="M{{ $x }} {{ $y + 8 }}c-2 6 4 10 2 {{ 68 - $y - 8 - 4 }}" stroke="#9AA3B0" stroke-width="0.6" fill="none"/>
        <ellipse cx="{{ $x }}" cy="{{ $y }}" rx="5.4" ry="7" fill="{{ $c }}"/>
        <path d="M{{ $x - 1 }} {{ $y + 7 }}h2l-1 1.6z" fill="{{ $c }}"/>
        <ellipse cx="{{ $x - 2 }}" cy="{{ $y - 3 }}" rx="1.2" ry="2" fill="#FFFFFF" opacity="0.5"/>
    </g>
@endforeach
