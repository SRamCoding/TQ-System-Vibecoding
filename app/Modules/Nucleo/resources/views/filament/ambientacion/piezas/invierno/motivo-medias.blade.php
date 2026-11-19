{{-- Invierno · motivo · Medias de lana tendidas. --}}
<style>
    .tq-corre .tq-pz-media { animation: tq-pz-md 1.6s ease-in-out both; transform-box: fill-box; transform-origin: top center; }
    @keyframes tq-pz-md { 0%, 100% { transform: none; } 40% { transform: rotate(7deg); } 70% { transform: rotate(-5deg); } }
</style>
<path d="M18 40c14 4 30 4 44 0" stroke="#8A6143" stroke-width="0.8" fill="none"/>
@foreach ([[30, '#E8414F', 0], [42, '#F2C14E', 0.15], [54, '#6E9FE0', 0.3]] as [$x, $c, $r])
    <g class="tq-pz-media" style="animation-delay: {{ $r }}s">
        <path d="M{{ $x - 3 }} 43h6v12l4 2a2 2 0 0 1-1 4h-6a3 3 0 0 1-3-3z" fill="{{ $c }}"/>
        <path d="M{{ $x - 3 }} 46h6" stroke="#FFFFFF" stroke-width="1.2"/>
        <rect x="{{ $x - 1 }}" y="41" width="2" height="3" fill="#C9A97B"/>
    </g>
@endforeach
