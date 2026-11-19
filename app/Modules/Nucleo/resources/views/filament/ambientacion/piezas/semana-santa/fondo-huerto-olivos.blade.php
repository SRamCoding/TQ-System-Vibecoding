{{-- Semana Santa · fondo · Huerto de olivos. --}}
<rect x="0" y="62" width="80" height="18" fill="#B7B98A"/>
@foreach ([[8, 56], [70, 55]] as [$x, $y])
    <path d="M{{ $x }} 64v-6" stroke="#7A6A55" stroke-width="1.6"/>
    <ellipse cx="{{ $x }}" cy="{{ $y }}" rx="6" ry="3.6" fill="#8E9C6A"/>
@endforeach
