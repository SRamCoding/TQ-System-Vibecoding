{{-- Fiestas Patrias · Bandera: la bandera del Perú sube por el asta y flamea. --}}
<svg viewBox="0 0 64 64" aria-hidden="true">
    <style>
        .tq-corre .tq-fb-bandera { animation: tq-fb-iza 0.9s ease-out both; }
        .tq-corre .tq-fb-tela { transform-origin: left center; animation: tq-fb-flamea 0.4s 0.9s ease-in-out 4 alternate both; }
        @keyframes tq-fb-iza { from { transform: translateY(28px); } to { transform: none; } }
        @keyframes tq-fb-flamea { from { transform: skewY(0); } to { transform: skewY(-5deg) scaleX(0.96); } }
    </style>
    <path d="M12 4v56" stroke="#8B5E3C" stroke-width="3" stroke-linecap="round"/>
    <circle cx="12" cy="4" r="2.5" fill="#F2C14E"/>
    <g class="tq-fb-bandera">
        <g class="tq-fb-tela">
            <rect x="13" y="8" width="13" height="24" fill="#D91023"/>
            <rect x="26" y="8" width="13" height="24" fill="#FFFFFF" stroke="#E5E5E5" stroke-width="0.5"/>
            <rect x="39" y="8" width="13" height="24" fill="#D91023"/>
        </g>
    </g>
</svg>
