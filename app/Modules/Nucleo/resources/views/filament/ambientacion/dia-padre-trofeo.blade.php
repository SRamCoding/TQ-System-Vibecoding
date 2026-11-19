{{-- Día del Padre · Trofeo: sube el trofeo y salen chispas. --}}
<svg viewBox="0 0 64 64" aria-hidden="true">
    <style>
        .tq-corre .tq-pf2-trofeo { animation: tq-pf2-sube 0.7s cubic-bezier(0.3, 1.4, 0.5, 1) both; }
        .tq-corre .tq-pf2-chispa { animation: tq-pf2-chispa 0.5s 0.6s ease-out both; }
        @keyframes tq-pf2-sube { from { transform: translateY(18px); opacity: 0; } to { transform: none; opacity: 1; } }
        @keyframes tq-pf2-chispa { from { opacity: 0; } to { opacity: 1; } }
    </style>
    <g class="tq-pf2-trofeo">
        <path d="M20 8h24v12a12 12 0 0 1-24 0z" fill="#F2C14E"/>
        <path d="M20 12h-6a6 6 0 0 0 6 10M44 12h6a6 6 0 0 1-6 10" stroke="#F2C14E" stroke-width="3" fill="none"/>
        <path d="M29 32h6v10h-6z" fill="#D9A83A"/>
        <path d="M20 42h24v6H20zM16 48h32v8H16z" fill="#3A78B5"/>
    </g>
    <path class="tq-pf2-chispa" d="M10 6l2 4M54 6l-2 4M6 18h4M58 18h-4" stroke="#F2C14E" stroke-width="2.2" stroke-linecap="round"/>
</svg>
