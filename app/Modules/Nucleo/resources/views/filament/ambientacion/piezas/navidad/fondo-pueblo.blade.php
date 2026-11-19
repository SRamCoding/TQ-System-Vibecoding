{{-- Navidad · fondo · Pueblito de Belén: casitas de techo plano en las colinas. --}}
<path d="M0 60c20-6 50-6 80 0V80H0z" fill="#C9B88E"/>
@foreach ([[8, 52, 10], [20, 54, 8], [58, 52, 10], [70, 55, 8]] as [$x, $y, $w])
    <rect x="{{ $x }}" y="{{ $y }}" width="{{ $w }}" height="{{ 60 - $y }}" fill="#E8D9B5"/>
    <rect x="{{ $x + 2 }}" y="{{ $y + 2 }}" width="2" height="2" fill="#F6C944" opacity="0.8"/>
@endforeach
<rect x="0" y="66" width="80" height="14" fill="#B8A57A"/>
