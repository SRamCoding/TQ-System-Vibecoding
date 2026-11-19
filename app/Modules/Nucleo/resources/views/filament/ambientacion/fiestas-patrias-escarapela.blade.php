{{-- Fiestas Patrias · Escarapela: la escarapela rojiblanca gira y se fija. --}}
<svg viewBox="0 0 64 64" aria-hidden="true">
    <style>
        .tq-corre .tq-fe-roseta { transform-origin: center; animation: tq-fe-gira 1s cubic-bezier(0.3, 1.3, 0.5, 1) both; }
        .tq-corre .tq-fe-cintas { transform-origin: center top; animation: tq-fe-cae 0.5s 0.6s ease-out both; }
        @keyframes tq-fe-gira { from { transform: scale(0) rotate(-180deg); } to { transform: none; } }
        @keyframes tq-fe-cae { from { transform: scaleY(0); } to { transform: none; } }
    </style>
    <g class="tq-fe-cintas">
        <path d="M24 34l-8 26 7-4 5 6 6-26z" fill="#D91023"/>
        <path d="M40 34l8 26-7-4-5 6-6-26z" fill="#D91023"/>
    </g>
    <g class="tq-fe-roseta">
        @foreach (range(0, 345, 15) as $giro)
            <path d="M32 4l3 10h-6z" fill="{{ $loop->even ? '#D91023' : '#FFFFFF' }}" stroke="#E5E5E5" stroke-width="0.4" transform="rotate({{ $giro }} 32 26)"/>
        @endforeach
        <circle cx="32" cy="26" r="13" fill="#D91023"/>
        <circle cx="32" cy="26" r="8" fill="#FFFFFF"/>
        <circle cx="32" cy="26" r="4" fill="#D91023"/>
    </g>
</svg>
