{{-- Otoño · fondo · Parque: césped con un caminito de piedras. --}}
<rect x="0" y="62" width="80" height="18" fill="#A8B866"/>
<path d="M30 80c3-6 8-10 16-14" stroke="#D8CDB8" stroke-width="6" stroke-linecap="round" fill="none"/>
@foreach ([[8, 70], [62, 72], [70, 66]] as [$x, $y])
    <ellipse cx="{{ $x }}" cy="{{ $y }}" rx="2" ry="1" fill="#D9861F"/>
@endforeach
