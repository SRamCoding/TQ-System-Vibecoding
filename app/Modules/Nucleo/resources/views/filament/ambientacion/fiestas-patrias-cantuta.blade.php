{{-- Fiestas Patrias · Cantuta: la flor nacional del Perú cuelga y se abre. --}}
<svg viewBox="0 0 64 64" aria-hidden="true">
    <style>
        .tq-corre .tq-fk-flor { transform-origin: center top; animation: tq-fk-cuelga 0.8s cubic-bezier(0.3, 1.5, 0.5, 1) both; }
        .tq-corre .tq-fk-2 { animation-delay: 0.25s; }
        @keyframes tq-fk-cuelga { from { transform: scaleY(0) rotate(15deg); } to { transform: none; } }
    </style>
    <path d="M6 10c16 2 36 0 52 6" stroke="#5E9E57" stroke-width="2.4" fill="none" stroke-linecap="round"/>
    <path d="M18 11c-2-4 0-7 4-7-1 3-2 5-4 7zM46 13c2-4 6-5 8-3-3 1-5 2-8 3z" fill="#8CC084"/>
    @foreach ([[22, 12], [42, 14]] as [$x, $y])
        <g class="tq-fk-flor tq-fk-{{ $loop->iteration }}">
            <path d="M{{ $x }} {{ $y }}v8" stroke="#5E9E57" stroke-width="2"/>
            <path d="M{{ $x - 3 }} {{ $y + 8 }}h6l3 26c-2 3-10 3-12 0z" fill="#D91023"/>
            <path d="M{{ $x - 6 }} {{ $y + 34 }}l3 6 3-4 3 4 3-4 3 6c-4 3-11 3-15-2z" fill="#E8414F"/>
            <path d="M{{ $x }} {{ $y + 36 }}v6" stroke="#F2C14E" stroke-width="1.5" stroke-linecap="round"/>
        </g>
    @endforeach
</svg>
