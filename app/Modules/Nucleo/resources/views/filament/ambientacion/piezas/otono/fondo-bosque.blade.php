{{-- Otoño · fondo · Bosque: árboles dorados a lo lejos. --}}
<path d="M0 62c20-3 50-3 80 0V80H0z" fill="#C9B07A"/>
@foreach ([[6, 54, '#D9861F'], [16, 56, '#C0602B'], [62, 54, '#E0A040'], [72, 56, '#C0602B']] as [$x, $y, $c])
    <path d="M{{ $x }} {{ $y + 4 }}v6" stroke="#7A5240" stroke-width="1"/>
    <circle cx="{{ $x }}" cy="{{ $y }}" r="5" fill="{{ $c }}"/>
@endforeach
<rect x="0" y="68" width="80" height="12" fill="#B89B62"/>
