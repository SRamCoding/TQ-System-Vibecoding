{{-- Año Nuevo · Calendario: la hoja se da vuelta y aparece el 1. --}}
<svg viewBox="0 0 64 64" aria-hidden="true">
    <style>
        .tq-corre .tq-yk-hoja { transform-origin: center top; animation: tq-yk-voltea 0.8s ease-in both; }
        .tq-corre .tq-yk-uno { transform-origin: center; animation: tq-yk-aparece 0.5s 0.7s cubic-bezier(0.3, 1.5, 0.5, 1) both; }
        @keyframes tq-yk-voltea { from { transform: none; opacity: 1; } to { transform: rotateX(90deg) translateY(-6px); opacity: 0; } }
        @keyframes tq-yk-aparece { from { transform: scale(0); } to { transform: scale(1); } }
    </style>
    <rect x="12" y="12" width="40" height="44" rx="4" fill="#F6F0F0" stroke="#3D4772" stroke-width="2.5"/>
    <rect x="12" y="12" width="40" height="12" rx="4" fill="#C0392B"/>
    <path d="M22 8v8M42 8v8" stroke="#3D4772" stroke-width="3" stroke-linecap="round"/>
    <path class="tq-yk-uno" d="M28 34l5-4v20M27 50h12" stroke="#3D4772" stroke-width="3.4" fill="none" stroke-linecap="round" stroke-linejoin="round"/>
    <g class="tq-yk-hoja" style="opacity: 0">
        <rect x="13" y="25" width="38" height="30" fill="#ECE6E6"/>
        <path d="M26 33h12M26 40h12M26 47h8" stroke="#B0A8A8" stroke-width="2.5" stroke-linecap="round"/>
    </g>
</svg>
