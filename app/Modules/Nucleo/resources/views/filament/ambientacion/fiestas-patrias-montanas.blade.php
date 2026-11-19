{{-- Fiestas Patrias · Andes: los cerros aparecen y sale el sol del Perú. --}}
<svg viewBox="0 0 64 64" aria-hidden="true">
    <style>
        .tq-corre .tq-fm-cerro { transform-origin: center bottom; animation: tq-fm-sube 0.6s ease-out both; }
        .tq-corre .tq-fm-c2 { animation-delay: 0.2s; }
        .tq-corre .tq-fm-sol { animation: tq-fm-sol 0.8s 0.5s ease-out both; }
        @keyframes tq-fm-sube { from { transform: scaleY(0); } to { transform: none; } }
        @keyframes tq-fm-sol { from { transform: translateY(12px); opacity: 0; } to { transform: none; opacity: 1; } }
    </style>
    <circle class="tq-fm-sol" cx="44" cy="20" r="8" fill="#F2C14E"/>
    <path class="tq-fm-cerro tq-fm-c1" d="M0 58l18-30 10 14 8-12 28 28z" fill="#5E9E57"/>
    <path class="tq-fm-cerro tq-fm-c2" d="M14 58l14-20 8 10 6-8 16 18z" fill="#8CC084"/>
    <path d="M14 34l4-6 4 6-2-1-2 2-2-2z" fill="#F6F0F0"/>
    <path d="M0 58h64" stroke="#D91023" stroke-width="4"/>
</svg>
