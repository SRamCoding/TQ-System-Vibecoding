{{-- Año Nuevo · Fuegos: tres estallidos de colores, uno tras otro. --}}
<svg viewBox="0 0 64 64" aria-hidden="true">
    <style>
        .tq-corre .tq-yf-fuego { transform-origin: center; animation: tq-yf-estalla 0.7s ease-out both; }
        .tq-corre .tq-yf-2 { animation-delay: 0.35s; }
        .tq-corre .tq-yf-3 { animation-delay: 0.7s; }
        @keyframes tq-yf-estalla { from { transform: scale(0); opacity: 0; } 60% { opacity: 1; } to { transform: scale(1); opacity: 1; } }
    </style>
    @foreach ([[22, 22, 11, '#F2C14E'], [44, 18, 9, '#E06C9F'], [38, 42, 10, '#A6DAE5']] as [$x, $y, $r, $color])
        <g class="tq-yf-fuego tq-yf-{{ $loop->iteration }}">
            @foreach (range(0, 315, 45) as $giro)
                <path d="M{{ $x }} {{ $y - 3 }}V{{ $y - $r }}" stroke="{{ $color }}" stroke-width="2.4" stroke-linecap="round" transform="rotate({{ $giro }} {{ $x }} {{ $y }})"/>
            @endforeach
            <circle cx="{{ $x }}" cy="{{ $y }}" r="1.8" fill="{{ $color }}"/>
        </g>
    @endforeach
</svg>
