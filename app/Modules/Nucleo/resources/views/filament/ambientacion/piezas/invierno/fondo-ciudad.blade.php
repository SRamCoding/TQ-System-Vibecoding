{{-- Invierno · fondo · Ciudad: techos bajos y una calle mojada. --}}
@foreach ([[0, 50, 14, '#B8C0CC'], [14, 46, 12, '#A6B0BE'], [26, 52, 16, '#C4CBD5'], [54, 48, 14, '#A6B0BE'], [68, 52, 12, '#B8C0CC']] as [$x, $y, $w, $c])
    <rect x="{{ $x }}" y="{{ $y }}" width="{{ $w }}" height="{{ 64 - $y }}" fill="{{ $c }}"/>
    <rect x="{{ $x + 3 }}" y="{{ $y + 4 }}" width="3" height="3" fill="#F2E3A6" opacity="0.8"/>
@endforeach
<rect x="0" y="64" width="80" height="16" fill="#8E97A6"/>
<path d="M8 72h10M40 74h12" stroke="#B8C0CC" stroke-width="1" stroke-linecap="round"/>
