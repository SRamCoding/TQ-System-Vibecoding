{{-- Cumpleaños · Gorro de fiesta: cae el gorro y sopla el silbato. --}}
<svg viewBox="0 0 64 64" aria-hidden="true">
    <style>
        .tq-corre .tq-cz-gorro { transform-origin: center bottom; animation: tq-cz-cae 0.8s cubic-bezier(0.3, 1.4, 0.5, 1) both; }
        .tq-corre .tq-cz-pito { transform-origin: left center; animation: tq-cz-sopla 0.5s 0.7s ease-out both; }
        @keyframes tq-cz-cae { from { transform: translateY(-24px) rotate(-20deg); opacity: 0; } to { transform: none; opacity: 1; } }
        @keyframes tq-cz-sopla { from { transform: scaleX(0); } to { transform: scaleX(1); } }
    </style>
    <g class="tq-cz-gorro">
        <path d="M32 6L16 44h32z" fill="#B9A7E8"/>
        <path d="M24 26l4 4M30 18l4 4M34 32l4 4M22 38l4 4M38 22l3 3" stroke="#F2C14E" stroke-width="2.4" stroke-linecap="round"/>
        <circle cx="32" cy="6" r="4" fill="#E06C9F"/>
        <path d="M14 44h36" stroke="#E06C9F" stroke-width="4" stroke-linecap="round"/>
    </g>
    <g class="tq-cz-pito">
        <path d="M30 54h14" stroke="#F2C14E" stroke-width="3" stroke-linecap="round"/>
        <path d="M44 54c6 0 10-2 10-5s-4-4-6-2" stroke="#E06C9F" stroke-width="3" fill="none" stroke-linecap="round"/>
    </g>
</svg>
