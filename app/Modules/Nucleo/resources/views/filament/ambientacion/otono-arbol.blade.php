{{-- Otoño · Árbol: el árbol se tiñe de otoño y suelta una hoja. --}}
<svg viewBox="0 0 64 64" aria-hidden="true">
    <style>
        .tq-corre .tq-oa-copa { animation: tq-oa-tine 1s ease-out both; }
        .tq-corre .tq-oa-hoja { animation: tq-oa-cae 1s 0.7s ease-in both; }
        @keyframes tq-oa-tine { from { fill: #8CC084; } to { fill: #E0A96D; } }
        @keyframes tq-oa-cae { from { transform: translate(-6px, -22px) rotate(-40deg); opacity: 0; } to { transform: none; opacity: 1; } }
    </style>
    <path d="M30 60V36M30 44l-6-6M30 40l7-7" stroke="#8B5E3C" stroke-width="4" stroke-linecap="round"/>
    <circle class="tq-oa-copa" cx="30" cy="24" r="16" fill="#E0A96D"/>
    <circle cx="22" cy="20" r="5" fill="#D9861F"/><circle cx="36" cy="28" r="5" fill="#C0392B"/><circle cx="34" cy="14" r="4" fill="#D9861F"/>
    <path class="tq-oa-hoja" d="M50 50c5 3 5 9 0 12-5-3-5-9 0-12z" fill="#C0392B"/>
    <path d="M4 60h56" stroke="#B08968" stroke-width="3" stroke-linecap="round"/>
</svg>
