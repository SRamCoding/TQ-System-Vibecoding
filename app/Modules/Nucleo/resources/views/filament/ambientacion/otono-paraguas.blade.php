{{-- Otoño · Paraguas: se abre el paraguas bajo la llovizna. --}}
<svg viewBox="0 0 64 64" aria-hidden="true">
    <style>
        .tq-corre .tq-op-tela { transform-origin: center bottom; animation: tq-op-abre 0.7s cubic-bezier(0.3, 1.4, 0.5, 1) both; }
        .tq-corre .tq-op-gota { animation: tq-op-cae 0.6s ease-in both; }
        .tq-corre .tq-op-g2 { animation-delay: 0.2s; }
        .tq-corre .tq-op-g3 { animation-delay: 0.4s; }
        @keyframes tq-op-abre { from { transform: scaleX(0.1); } to { transform: none; } }
        @keyframes tq-op-cae { from { transform: translateY(-10px); opacity: 0; } to { transform: none; opacity: 1; } }
    </style>
    <path class="tq-op-gota tq-op-g1" d="M10 8v6" stroke="#6FB7C9" stroke-width="2" stroke-linecap="round"/>
    <path class="tq-op-gota tq-op-g2" d="M54 6v6" stroke="#6FB7C9" stroke-width="2" stroke-linecap="round"/>
    <path class="tq-op-gota tq-op-g3" d="M30 2v5" stroke="#6FB7C9" stroke-width="2" stroke-linecap="round"/>
    <g class="tq-op-tela">
        <path d="M8 34a24 22 0 0 1 48 0c-4-3-8-3-12 0-4-3-8-3-12 0-4-3-8-3-12 0-4-3-8-3-12 0z" fill="#D9861F"/>
        <path d="M32 12v22" stroke="#B5701A" stroke-width="1.5"/>
    </g>
    <path d="M32 34v20a4 4 0 0 1-8 0" stroke="#3D4772" stroke-width="2.6" fill="none" stroke-linecap="round"/>
</svg>
