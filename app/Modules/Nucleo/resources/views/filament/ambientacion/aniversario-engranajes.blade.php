{{-- Aniversario · Engranajes: dos engranajes giran juntos, como el equipo. --}}
<svg viewBox="0 0 64 64" aria-hidden="true">
    <style>
        .tq-corre .tq-ae-grande { transform-origin: center; animation: tq-ae-horario 1.6s ease-in-out both; }
        .tq-corre .tq-ae-chico { transform-origin: center; animation: tq-ae-antihorario 1.6s ease-in-out both; }
        @keyframes tq-ae-horario { from { transform: rotate(-120deg); } to { transform: rotate(0); } }
        @keyframes tq-ae-antihorario { from { transform: rotate(200deg); } to { transform: rotate(0); } }
    </style>
    <g class="tq-ae-grande">
        @foreach (range(0, 315, 45) as $giro)
            <rect x="21" y="12" width="6" height="8" rx="1" fill="#3D4772" transform="rotate({{ $giro }} 24 28)"/>
        @endforeach
        <circle cx="24" cy="28" r="12" fill="#3D4772"/>
        <circle cx="24" cy="28" r="5" fill="#F6F0F0"/>
    </g>
    <g class="tq-ae-chico">
        @foreach (range(0, 300, 60) as $giro)
            <rect x="42" y="33" width="5" height="6" rx="1" fill="#A6DAE5" transform="rotate({{ $giro }} 44.5 44)"/>
        @endforeach
        <circle cx="44.5" cy="44" r="8.5" fill="#A6DAE5"/>
        <circle cx="44.5" cy="44" r="3.4" fill="#F6F0F0"/>
    </g>
</svg>
