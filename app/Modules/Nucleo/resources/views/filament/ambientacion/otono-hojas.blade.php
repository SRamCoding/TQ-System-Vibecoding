{{-- Otoño · Hojas: tres hojas caen balanceándose. --}}
<svg viewBox="0 0 64 64" aria-hidden="true">
    <style>
        .tq-corre .tq-oh-hoja { animation: tq-oh-cae 1.3s ease-in-out both; }
        .tq-corre .tq-oh-2 { animation-delay: 0.2s; }
        .tq-corre .tq-oh-3 { animation-delay: 0.4s; }
        @keyframes tq-oh-cae { 0% { transform: translate(0, -26px) rotate(-30deg); opacity: 0; } 30% { transform: translate(6px, -16px) rotate(20deg); opacity: 1; } 60% { transform: translate(-5px, -6px) rotate(-15deg); } to { transform: none; } }
    </style>
    @foreach ([[18, 22, '#D9861F', -20], [40, 30, '#C0392B', 25], [26, 46, '#E0A96D', 5]] as [$x, $y, $color, $giro])
        <g class="tq-oh-hoja tq-oh-{{ $loop->iteration }}">
            <path d="M{{ $x }} {{ $y - 9 }}c7 4 7 14 0 18-7-4-7-14 0-18z" fill="{{ $color }}" transform="rotate({{ $giro }} {{ $x }} {{ $y }})"/>
            <path d="M{{ $x }} {{ $y - 8 }}v17" stroke="#8B5E3C" stroke-width="1.2" transform="rotate({{ $giro }} {{ $x }} {{ $y }})"/>
        </g>
    @endforeach
</svg>
