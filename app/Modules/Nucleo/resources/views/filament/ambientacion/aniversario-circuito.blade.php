{{-- Aniversario · Circuito: las pistas se encienden hasta el chip, por lo que hace Techquk. --}}
<svg viewBox="0 0 64 64" aria-hidden="true">
    <style>
        .tq-corre .tq-ac-pista { stroke-dasharray: 26; animation: tq-ac-corre 0.8s ease-out both; }
        .tq-corre .tq-ac-p2 { animation-delay: 0.15s; }
        .tq-corre .tq-ac-p3 { animation-delay: 0.3s; }
        .tq-corre .tq-ac-p4 { animation-delay: 0.45s; }
        .tq-corre .tq-ac-luz { animation: tq-ac-luz 0.5s 0.9s ease-out both; }
        @keyframes tq-ac-corre { from { stroke-dashoffset: 26; } to { stroke-dashoffset: 0; } }
        @keyframes tq-ac-luz { from { opacity: 0.2; } to { opacity: 1; } }
    </style>
    <path class="tq-ac-pista tq-ac-p1" d="M4 14h14l6 6" stroke="#A6DAE5" stroke-width="2.4" fill="none" stroke-linecap="round"/>
    <path class="tq-ac-pista tq-ac-p2" d="M60 14H46l-6 6" stroke="#A6DAE5" stroke-width="2.4" fill="none" stroke-linecap="round"/>
    <path class="tq-ac-pista tq-ac-p3" d="M4 50h14l6-6" stroke="#CF92BB" stroke-width="2.4" fill="none" stroke-linecap="round"/>
    <path class="tq-ac-pista tq-ac-p4" d="M60 50H46l-6-6" stroke="#CF92BB" stroke-width="2.4" fill="none" stroke-linecap="round"/>
    @foreach ([[4, 14], [60, 14], [4, 50], [60, 50]] as [$x, $y])
        <circle cx="{{ $x }}" cy="{{ $y }}" r="2.6" fill="#3D4772"/>
    @endforeach
    <rect x="22" y="18" width="20" height="28" rx="3" fill="#3D4772"/>
    @foreach ([22, 28, 34, 40] as $y)
        <path d="M18 {{ $y }}h4M42 {{ $y }}h4" stroke="#3D4772" stroke-width="2"/>
    @endforeach
    <circle class="tq-ac-luz" cx="32" cy="32" r="5" fill="#F2C14E"/>
</svg>
