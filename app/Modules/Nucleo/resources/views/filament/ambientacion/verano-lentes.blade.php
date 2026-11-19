{{-- Verano · Lentes de sol: caen los lentes y brilla el reflejo. --}}
<svg viewBox="0 0 64 64" aria-hidden="true">
    <style>
        .tq-corre .tq-vl-lentes { animation: tq-vl-cae 0.7s cubic-bezier(0.3, 1.5, 0.5, 1) both; }
        .tq-corre .tq-vl-brillo { animation: tq-vl-brillo 0.5s 0.7s ease-out both; }
        @keyframes tq-vl-cae { from { transform: translateY(-20px) rotate(-12deg); opacity: 0; } to { transform: none; opacity: 1; } }
        @keyframes tq-vl-brillo { from { opacity: 0; transform: translateX(-4px); } to { opacity: 1; transform: none; } }
    </style>
    <circle cx="32" cy="32" r="28" fill="#F6C343"/>
    <g class="tq-vl-lentes">
        <path d="M8 26h48" stroke="#3D4772" stroke-width="3" stroke-linecap="round"/>
        <path d="M12 26h16v6a8 8 0 0 1-16 0zM36 26h16v6a8 8 0 0 1-16 0z" fill="#3D4772"/>
    </g>
    <path class="tq-vl-brillo" d="M16 30l4-3M40 30l4-3" stroke="#A6DAE5" stroke-width="2" stroke-linecap="round"/>
    <path d="M24 44c4 4 12 4 16 0" stroke="#D9861F" stroke-width="2.4" fill="none" stroke-linecap="round"/>
</svg>
