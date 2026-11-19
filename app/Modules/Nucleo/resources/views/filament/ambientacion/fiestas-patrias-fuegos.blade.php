{{-- Fiestas Patrias · Fuegos rojiblancos: estallidos rojos y blancos sobre el cielo. --}}
<svg viewBox="0 0 64 64" aria-hidden="true">
    <style>
        .tq-corre .tq-fx-fuego { transform-origin: center; animation: tq-fx-estalla 0.7s ease-out both; }
        .tq-corre .tq-fx-2 { animation-delay: 0.3s; }
        .tq-corre .tq-fx-3 { animation-delay: 0.6s; }
        @keyframes tq-fx-estalla { from { transform: scale(0); opacity: 0; } 60% { opacity: 1; } to { transform: scale(1); opacity: 1; } }
    </style>
    <circle cx="32" cy="32" r="28" fill="#3D4772"/>
    @foreach ([[22, 24, 10, '#D91023'], [42, 22, 9, '#FFFFFF'], [32, 42, 9, '#E8414F']] as [$x, $y, $r, $color])
        <g class="tq-fx-fuego tq-fx-{{ $loop->iteration }}">
            @foreach (range(0, 330, 30) as $giro)
                <circle cx="{{ $x }}" cy="{{ $y - $r }}" r="1.6" fill="{{ $color }}" transform="rotate({{ $giro }} {{ $x }} {{ $y }})"/>
            @endforeach
            <circle cx="{{ $x }}" cy="{{ $y }}" r="2" fill="{{ $color }}"/>
        </g>
    @endforeach
</svg>
