{{-- Semana Santa · Ramos: dos hojas de palma se abren, como el Domingo de Ramos. --}}
<svg viewBox="0 0 64 64" aria-hidden="true">
    <style>
        .tq-corre .tq-sr-izq { transform-origin: right bottom; animation: tq-sr-abre-i 0.8s ease-out both; }
        .tq-corre .tq-sr-der { transform-origin: left bottom; animation: tq-sr-abre-d 0.8s 0.2s ease-out both; }
        @keyframes tq-sr-abre-i { from { transform: rotate(25deg) scale(0.3); opacity: 0; } to { transform: none; opacity: 1; } }
        @keyframes tq-sr-abre-d { from { transform: rotate(-25deg) scale(0.3); opacity: 0; } to { transform: none; opacity: 1; } }
    </style>
    <g class="tq-sr-izq">
        <path d="M31 58C26 42 18 26 8 14" stroke="#5E9E57" stroke-width="2.4" fill="none" stroke-linecap="round"/>
        @foreach ([[12, 18], [16, 25], [20, 32], [24, 40], [27, 48]] as [$x, $y])
            <path d="M{{ $x }} {{ $y }}c-6-1-9-4-10-8M{{ $x }} {{ $y }}c1-6 4-9 8-10" stroke="#8CC084" stroke-width="2.2" fill="none" stroke-linecap="round"/>
        @endforeach
    </g>
    <g class="tq-sr-der">
        <path d="M33 58C38 42 46 26 56 14" stroke="#5E9E57" stroke-width="2.4" fill="none" stroke-linecap="round"/>
        @foreach ([[52, 18], [48, 25], [44, 32], [40, 40], [37, 48]] as [$x, $y])
            <path d="M{{ $x }} {{ $y }}c6-1 9-4 10-8M{{ $x }} {{ $y }}c-1-6-4-9-8-10" stroke="#8CC084" stroke-width="2.2" fill="none" stroke-linecap="round"/>
        @endforeach
    </g>
    <path d="M28 58h8" stroke="#7B5EA7" stroke-width="4" stroke-linecap="round"/>
</svg>
