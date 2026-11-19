{{-- Año Nuevo · fondo · La ciudad con sus ventanas encendidas. --}}
@foreach ([[0, 48, 14, '#4A5578'], [14, 44, 12, '#3D4772'], [26, 52, 16, '#56628A'], [54, 46, 14, '#3D4772'], [68, 50, 12, '#4A5578']] as [$x, $y, $w, $c])
    <rect x="{{ $x }}" y="{{ $y }}" width="{{ $w }}" height="{{ 66 - $y }}" fill="{{ $c }}"/>
    <rect x="{{ $x + 3 }}" y="{{ $y + 4 }}" width="2.4" height="2.4" fill="#F6D365"/>
    <rect x="{{ $x + 7 }}" y="{{ $y + 9 }}" width="2.4" height="2.4" fill="#F6D365"/>
@endforeach
<rect x="0" y="66" width="80" height="14" fill="#6B7590"/>
