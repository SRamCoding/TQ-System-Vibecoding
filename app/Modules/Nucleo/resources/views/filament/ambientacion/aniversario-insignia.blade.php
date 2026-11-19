{{-- Aniversario · Insignia: la insignia gira y aparece la estrella del centro. --}}
<svg viewBox="0 0 64 64" aria-hidden="true">
    <style>
        .tq-corre .tq-ai-sello { transform-origin: center; animation: tq-ai-gira 1s cubic-bezier(0.3, 1.3, 0.5, 1) both; }
        .tq-corre .tq-ai-cintas { transform-origin: center top; animation: tq-ai-cae 0.5s 0.6s ease-out both; }
        @keyframes tq-ai-gira { from { transform: scale(0) rotate(-200deg); } to { transform: none; } }
        @keyframes tq-ai-cae { from { transform: scaleY(0); } to { transform: none; } }
    </style>
    <g class="tq-ai-cintas">
        <path d="M24 38l-6 22 7-3 4 6 4-22zM40 38l6 22-7-3-4 6-4-22z" fill="#CF92BB"/>
    </g>
    <g class="tq-ai-sello">
        @foreach (range(0, 330, 30) as $giro)
            <circle cx="32" cy="8" r="5" fill="#3D4772" transform="rotate({{ $giro }} 32 26)"/>
        @endforeach
        <circle cx="32" cy="26" r="17" fill="#3D4772"/>
        <circle cx="32" cy="26" r="12" fill="none" stroke="#A6DAE5" stroke-width="2"/>
        <path d="M32 18l2.4 4.9 5.4.8-3.9 3.8.9 5.4-4.8-2.5-4.8 2.5.9-5.4-3.9-3.8 5.4-.8z" fill="#F2C14E"/>
    </g>
</svg>
