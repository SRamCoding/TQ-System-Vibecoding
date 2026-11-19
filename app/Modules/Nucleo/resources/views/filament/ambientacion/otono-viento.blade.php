{{-- Otoño · Viento: sopla el viento y se lleva una hoja. --}}
<svg viewBox="0 0 64 64" aria-hidden="true">
    <style>
        .tq-corre .tq-ov-rafaga { stroke-dasharray: 50; animation: tq-ov-sopla 0.9s ease-out both; }
        .tq-corre .tq-ov-r2 { animation-delay: 0.2s; }
        .tq-corre .tq-ov-hoja { animation: tq-ov-vuela 1.2s 0.3s ease-out both; }
        @keyframes tq-ov-sopla { from { stroke-dashoffset: 50; } to { stroke-dashoffset: 0; } }
        @keyframes tq-ov-vuela { from { transform: translate(-30px, 10px) rotate(-120deg); opacity: 0; } to { transform: none; opacity: 1; } }
    </style>
    <path class="tq-ov-rafaga tq-ov-r1" d="M4 24h34a6 6 0 1 0-6-6" stroke="#A6B4C8" stroke-width="3" fill="none" stroke-linecap="round"/>
    <path class="tq-ov-rafaga tq-ov-r2" d="M4 36h44a6 6 0 1 1-6 6" stroke="#A6B4C8" stroke-width="3" fill="none" stroke-linecap="round"/>
    <g class="tq-ov-hoja">
        <path d="M50 12c7 3 8 12 2 16-7-3-8-12-2-16z" fill="#D9861F"/>
        <path d="M50 13l2 14" stroke="#8B5E3C" stroke-width="1.2"/>
    </g>
</svg>
