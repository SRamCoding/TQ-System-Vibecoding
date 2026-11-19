{{-- Navidad · Campana: se balancea y suena, con su moño. --}}
<svg viewBox="0 0 64 64" aria-hidden="true">
    <style>
        .tq-corre .tq-nc-campana { transform-origin: center top; animation: tq-nc-tane 1.4s ease-in-out both; }
        .tq-corre .tq-nc-onda { animation: tq-nc-suena 0.7s 0.5s ease-out both; }
        @keyframes tq-nc-tane { 0% { transform: rotate(0); } 20% { transform: rotate(16deg); } 45% { transform: rotate(-12deg); } 70% { transform: rotate(7deg); } to { transform: rotate(0); } }
        @keyframes tq-nc-suena { from { opacity: 0; transform: scale(0.6); } 50% { opacity: 1; } to { opacity: 0; transform: scale(1.2); } }
    </style>
    <path d="M24 10c4 4 12 4 16 0l-3 7h-10z" fill="#C0392B"/>
    <circle cx="32" cy="13" r="3" fill="#2E7D4F"/>
    <g class="tq-nc-campana">
        <path d="M32 16c-9 0-13 8-13 18v8l-5 6h36l-5-6v-8c0-10-4-18-13-18z" fill="#F2C14E"/>
        <path d="M14 48h36" stroke="#D9A83A" stroke-width="2" stroke-linecap="round"/>
        <circle cx="32" cy="52" r="4" fill="#D9A83A"/>
    </g>
    <path class="tq-nc-onda" d="M8 36c-3 4-3 10 0 14M56 36c3 4 3 10 0 14" stroke="#C0392B" stroke-width="2" fill="none" stroke-linecap="round"/>
</svg>
