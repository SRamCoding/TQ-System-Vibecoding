{{-- Verano · Sol: el sol sale y sus rayos giran. --}}
<svg viewBox="0 0 64 64" aria-hidden="true">
    <style>
        .tq-corre .tq-vs-rayos { transform-origin: center; animation: tq-vs-gira 1.4s ease-out both; }
        .tq-corre .tq-vs-sol { transform-origin: center; animation: tq-vs-sale 0.6s cubic-bezier(0.3, 1.4, 0.5, 1) both; }
        @keyframes tq-vs-gira { from { transform: rotate(-90deg) scale(0.4); opacity: 0; } to { transform: none; opacity: 1; } }
        @keyframes tq-vs-sale { from { transform: scale(0); } to { transform: scale(1); } }
    </style>
    <g class="tq-vs-rayos">
        @foreach (range(0, 315, 45) as $giro)
            <path d="M32 4v8" stroke="#F2A541" stroke-width="3.2" stroke-linecap="round" transform="rotate({{ $giro }} 32 32)"/>
        @endforeach
    </g>
    <circle class="tq-vs-sol" cx="32" cy="32" r="14" fill="#F6C343"/>
    <path d="M26 34c2 3 10 3 12 0" stroke="#D9861F" stroke-width="2" fill="none" stroke-linecap="round"/>
    <circle cx="27" cy="29" r="1.6" fill="#D9861F"/><circle cx="37" cy="29" r="1.6" fill="#D9861F"/>
</svg>
