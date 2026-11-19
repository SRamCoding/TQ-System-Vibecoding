{{-- Día de la Madre · Ramo: aparecen las flores una a una en el ramo. --}}
<svg viewBox="0 0 64 64" aria-hidden="true">
    <style>
        .tq-corre .tq-mr-flor { transform-origin: center; animation: tq-mr-abre 0.5s cubic-bezier(0.3, 1.6, 0.5, 1) both; }
        .tq-corre .tq-mr-2 { animation-delay: 0.2s; }
        .tq-corre .tq-mr-3 { animation-delay: 0.4s; }
        .tq-corre .tq-mr-4 { animation-delay: 0.6s; }
        @keyframes tq-mr-abre { from { transform: scale(0); } to { transform: scale(1); } }
    </style>
    <path d="M32 58l-6-24M32 58l0-26M32 58l6-24" stroke="#5E9E57" stroke-width="2.2" stroke-linecap="round"/>
    @foreach ([[24, 26, '#E06C9F'], [32, 18, '#F2A7C3'], [40, 26, '#B9A7E8'], [32, 32, '#F2C14E']] as [$x, $y, $color])
        <g class="tq-mr-flor tq-mr-{{ $loop->iteration }}">
            @foreach ([0, 72, 144, 216, 288] as $giro)
                <circle cx="{{ $x }}" cy="{{ $y - 4 }}" r="3.4" fill="{{ $color }}" transform="rotate({{ $giro }} {{ $x }} {{ $y }})"/>
            @endforeach
            <circle cx="{{ $x }}" cy="{{ $y }}" r="2.4" fill="#F6F0F0"/>
        </g>
    @endforeach
    <path d="M22 44h20l-6 16h-8z" fill="#A6DAE5"/>
    <path d="M26 48l12 0" stroke="#F6F0F0" stroke-width="2" stroke-linecap="round"/>
</svg>
