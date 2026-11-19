{{-- Semana Santa · Lirio: el lirio blanco de Pascua se abre. --}}
<svg viewBox="0 0 64 64" aria-hidden="true">
    <style>
        .tq-corre .tq-sl-tallo { stroke-dasharray: 34; animation: tq-sl-crece 0.6s ease-out both; }
        .tq-corre .tq-sl-flor { transform-origin: center bottom; animation: tq-sl-abre 0.9s 0.5s cubic-bezier(0.3, 1.4, 0.5, 1) both; }
        @keyframes tq-sl-crece { from { stroke-dashoffset: 34; } to { stroke-dashoffset: 0; } }
        @keyframes tq-sl-abre { from { transform: scale(0.2, 0); } to { transform: none; } }
    </style>
    <path class="tq-sl-tallo" d="M32 62V30" stroke="#5E9E57" stroke-width="2.6" stroke-linecap="round" fill="none"/>
    <path d="M32 50c-8-2-12-7-13-13 7 1 11 6 13 13zM32 46c8-2 12-7 13-13-7 1-11 6-13 13z" fill="#8CC084"/>
    <g class="tq-sl-flor">
        <path d="M32 30c-4-6-14-8-18-16 8 0 14 4 18 10 4-6 10-10 18-10-4 8-14 10-18 16z" fill="#FFFFFF" stroke="#D8CFE8" stroke-width="1.5" stroke-linejoin="round"/>
        <path d="M32 30c-2-8-2-16 0-24 2 8 2 16 0 24z" fill="#F6F0F0" stroke="#D8CFE8" stroke-width="1.5"/>
        <path d="M32 26v-8M30 18l2-2 2 2" stroke="#F2C14E" stroke-width="1.6" stroke-linecap="round" fill="none"/>
    </g>
</svg>
