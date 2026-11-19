{{-- Invierno · Nevados: los nevados andinos aparecen y cae nieve fina. --}}
<svg viewBox="0 0 64 64" aria-hidden="true">
    <style>
        .tq-corre .tq-in-cerro { transform-origin: center bottom; animation: tq-in-sube 0.7s ease-out both; }
        .tq-corre .tq-in-c2 { animation-delay: 0.2s; }
        .tq-corre .tq-in-nieve { animation: tq-in-cae 0.8s 0.6s ease-in both; }
        @keyframes tq-in-sube { from { transform: scaleY(0); } to { transform: none; } }
        @keyframes tq-in-cae { from { transform: translateY(-8px); opacity: 0; } to { transform: none; opacity: 1; } }
    </style>
    <g class="tq-in-cerro tq-in-c1">
        <path d="M0 60L22 18l22 42z" fill="#7F8C9D"/>
        <path d="M22 18l-7 13 4-2 3 3 3-3 4 2z" fill="#FFFFFF"/>
    </g>
    <g class="tq-in-cerro tq-in-c2">
        <path d="M24 60l20-34 20 34z" fill="#A6B4C8"/>
        <path d="M44 26l-6 10 3-1 3 3 3-3 3 1z" fill="#FFFFFF"/>
    </g>
    @foreach ([[10, 10], [36, 6], [52, 14], [28, 20], [58, 30]] as [$x, $y])
        <circle class="tq-in-nieve" cx="{{ $x }}" cy="{{ $y }}" r="1.6" fill="#C9D3E0"/>
    @endforeach
</svg>
