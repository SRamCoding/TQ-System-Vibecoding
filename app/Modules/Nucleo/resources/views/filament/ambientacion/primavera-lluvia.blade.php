{{-- Primavera · Lluvia: caen tres gotas y brota una flor. --}}
<svg viewBox="0 0 64 64" aria-hidden="true">
    <style>
        .tq-corre .tq-pl-gota { animation: tq-pl-cae 0.6s ease-in both; }
        .tq-corre .tq-pl-g2 { animation-delay: 0.2s; }
        .tq-corre .tq-pl-g3 { animation-delay: 0.4s; }
        .tq-corre .tq-pl-flor { transform-origin: center bottom; animation: tq-pl-brota 0.6s 0.8s cubic-bezier(0.3, 1.4, 0.5, 1) both; }
        @keyframes tq-pl-cae { from { transform: translateY(-14px); opacity: 0; } to { transform: none; opacity: 1; } }
        @keyframes tq-pl-brota { from { transform: scale(0); } to { transform: scale(1); } }
    </style>
    <path d="M14 20a8 8 0 0 1 8-8 10 10 0 0 1 19 2 7 7 0 0 1 9 7H14z" fill="#A6DAE5"/>
    <path class="tq-pl-gota tq-pl-g1" d="M22 28l2 4a2.2 2.2 0 1 1-4 0z" fill="#6FB7C9"/>
    <path class="tq-pl-gota tq-pl-g2" d="M34 26l2 4a2.2 2.2 0 1 1-4 0z" fill="#6FB7C9"/>
    <path class="tq-pl-gota tq-pl-g3" d="M44 30l2 4a2.2 2.2 0 1 1-4 0z" fill="#6FB7C9"/>
    <path d="M10 58h44" stroke="#B08968" stroke-width="3" stroke-linecap="round"/>
    <g class="tq-pl-flor">
        <path d="M32 57V44" stroke="#5E9E57" stroke-width="2.5" stroke-linecap="round"/>
        <path d="M32 52c-5 0-7-3-8-6 4 0 7 2 8 6z" fill="#8CC084"/>
        <circle cx="28.5" cy="41" r="3.2" fill="#F2A7C3"/><circle cx="35.5" cy="41" r="3.2" fill="#F2A7C3"/>
        <circle cx="32" cy="37.5" r="3.2" fill="#F2A7C3"/><circle cx="32" cy="41" r="2.2" fill="#F6D365"/>
    </g>
</svg>
