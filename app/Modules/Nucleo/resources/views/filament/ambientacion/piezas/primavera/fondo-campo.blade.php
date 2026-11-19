{{-- Primavera · fondo · Campo: pradera con árboles a lo lejos. --}}
<path d="M0 60c20-3 40-3 80 0V80H0z" fill="#9FD18E"/>
@foreach ([[10, 56, 4], [22, 57, 3], [66, 55, 4.5], [74, 57, 3]] as [$x, $y, $r])
    <path d="M{{ $x }} {{ $y + $r }}v4" stroke="#7A5A3E" stroke-width="1"/>
    <circle cx="{{ $x }}" cy="{{ $y }}" r="{{ $r }}" fill="#6FAE62"/>
@endforeach
<rect x="0" y="66" width="80" height="14" fill="#86C574"/>
<path d="M6 74h3M30 76h3M58 73h3" stroke="#5E9E57" stroke-width="1" stroke-linecap="round"/>
