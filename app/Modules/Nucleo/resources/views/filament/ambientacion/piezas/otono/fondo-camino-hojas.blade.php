{{-- Otoño · fondo · Camino cubierto de hojas. --}}
<path d="M0 60c20-4 50-4 80 0V80H0z" fill="#B5A36A"/>
<path d="M24 80c4-8 8-12 18-18" stroke="#CDBB8E" stroke-width="8" stroke-linecap="round" fill="none"/>
@foreach ([[30, 74, '#D9861F'], [36, 70, '#C0602B'], [40, 66, '#E0A040'], [26, 77, '#C0602B'], [44, 64, '#D9861F']] as [$x, $y, $c])
    <ellipse cx="{{ $x }}" cy="{{ $y }}" rx="1.6" ry="0.9" fill="{{ $c }}" transform="rotate(30 {{ $x }} {{ $y }})"/>
@endforeach
