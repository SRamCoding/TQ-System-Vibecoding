{{-- Año Nuevo · Destellos: cuatro estrellas se encienden en el cielo de medianoche. --}}
<svg viewBox="0 0 64 64" aria-hidden="true">
    <style>
        .tq-corre .tq-yd-estrella { transform-origin: center; animation: tq-yd-brilla 0.6s cubic-bezier(0.3, 1.6, 0.5, 1) both; }
        .tq-corre .tq-yd-2 { animation-delay: 0.25s; }
        .tq-corre .tq-yd-3 { animation-delay: 0.5s; }
        .tq-corre .tq-yd-4 { animation-delay: 0.75s; }
        @keyframes tq-yd-brilla { from { transform: scale(0) rotate(-90deg); } to { transform: scale(1) rotate(0); } }
    </style>
    <circle cx="32" cy="32" r="28" fill="#3D4772"/>
    <path d="M40 14a12 12 0 1 0 8 20 14 14 0 0 1-8-20z" fill="#F6F0F0"/>
    @foreach ([[18, 22, 5], [24, 44, 3.5], [44, 46, 4.5], [14, 34, 2.5]] as [$x, $y, $t])
        <path class="tq-yd-estrella tq-yd-{{ $loop->iteration }}" d="M{{ $x }} {{ $y - $t }}l{{ $t * 0.3 }} {{ $t * 0.7 }} {{ $t * 0.7 }} {{ $t * 0.3 }}-{{ $t * 0.7 }} {{ $t * 0.3 }}-{{ $t * 0.3 }} {{ $t * 0.7 }}-{{ $t * 0.3 }}-{{ $t * 0.7 }}-{{ $t * 0.7 }}-{{ $t * 0.3 }} {{ $t * 0.7 }}-{{ $t * 0.3 }}z" fill="#F2C14E"/>
    @endforeach
</svg>
