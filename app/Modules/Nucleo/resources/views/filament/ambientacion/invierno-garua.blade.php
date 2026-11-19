{{-- Invierno · Garúa: las nubes se juntan y cae la garúa fina. --}}
<svg viewBox="0 0 64 64" aria-hidden="true">
    <style>
        .tq-corre .tq-ig-nube { animation: tq-ig-llega 0.8s ease-out both; }
        .tq-corre .tq-ig-n2 { animation-delay: 0.15s; animation-name: tq-ig-llega-d; }
        .tq-corre .tq-ig-gota { animation: tq-ig-cae 0.5s 0.7s ease-in both; }
        @keyframes tq-ig-llega { from { transform: translateX(-14px); opacity: 0; } to { transform: none; opacity: 1; } }
        @keyframes tq-ig-llega-d { from { transform: translateX(14px); opacity: 0; } to { transform: none; opacity: 1; } }
        @keyframes tq-ig-cae { from { transform: translateY(-6px); opacity: 0; } to { transform: none; opacity: 1; } }
    </style>
    <path class="tq-ig-nube tq-ig-n1" d="M8 30a8 8 0 0 1 8-8 11 11 0 0 1 20 1 7 7 0 0 1 7 7z" fill="#A6B4C8"/>
    <path class="tq-ig-nube tq-ig-n2" d="M22 36a8 8 0 0 1 8-8 11 11 0 0 1 20 1 7 7 0 0 1 7 7z" fill="#C9D3E0"/>
    @foreach ([[16, 42], [26, 48], [36, 44], [46, 50], [21, 54], [41, 56]] as [$x, $y])
        <path class="tq-ig-gota" d="M{{ $x }} {{ $y }}l-1 3" stroke="#6FB7C9" stroke-width="1.6" stroke-linecap="round"/>
    @endforeach
</svg>
