{{-- Primavera · fondo · Jardín: pasto y una cerca baja de madera. --}}
<rect x="0" y="62" width="80" height="18" fill="#8CC97A"/>
<path d="M0 62h80" stroke="#6FAE5F" stroke-width="1.2"/>
<g fill="#E9D3B0" stroke="#C9A97B" stroke-width="0.6">
    @foreach ([4, 16, 28, 52, 64, 76] as $x)
        <path d="M{{ $x - 2 }} 64v-9l2-2 2 2v9z"/>
    @endforeach
    <rect x="0" y="56" width="80" height="2" rx="1"/>
    <rect x="0" y="60.5" width="80" height="1.6" rx="0.8"/>
</g>
