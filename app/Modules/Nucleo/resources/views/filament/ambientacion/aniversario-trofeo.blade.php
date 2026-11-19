{{-- Aniversario · Copa: la copa de Techquk sube con una estrella. --}}
<svg viewBox="0 0 64 64" aria-hidden="true">
    <style>
        .tq-corre .tq-at-copa { animation: tq-at-sube 0.8s cubic-bezier(0.3, 1.4, 0.5, 1) both; }
        .tq-corre .tq-at-estrella { transform-origin: center; animation: tq-at-brilla 0.6s 0.7s cubic-bezier(0.3, 1.6, 0.5, 1) both; }
        @keyframes tq-at-sube { from { transform: translateY(18px); opacity: 0; } to { transform: none; opacity: 1; } }
        @keyframes tq-at-brilla { from { transform: scale(0) rotate(-90deg); } to { transform: none; } }
    </style>
    <g class="tq-at-copa">
        <path d="M18 14h28v10a14 14 0 0 1-28 0z" fill="#A6DAE5"/>
        <path d="M18 18h-6a6 6 0 0 0 7 10M46 18h6a6 6 0 0 1-7 10" stroke="#A6DAE5" stroke-width="3" fill="none"/>
        <path d="M29 38h6v8h-6z" fill="#7FC4D4"/>
        <path d="M18 46h28v10H18z" fill="#3D4772"/>
        <path d="M24 51h16" stroke="#A6DAE5" stroke-width="2" stroke-linecap="round"/>
    </g>
    <path class="tq-at-estrella" d="M32 16l2.4 4.9 5.4.8-3.9 3.8.9 5.4-4.8-2.5-4.8 2.5.9-5.4-3.9-3.8 5.4-.8z" fill="#F6F0F0"/>
</svg>
