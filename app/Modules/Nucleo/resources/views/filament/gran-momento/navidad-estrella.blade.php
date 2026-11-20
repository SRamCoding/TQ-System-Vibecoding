{{-- Navidad · La estrella sobre los cerros (ficha op-gran-momento, plan 1.4d paso 7): cielo de noche, la estrella que nace y
     se queda, su estela hacia los cerros y una aldea a lo lejos con luces tibias. Sin destellos; quieta con «reducir». --}}
<svg viewBox="0 0 1600 900" preserveAspectRatio="xMidYMid slice" role="presentation">
    <style>
        .tq-gm-corre .tq-gm-es-estrella { transform-origin: 800px 240px; animation: tq-gm-es-nace 3.5s cubic-bezier(.2,.8,.3,1) 1s both; }
        .tq-gm-corre .tq-gm-es-estela { animation: tq-gm-es-aparece 4s ease-out 3.5s both; }
        .tq-gm-corre .tq-gm-es-luces { animation: tq-gm-es-aparece 3s ease-out 5s both; }
        .tq-gm-corre .tq-gm-es-astro { animation: tq-gm-es-titila 4s ease-in-out infinite alternate; }
        @keyframes tq-gm-es-nace { from { opacity: 0; transform: scale(.2) rotate(-20deg); } to { opacity: 1; transform: scale(1) rotate(0); } }
        @keyframes tq-gm-es-aparece { from { opacity: 0; } to { opacity: 1; } }
        @keyframes tq-gm-es-titila { from { opacity: .9; } to { opacity: .35; } }
        @media (prefers-reduced-motion: reduce) { .tq-gm-corre [class^="tq-gm-es-"] { animation: none !important; } }
        html.tq-sin-animaciones .tq-gm-corre [class^="tq-gm-es-"] { animation: none !important; }
    </style>
    <defs>
        <linearGradient id="tq-gm-es-cielo" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#050818"/><stop offset=".6" stop-color="#15204d"/><stop offset="1" stop-color="#2d2a55"/></linearGradient>
        <radialGradient id="tq-gm-es-brillo" cx=".5" cy=".5" r=".5"><stop offset="0" stop-color="#fffbe6"/><stop offset=".3" stop-color="#ffe9a8" stop-opacity=".45"/><stop offset="1" stop-color="#ffe9a8" stop-opacity="0"/></radialGradient>
        <linearGradient id="tq-gm-es-haz" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#fff3c4" stop-opacity=".5"/><stop offset="1" stop-color="#fff3c4" stop-opacity="0"/></linearGradient>
    </defs>
    <rect width="1600" height="900" fill="url(#tq-gm-es-cielo)"/>
    @foreach ([[180, 120], [320, 260], [470, 90], [610, 330], [1040, 110], [1180, 280], [1320, 150], [1460, 320], [240, 400], [1390, 420], [900, 70], [700, 170]] as [$x, $y])
        <circle class="tq-gm-es-astro" cx="{{ $x }}" cy="{{ $y }}" r="{{ 1.4 + ($x % 3) * 0.6 }}" fill="#e8eeff" style="animation-delay: {{ ($x % 7) * 0.4 }}s"/>
    @endforeach
    <polygon class="tq-gm-es-estela" points="790,250 810,250 1020,760 580,760" fill="url(#tq-gm-es-haz)"/>
    <g class="tq-gm-es-estrella">
        <circle cx="800" cy="240" r="120" fill="url(#tq-gm-es-brillo)"/>
        <path d="M800 170 L812 228 L870 240 L812 252 L800 310 L788 252 L730 240 L788 228 Z" fill="#fffbe6"/>
    </g>
    <path d="M0 720 Q220 620 420 690 T860 670 T1260 700 T1600 660 V900 H0 Z" fill="#101532"/>
    <path d="M0 790 Q300 720 640 770 T1300 760 T1600 750 V900 H0 Z" fill="#0a0d22"/>
    <g class="tq-gm-es-luces" fill="#ffcf73">
        @foreach ([[540, 735], [575, 742], [612, 738], [960, 728], [998, 734], [1032, 731]] as [$x, $y])<rect x="{{ $x }}" y="{{ $y }}" width="9" height="7" rx="1.5"/>@endforeach
    </g>
</svg>
