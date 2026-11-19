{{-- Aniversario · Cohete: despega y deja su estela, por un año más creciendo. --}}
<svg viewBox="0 0 64 64" aria-hidden="true">
    <style>
        .tq-corre .tq-ak-cohete { animation: tq-ak-despega 1.1s cubic-bezier(0.3, 1.2, 0.5, 1) both; }
        .tq-corre .tq-ak-fuego { transform-origin: center top; animation: tq-ak-fuego 0.15s linear 8 alternate both; }
        @keyframes tq-ak-despega { from { transform: translate(-14px, 22px); } to { transform: none; } }
        @keyframes tq-ak-fuego { from { transform: scaleY(0.6); } to { transform: scaleY(1.1); } }
    </style>
    <g transform="rotate(35 32 32)">
    <g class="tq-ak-cohete">
        <path class="tq-ak-fuego" d="M27 46h10l-5 14z" fill="#F2C14E"/>
        <path d="M32 4c8 6 10 18 8 34H24c-2-16 0-28 8-34z" fill="#F6F0F0" stroke="#3D4772" stroke-width="2"/>
        <circle cx="32" cy="22" r="4.5" fill="#A6DAE5" stroke="#3D4772" stroke-width="2"/>
        <path d="M24 30l-7 10 7 2zM40 30l7 10-7 2z" fill="#CF92BB"/>
        <path d="M26 38h12v8H26z" fill="#3D4772"/>
    </g>
    </g>
</svg>
