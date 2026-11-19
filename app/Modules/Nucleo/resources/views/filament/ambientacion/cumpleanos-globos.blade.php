{{-- Cumpleaños · Globos: tres globos suben flotando. --}}
<svg viewBox="0 0 64 64" aria-hidden="true">
    <style>
        .tq-corre .tq-cg-globo { animation: tq-cg-sube 1.1s cubic-bezier(0.2, 0.8, 0.4, 1) both; }
        .tq-corre .tq-cg-2 { animation-delay: 0.15s; }
        .tq-corre .tq-cg-3 { animation-delay: 0.3s; }
        @keyframes tq-cg-sube { from { transform: translateY(40px); opacity: 0; } 70% { transform: translateY(-3px); opacity: 1; } to { transform: none; } }
    </style>
    @foreach ([[20, 22, '#E06C9F'], [34, 16, '#A6DAE5'], [46, 24, '#F2C14E']] as [$x, $y, $color])
        <g class="tq-cg-globo tq-cg-{{ $loop->iteration }}">
            <path d="M{{ $x }} {{ $y + 11 }}c-2 8 4 14 2 26" stroke="#9C9388" stroke-width="1.2" fill="none"/>
            <ellipse cx="{{ $x }}" cy="{{ $y }}" rx="8" ry="10" fill="{{ $color }}"/>
            <path d="M{{ $x - 1.5 }} {{ $y + 10 }}h3l-1.5 2.5z" fill="{{ $color }}"/>
            <ellipse cx="{{ $x - 3 }}" cy="{{ $y - 4 }}" rx="2" ry="3" fill="#FFFFFF" opacity="0.6"/>
        </g>
    @endforeach
</svg>
