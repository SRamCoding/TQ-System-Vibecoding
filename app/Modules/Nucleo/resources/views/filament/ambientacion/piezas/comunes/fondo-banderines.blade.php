{{-- Fechas · fondo común · Patio con banderines de fiesta. --}}
<rect x="0" y="62" width="80" height="18" fill="#9CC873"/>
<path d="M0 30c20 6 60 6 80 0" stroke="#8A6143" stroke-width="0.6" fill="none"/>
@foreach ([6, 16, 26, 36, 46, 56, 66, 76] as $i => $x)
    <path d="M{{ $x - 3 }} {{ 31 + ($i > 0 && $i < 7 ? 2 : 0) }}h6l-3 5z" fill="{{ ['#E8414F', '#F6C944', '#4FA8C8', '#8CC084'][$i % 4] }}"/>
@endforeach
