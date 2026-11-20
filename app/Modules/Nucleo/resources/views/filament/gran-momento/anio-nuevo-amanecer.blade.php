{{-- Año Nuevo · El primer amanecer (ficha op-gran-momento): la noche que se aclara, el sol que asoma sobre el mar y su
     camino de luz en el agua. Sin destellos; quieta con «reducir». --}}
<svg viewBox="0 0 1600 900" preserveAspectRatio="xMidYMid slice" role="presentation">
    <style>
        .tq-gm-corre .tq-gm-am-dia { animation: tq-gm-am-aparece 6s ease-in-out .5s both; }
        .tq-gm-corre .tq-gm-am-sol { animation: tq-gm-am-sube 7s cubic-bezier(.3,.7,.3,1) 1s both; }
        .tq-gm-corre .tq-gm-am-camino { animation: tq-gm-am-aparece 4s ease-out 4s both; }
        @keyframes tq-gm-am-aparece { from { opacity: 0; } to { opacity: 1; } }
        @keyframes tq-gm-am-sube { from { transform: translateY(160px); } to { transform: translateY(0); } }
        @media (prefers-reduced-motion: reduce) { .tq-gm-corre [class^="tq-gm-am-"] { animation: none !important; } }
        html.tq-sin-animaciones .tq-gm-corre [class^="tq-gm-am-"] { animation: none !important; }
    </style>
    <defs>
        <linearGradient id="tq-gm-am-noche" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#0b1030"/><stop offset="1" stop-color="#3a2f5a"/></linearGradient>
        <linearGradient id="tq-gm-am-alba" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#5b7bd5"/><stop offset=".55" stop-color="#f7a072"/><stop offset="1" stop-color="#ffd9a0"/></linearGradient>
        <linearGradient id="tq-gm-am-mar" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#3b4a7a"/><stop offset="1" stop-color="#141a33"/></linearGradient>
        <clipPath id="tq-gm-am-cielo"><rect width="1600" height="620"/></clipPath>
    </defs>
    <rect width="1600" height="620" fill="url(#tq-gm-am-noche)"/>
    <rect class="tq-gm-am-dia" width="1600" height="620" fill="url(#tq-gm-am-alba)"/>
    <g clip-path="url(#tq-gm-am-cielo)"><g class="tq-gm-am-sol"><circle cx="800" cy="620" r="110" fill="#ffe08a"/><circle cx="800" cy="620" r="170" fill="#ffe08a" opacity=".25"/></g></g>
    <rect y="620" width="1600" height="280" fill="url(#tq-gm-am-mar)"/>
    <g class="tq-gm-am-camino" fill="#ffd98a" opacity=".8">
        @foreach ([[760, 640, 80], [740, 670, 120], [770, 700, 60], [720, 735, 160], [750, 775, 100], [700, 820, 200]] as [$x, $y, $w])<rect x="{{ $x }}" y="{{ $y }}" width="{{ $w }}" height="5" rx="2.5"/>@endforeach
    </g>
</svg>
