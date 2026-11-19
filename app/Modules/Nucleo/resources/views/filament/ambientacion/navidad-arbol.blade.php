{{-- Navidad · Árbol geométrico: el árbol sube por pisos, aparecen los adornos y al final la estrella. --}}
<svg viewBox="0 0 64 64" aria-hidden="true">
    <style>
        .tq-corre .tq-na-piso { transform-origin: center bottom; animation: tq-na-sube 0.45s ease-out both; }
        .tq-corre .tq-na-p2 { animation-delay: 0.25s; }
        .tq-corre .tq-na-p3 { animation-delay: 0.5s; }
        .tq-corre .tq-na-adorno { transform-origin: center; animation: tq-na-aparece 0.35s cubic-bezier(0.3, 1.6, 0.5, 1) both; }
        .tq-corre .tq-na-a1 { animation-delay: 0.8s; }
        .tq-corre .tq-na-a2 { animation-delay: 0.9s; }
        .tq-corre .tq-na-a3 { animation-delay: 1s; }
        .tq-corre .tq-na-a4 { animation-delay: 1.1s; }
        .tq-corre .tq-na-estrella { transform-origin: center; animation: tq-na-aparece 0.5s 1.25s cubic-bezier(0.3, 1.6, 0.5, 1) both; }
        @keyframes tq-na-sube { from { transform: scaleY(0); opacity: 0; } to { transform: scaleY(1); opacity: 1; } }
        @keyframes tq-na-aparece { from { transform: scale(0); } to { transform: scale(1); } }
    </style>
    <rect x="29" y="52" width="6" height="8" rx="1" fill="#8B5E3C"/>
    <path class="tq-na-piso tq-na-p1" d="M32 30L50 53H14z" fill="#2E7D4F"/>
    <path class="tq-na-piso tq-na-p2" d="M32 20L46 40H18z" fill="#3F9B63"/>
    <path class="tq-na-piso tq-na-p3" d="M32 11L42 27H22z" fill="#5BB37A"/>
    <circle class="tq-na-adorno tq-na-a1" cx="24" cy="47" r="2.6" fill="#C0392B"/>
    <circle class="tq-na-adorno tq-na-a2" cx="39" cy="44" r="2.6" fill="#F2C14E"/>
    <circle class="tq-na-adorno tq-na-a3" cx="27" cy="34" r="2.3" fill="#A6DAE5"/>
    <circle class="tq-na-adorno tq-na-a4" cx="36" cy="24" r="2.1" fill="#C0392B"/>
    <path class="tq-na-estrella" d="M32 2l2.6 5.3 5.9.9-4.3 4.1 1 5.8L32 15.3l-5.2 2.8 1-5.8-4.3-4.1 5.9-.9z" fill="#F2C14E"/>
</svg>
