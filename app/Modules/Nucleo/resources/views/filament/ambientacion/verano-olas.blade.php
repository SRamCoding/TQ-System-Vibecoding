{{-- Verano · Olas: las olas llegan bajo el sol. --}}
<svg viewBox="0 0 64 64" aria-hidden="true">
    <style>
        .tq-corre .tq-vo-ola { animation: tq-vo-llega 0.9s ease-out both; }
        .tq-corre .tq-vo-2 { animation-delay: 0.2s; }
        .tq-corre .tq-vo-sol { animation: tq-vo-sol 0.8s ease-out both; }
        @keyframes tq-vo-llega { from { transform: translateX(-18px); opacity: 0; } to { transform: none; opacity: 1; } }
        @keyframes tq-vo-sol { from { transform: translateY(10px); opacity: 0; } to { transform: none; opacity: 1; } }
    </style>
    <circle class="tq-vo-sol" cx="44" cy="18" r="9" fill="#F6C343"/>
    <path class="tq-vo-ola tq-vo-1" d="M2 40c6-6 12-6 18 0s12 6 18 0 12-6 18 0 6 4 6 4v20H2z" fill="#4FA8C8"/>
    <path class="tq-vo-ola tq-vo-2" d="M2 50c6-5 12-5 18 0s12 5 18 0 12-5 18 0 6 3 6 3v11H2z" fill="#A6DAE5"/>
</svg>
