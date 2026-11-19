{{-- Navidad · Regalo: la tapa salta y sale una estrella. --}}
<svg viewBox="0 0 64 64" aria-hidden="true">
    <style>
        .tq-corre .tq-nr-tapa { transform-origin: left bottom; animation: tq-nr-abre 0.9s 0.2s ease-in-out both; }
        .tq-corre .tq-nr-estrella { transform-origin: center; animation: tq-nr-sale 0.7s 0.7s cubic-bezier(0.3, 1.5, 0.5, 1) both; }
        @keyframes tq-nr-abre { 0% { transform: rotate(0); } 40% { transform: rotate(-25deg) translateY(-4px); } to { transform: rotate(0); } }
        @keyframes tq-nr-sale { from { transform: translateY(18px) scale(0); opacity: 0; } to { transform: none; opacity: 1; } }
    </style>
    <path class="tq-nr-estrella" d="M32 2l2.6 5.3 5.9.9-4.3 4.1 1 5.8L32 15.3l-5.2 2.8 1-5.8-4.3-4.1 5.9-.9z" fill="#F2C14E"/>
    <rect x="13" y="32" width="38" height="28" rx="2" fill="#C0392B"/>
    <rect x="29" y="32" width="6" height="28" fill="#F2C14E"/>
    <g class="tq-nr-tapa">
        <rect x="10" y="24" width="44" height="9" rx="2" fill="#2E7D4F"/>
        <rect x="29" y="24" width="6" height="9" fill="#F2C14E"/>
        <path d="M32 24c-6-8-14-6-10-1 2 2 7 1 10 1zM32 24c6-8 14-6 10-1-2 2-7 1-10 1z" fill="#F2C14E"/>
    </g>
</svg>
