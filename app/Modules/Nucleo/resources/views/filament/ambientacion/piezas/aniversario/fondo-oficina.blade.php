{{-- Aniversario · fondo · La oficina: pared con ventanales y piso claro. --}}
<rect x="0" y="44" width="80" height="24" fill="#E8EBF2"/>
@foreach ([4, 58] as $x)
    <rect x="{{ $x }}" y="48" width="18" height="14" rx="1" fill="#BFE4F6" stroke="#A6B4C8" stroke-width="1"/>
    <path d="M{{ $x + 9 }} 48v14" stroke="#A6B4C8" stroke-width="0.8"/>
@endforeach
<rect x="0" y="68" width="80" height="12" fill="#C9CFDA"/>
