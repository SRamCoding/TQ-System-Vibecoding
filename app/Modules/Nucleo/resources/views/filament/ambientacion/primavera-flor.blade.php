{{-- Primavera · Flor que se abre: crece el tallo, brota la hoja y se abre la flor. --}}
<svg viewBox="0 0 64 64" aria-hidden="true">
    <style>
        .tq-corre .tq-pf-tallo { stroke-dasharray: 30; animation: tq-pf-crece 0.7s ease-out both; }
        .tq-corre .tq-pf-hoja { transform-origin: right bottom; animation: tq-pf-brota 0.5s 0.5s ease-out both; }
        .tq-corre .tq-pf-flor { transform-origin: center; animation: tq-pf-abre 0.9s 0.8s cubic-bezier(0.3, 1.4, 0.5, 1) both; }
        @keyframes tq-pf-crece { from { stroke-dashoffset: 30; } to { stroke-dashoffset: 0; } }
        @keyframes tq-pf-brota { from { transform: scale(0); } to { transform: scale(1); } }
        @keyframes tq-pf-abre { from { transform: scale(0) rotate(-90deg); } to { transform: scale(1) rotate(0deg); } }
    </style>
    <path class="tq-pf-tallo" d="M32 61V32" stroke="#5E9E57" stroke-width="3" stroke-linecap="round" fill="none"/>
    <path class="tq-pf-hoja" d="M32 50c-9 0-14-5-15-11 8 0 13 4 15 11z" fill="#8CC084"/>
    <g class="tq-pf-flor">
        @foreach ([0, 72, 144, 216, 288] as $giro)
            <ellipse cx="32" cy="15" rx="6.5" ry="10" fill="#F2A7C3" transform="rotate({{ $giro }} 32 25)"/>
        @endforeach
        <circle cx="32" cy="25" r="6" fill="#F6D365"/>
    </g>
</svg>
