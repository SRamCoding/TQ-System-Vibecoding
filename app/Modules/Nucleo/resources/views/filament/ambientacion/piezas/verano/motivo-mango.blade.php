{{-- Verano · motivo · Mangos: dos mangos maduros de la costa norte. --}}
<style>
    .tq-corre .tq-pz-mango { animation: tq-pz-mg2 0.9s cubic-bezier(0.3, 1.5, 0.5, 1) both; transform-box: fill-box; transform-origin: center bottom; }
    @keyframes tq-pz-mg2 { from { transform: translateY(-12px); opacity: 0; } to { transform: none; opacity: 1; } }
</style>
@foreach ([[34, 62, 0, 0], [47, 63, 20, 0.2]] as [$x, $y, $g, $r])
    <g class="tq-pz-mango" style="animation-delay: {{ $r }}s" transform="rotate({{ $g }} {{ $x }} {{ $y }})">
        <ellipse cx="{{ $x }}" cy="{{ $y }}" rx="7" ry="5.4" fill="#F7A23B"/>
        <path d="M{{ $x - 5 }} {{ $y - 1 }}c2-4 6-5 9-3" stroke="#E8414F" stroke-width="2.2" opacity="0.5" fill="none"/>
        <path d="M{{ $x + 6 }} {{ $y - 4 }}c2-2 4-2 5-1-2 2-3 2-5 1z" fill="#4E8F48"/>
    </g>
@endforeach
