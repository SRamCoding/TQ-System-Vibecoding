{{-- Invierno · Copo: el copo de nieve de las alturas gira y se forma. --}}
<svg viewBox="0 0 64 64" aria-hidden="true">
    <style>
        .tq-corre .tq-ix-copo { transform-origin: center; animation: tq-ix-gira 1.2s ease-out both; }
        @keyframes tq-ix-gira { from { transform: rotate(-180deg) scale(0); } to { transform: none; } }
    </style>
    <g class="tq-ix-copo">
        @foreach (range(0, 300, 60) as $giro)
            <g transform="rotate({{ $giro }} 32 32)">
                <path d="M32 32V8M32 16l-5-5M32 16l5-5M32 24l-4-3M32 24l4-3" stroke="#6FB7C9" stroke-width="2.6" stroke-linecap="round" fill="none"/>
            </g>
        @endforeach
        <circle cx="32" cy="32" r="3.5" fill="#A6DAE5"/>
    </g>
</svg>
