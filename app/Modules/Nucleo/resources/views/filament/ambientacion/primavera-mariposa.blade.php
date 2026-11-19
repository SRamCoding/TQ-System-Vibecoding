{{-- Primavera · Mariposa: entra volando y aletea. --}}
<svg viewBox="0 0 64 64" aria-hidden="true">
    <style>
        .tq-corre .tq-pm-vuelo { transform-origin: center; animation: tq-pm-entra 1.3s ease-out both; }
        .tq-corre .tq-pm-ala-i { transform-origin: right center; animation: tq-pm-aletea 0.3s ease-in-out 6 alternate both; }
        .tq-corre .tq-pm-ala-d { transform-origin: left center; animation: tq-pm-aletea 0.3s ease-in-out 6 alternate both; }
        @keyframes tq-pm-entra { from { transform: translate(-20px, 18px) rotate(-18deg); opacity: 0; } to { transform: none; opacity: 1; } }
        @keyframes tq-pm-aletea { from { transform: scaleX(1); } to { transform: scaleX(0.35); } }
    </style>
    <g class="tq-pm-vuelo">
        <g class="tq-pm-ala-i">
            <path d="M31 30C24 16 10 14 9 22c-1 8 12 10 22 8z" fill="#B9A7E8"/>
            <path d="M31 31c-8 2-16 8-12 14 4 5 11-3 12-14z" fill="#F2C14E"/>
        </g>
        <g class="tq-pm-ala-d">
            <g transform="matrix(-1 0 0 1 64 0)">
                <path d="M31 30C24 16 10 14 9 22c-1 8 12 10 22 8z" fill="#B9A7E8"/>
                <path d="M31 31c-8 2-16 8-12 14 4 5 11-3 12-14z" fill="#F2C14E"/>
            </g>
        </g>
        <rect x="30.5" y="22" width="3" height="19" rx="1.5" fill="#3D4772"/>
        <path d="M32 23l-4-7M32 23l4-7" stroke="#3D4772" stroke-width="1.5" stroke-linecap="round" fill="none"/>
    </g>
</svg>
