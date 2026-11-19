{{-- Día de la Madre · Rosa: la rosa gira y se abre. --}}
<svg viewBox="0 0 64 64" aria-hidden="true">
    <style>
        .tq-corre .tq-mo-capullo { transform-origin: center; animation: tq-mo-abre 1s ease-out both; }
        .tq-corre .tq-mo-hoja { transform-origin: center; animation: tq-mo-hoja 0.5s 0.3s ease-out both; }
        @keyframes tq-mo-abre { from { transform: scale(0.2) rotate(-120deg); } to { transform: none; } }
        @keyframes tq-mo-hoja { from { transform: scale(0); } to { transform: scale(1); } }
    </style>
    <path d="M32 62V36" stroke="#5E9E57" stroke-width="2.6" stroke-linecap="round"/>
    <path class="tq-mo-hoja" d="M32 50c-8 0-12-4-13-9 7 0 11 4 13 9z" fill="#8CC084"/>
    <path class="tq-mo-hoja" d="M32 46c7 0 11-4 12-8-6 0-10 3-12 8z" fill="#8CC084"/>
    <g class="tq-mo-capullo">
        <circle cx="32" cy="24" r="14" fill="#C0392B"/>
        <path d="M32 12c-7 2-9 9-6 14 3 4 10 3 11-2 1-4-3-6-5-4" stroke="#8E2A20" stroke-width="2" fill="none" stroke-linecap="round"/>
        <path d="M20 22c2 8 10 14 20 10" stroke="#8E2A20" stroke-width="1.8" fill="none" stroke-linecap="round"/>
    </g>
</svg>
