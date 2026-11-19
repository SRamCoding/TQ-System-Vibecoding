{{-- Cumpleaños · Confeti: la serpentina estalla y caen papelitos (solo dentro del dibujo). --}}
<svg viewBox="0 0 64 64" aria-hidden="true">
    <style>
        .tq-corre .tq-cf-cono { transform-origin: left bottom; animation: tq-cf-sacude 0.4s ease-in-out both; }
        .tq-corre .tq-cf-papel { animation: tq-cf-vuela 0.9s 0.3s ease-out both; }
        @keyframes tq-cf-sacude { 0% { transform: rotate(0); } 50% { transform: rotate(-10deg); } to { transform: rotate(0); } }
        @keyframes tq-cf-vuela { from { transform: translate(-20px, 20px) scale(0.3); opacity: 0; } to { transform: none; opacity: 1; } }
    </style>
    <path class="tq-cf-cono" d="M8 58l12-30 18 18z" fill="#B9A7E8"/>
    <path d="M12 48l6-12M16 54l10-14" stroke="#F6F0F0" stroke-width="2"/>
    @foreach ([[36, 18, '#E06C9F', 20], [46, 10, '#F2C14E', -30], [52, 26, '#A6DAE5', 45], [40, 32, '#8CC084', 10], [56, 14, '#E06C9F', 60], [30, 8, '#A6DAE5', -15]] as [$x, $y, $color, $giro])
        <g class="tq-cf-papel"><rect x="{{ $x }}" y="{{ $y }}" width="5" height="3" rx="1" fill="{{ $color }}" transform="rotate({{ $giro }} {{ $x }} {{ $y }})"/></g>
    @endforeach
    <path class="tq-cf-papel" d="M28 24c4-4 8 0 12-4" stroke="#F2C14E" stroke-width="2" fill="none" stroke-linecap="round"/>
</svg>
