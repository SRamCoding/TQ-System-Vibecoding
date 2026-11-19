{{-- Navidad · Estrella y luces: la estrella brilla y las luces se encienden una tras otra. --}}
<svg viewBox="0 0 64 64" aria-hidden="true">
    <style>
        .tq-corre .tq-ne-estrella { transform-origin: center; animation: tq-ne-brilla 0.9s cubic-bezier(0.3, 1.5, 0.5, 1) both; }
        .tq-corre .tq-ne-luz { animation: tq-ne-enciende 0.35s ease-out both; }
        .tq-corre .tq-ne-l2 { animation-delay: 0.5s; }
        .tq-corre .tq-ne-l3 { animation-delay: 0.7s; }
        .tq-corre .tq-ne-l4 { animation-delay: 0.9s; }
        .tq-corre .tq-ne-l5 { animation-delay: 1.1s; }
        .tq-corre .tq-ne-l1 { animation-delay: 0.3s; }
        @keyframes tq-ne-brilla { from { transform: scale(0) rotate(-144deg); } to { transform: scale(1) rotate(0deg); } }
        @keyframes tq-ne-enciende { from { opacity: 0.15; transform: scale(0.6); } 60% { opacity: 1; transform: scale(1.25); } to { opacity: 1; transform: scale(1); } }
    </style>
    <path class="tq-ne-estrella" d="M32 4l4.7 9.6 10.6 1.5-7.7 7.4 1.8 10.5L32 28l-9.4 5 1.8-10.5-7.7-7.4 10.6-1.5z" fill="#F2C14E"/>
    <path d="M4 40c9 10 19 13 28 13s19-3 28-13" stroke="#2E7D4F" stroke-width="2" fill="none" stroke-linecap="round"/>
    <g>
        <ellipse class="tq-ne-luz tq-ne-l1" cx="10" cy="47" rx="3.2" ry="4.2" fill="#C0392B" style="transform-origin: center"/>
        <ellipse class="tq-ne-luz tq-ne-l2" cx="21" cy="52.5" rx="3.2" ry="4.2" fill="#F2C14E" style="transform-origin: center"/>
        <ellipse class="tq-ne-luz tq-ne-l3" cx="32" cy="55" rx="3.2" ry="4.2" fill="#2E7D4F" style="transform-origin: center"/>
        <ellipse class="tq-ne-luz tq-ne-l4" cx="43" cy="52.5" rx="3.2" ry="4.2" fill="#C0392B" style="transform-origin: center"/>
        <ellipse class="tq-ne-luz tq-ne-l5" cx="54" cy="47" rx="3.2" ry="4.2" fill="#A6DAE5" style="transform-origin: center"/>
    </g>
</svg>
