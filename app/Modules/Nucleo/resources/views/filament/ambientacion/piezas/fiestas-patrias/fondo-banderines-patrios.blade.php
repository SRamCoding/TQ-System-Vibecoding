{{-- Fiestas Patrias · fondo · Plaza con banderines rojiblancos. --}}
<rect x="0" y="62" width="80" height="18" fill="#BFB6A8"/>
<path d="M0 30c20 6 60 6 80 0" stroke="#8A6143" stroke-width="0.6" fill="none"/>
@foreach ([6, 16, 26, 36, 46, 56, 66, 76] as $i => $x)
    <path d="M{{ $x - 3 }} {{ 31 + ($i > 0 && $i < 7 ? 2 : 0) }}h6l-3 5z" fill="{{ $i % 2 ? '#FFFFFF' : '#D91023' }}"/>
@endforeach
