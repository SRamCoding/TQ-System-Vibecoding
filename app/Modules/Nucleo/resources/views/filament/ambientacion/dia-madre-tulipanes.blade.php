{{-- Día de la Madre · Tulipanes: tres tulipanes crecen juntos. --}}
<svg viewBox="0 0 64 64" aria-hidden="true">
    <style>
        .tq-corre .tq-mt-tul { transform-origin: center bottom; animation: tq-mt-crece 0.7s ease-out both; }
        .tq-corre .tq-mt-2 { animation-delay: 0.2s; }
        .tq-corre .tq-mt-3 { animation-delay: 0.4s; }
        @keyframes tq-mt-crece { from { transform: scaleY(0); } 75% { transform: scaleY(1.06); } to { transform: scaleY(1); } }
    </style>
    @foreach ([[18, 30, '#E06C9F'], [32, 20, '#F2A7C3'], [46, 30, '#C0392B']] as [$x, $y, $color])
        <g class="tq-mt-tul tq-mt-{{ $loop->iteration }}">
            <path d="M{{ $x }} 58V{{ $y + 8 }}" stroke="#5E9E57" stroke-width="2.4" stroke-linecap="round"/>
            <path d="M{{ $x }} 52c-6-1-8-5-8-9 4 1 7 4 8 9z" fill="#8CC084"/>
            <path d="M{{ $x - 7 }} {{ $y }}l3.5 4 3.5-6 3.5 6 3.5-4v6a7 7 0 0 1-14 0z" fill="{{ $color }}"/>
        </g>
    @endforeach
</svg>
