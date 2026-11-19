{{-- Semana Santa · Sepulcro vacío: la piedra rueda y sale la luz. --}}
<svg viewBox="0 0 64 64" aria-hidden="true">
    <style>
        .tq-corre .tq-ss-piedra { transform-origin: center; animation: tq-ss-rueda 1s ease-in-out both; }
        .tq-corre .tq-ss-luz { animation: tq-ss-luz 0.7s 0.8s ease-out both; }
        @keyframes tq-ss-rueda { from { transform: translateX(-16px) rotate(-200deg); } to { transform: none; } }
        @keyframes tq-ss-luz { from { opacity: 0; } to { opacity: 1; } }
    </style>
    <path d="M4 56h56" stroke="#8CC084" stroke-width="3" stroke-linecap="round"/>
    <path d="M8 56V36c0-14 10-22 22-22s22 8 22 22v20z" fill="#C9C2B8"/>
    <path d="M18 56V42a9 9 0 0 1 18 0v14z" fill="#3D4772"/>
    <path class="tq-ss-luz" d="M27 34l-3-8M27 34l3-9M27 34l7-5" stroke="#F2C14E" stroke-width="2" stroke-linecap="round"/>
    <path class="tq-ss-luz" d="M21 56V45a6 6 0 0 1 12 0v11z" fill="#F6D365" opacity="0.85"/>
    <circle class="tq-ss-piedra" cx="47" cy="46" r="10" fill="#9C9388"/>
</svg>
