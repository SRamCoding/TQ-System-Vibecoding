{{-- Navidad · Pesebre en trazo: se dibuja el establo con el pesebre y brilla la estrella de Belén. --}}
<svg viewBox="0 0 64 64" aria-hidden="true">
    <style>
        .tq-corre .tq-np-trazo { stroke-dasharray: 120; animation: tq-np-dibuja 1.3s ease-in-out both; }
        .tq-corre .tq-np-estrella { transform-origin: center; animation: tq-np-brilla 0.8s 1.1s ease-out both; }
        .tq-corre .tq-np-rayo { animation: tq-np-rayos 0.6s 1.5s ease-out both; }
        @keyframes tq-np-dibuja { from { stroke-dashoffset: 120; } to { stroke-dashoffset: 0; } }
        @keyframes tq-np-brilla { from { transform: scale(0); opacity: 0; } 70% { transform: scale(1.3); opacity: 1; } to { transform: scale(1); opacity: 1; } }
        @keyframes tq-np-rayos { from { opacity: 0; } to { opacity: 1; } }
    </style>
    <path class="tq-np-trazo" d="M8 34L32 18l24 16M14 30v28M50 30v28M6 58h52" stroke="#8B5E3C" stroke-width="2.5" fill="none" stroke-linecap="round" stroke-linejoin="round"/>
    <path class="tq-np-trazo" d="M22 46h20l-3 8H25zM24 46c2-5 14-5 16 0" stroke="#B08968" stroke-width="2.2" fill="none" stroke-linecap="round" stroke-linejoin="round"/>
    <circle class="tq-np-trazo" cx="32" cy="42" r="3" stroke="#B08968" stroke-width="2" fill="#F6F0F0"/>
    <path class="tq-np-estrella" d="M32 3l2 5 5 2-5 2-2 5-2-5-5-2 5-2z" fill="#F2C14E"/>
    <path class="tq-np-rayo" d="M32 18v-1M32 18l-6 9M32 18l6 9" stroke="#F2C14E" stroke-width="1.2" stroke-linecap="round" opacity="0.8"/>
</svg>
