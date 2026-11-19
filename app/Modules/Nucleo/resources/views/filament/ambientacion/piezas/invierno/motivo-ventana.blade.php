{{-- Invierno · motivo · Ventana empañada con gotitas. --}}
<style>
    .tq-corre .tq-pz-gotita { animation: tq-pz-gt2 1.6s ease-in both; }
    @keyframes tq-pz-gt2 { from { transform: none; } to { transform: translateY(6px); } }
</style>
<rect x="26" y="36" width="28" height="30" rx="2" fill="#C9D9E6"/>
<rect x="26" y="36" width="28" height="30" rx="2" fill="none" stroke="#8A6143" stroke-width="2.4"/>
<path d="M40 36v30M26 51h28" stroke="#8A6143" stroke-width="1.6"/>
<path d="M30 42c2 1 4 1 6 0" stroke="#FFFFFF" stroke-width="1" opacity="0.8" fill="none"/>
@foreach ([[32, 54, 0], [46, 44, 0.3], [48, 56, 0.6]] as [$x, $y, $r])
    <ellipse class="tq-pz-gotita" style="animation-delay: {{ $r }}s" cx="{{ $x }}" cy="{{ $y }}" rx="0.8" ry="1.2" fill="#FFFFFF" opacity="0.9"/>
@endforeach
<path d="M24 66h32" stroke="#8A6143" stroke-width="2.4" stroke-linecap="round"/>
