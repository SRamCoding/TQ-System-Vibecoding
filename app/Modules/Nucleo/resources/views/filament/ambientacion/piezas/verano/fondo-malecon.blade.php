{{-- Verano · fondo · Malecón: baranda frente al mar. --}}
<rect x="0" y="48" width="80" height="16" fill="#5FB3D4"/>
<path d="M0 54c6-2 10 2 16 0s10-2 16 0 10 2 16 0 10-2 16 0 10 2 16 0" stroke="#D7EEF8" stroke-width="1" fill="none"/>
<rect x="0" y="64" width="80" height="16" fill="#D8D2C4"/>
<rect x="0" y="58" width="80" height="2" fill="#7F8C9D"/>
@foreach ([4, 14, 24, 34, 44, 54, 64, 74] as $x)
    <rect x="{{ $x }}" y="58" width="1.6" height="6" fill="#7F8C9D"/>
@endforeach
