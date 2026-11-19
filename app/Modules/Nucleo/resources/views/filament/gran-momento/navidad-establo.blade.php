{{--
    Navidad · El nacimiento, en silueta (plan 1.4d, D7): el establo a contraluz, la estrella de Belén y figuras sin rostro.
    La noche se aclara hacia el horizonte; la estrella aparece y la luz del pesebre crece hasta recortar a María y José.
    Clases `tq-gm-ne-…`; animaciones solo dentro de `.tq-gm-corre` (el marco), sin destellos: el brillo de las estrellas
    cambia despacio (cada 3 a 5 segundos).
--}}
{{-- Lo importante (de la estrella al suelo del establo) vive entre y = 165 y y = 750: centrada, la escena se ve entera
     tanto en una franja ancha de escritorio como en un celular vertical. --}}
<svg viewBox="0 0 1600 900" preserveAspectRatio="xMidYMid slice" role="presentation">
    <style>
        .tq-gm-corre .tq-gm-ne-cielo { animation: tq-gm-ne-aclara 4s ease-out both; }
        .tq-gm-corre .tq-gm-ne-astro { animation: tq-gm-ne-titila 4s ease-in-out 1s 6 alternate both; }
        .tq-gm-corre .tq-gm-ne-astro:nth-child(2n) { animation-duration: 5s; animation-delay: 1.8s; }
        .tq-gm-corre .tq-gm-ne-astro:nth-child(3n) { animation-duration: 3.4s; animation-delay: 2.4s; }
        .tq-gm-corre .tq-gm-ne-estrella { transform-origin: 800px 175px; animation: tq-gm-ne-nace 3.2s cubic-bezier(0.2, 0.8, 0.3, 1) 2s both; }
        .tq-gm-corre .tq-gm-ne-haz { animation: tq-gm-ne-aparece 4s ease-out 4.5s both; }
        .tq-gm-corre .tq-gm-ne-resplandor { animation: tq-gm-ne-aparece 4s ease-out 5s both; }
        .tq-gm-corre .tq-gm-ne-luz { animation: tq-gm-ne-aparece 3s ease-out 6.5s both; }
        .tq-gm-corre .tq-gm-ne-figuras { animation: tq-gm-ne-aparece 3s ease-out 6s both; }
        @keyframes tq-gm-ne-aclara { from { opacity: 0.35; } to { opacity: 1; } }
        @keyframes tq-gm-ne-titila { from { opacity: 0.9; } to { opacity: 0.35; } }
        @keyframes tq-gm-ne-nace { from { opacity: 0; transform: scale(0.3); } to { opacity: 1; transform: scale(1); } }
        @keyframes tq-gm-ne-aparece { from { opacity: 0; } to { opacity: 1; } }
        @media (prefers-reduced-motion: reduce) {
            .tq-gm-corre [class^="tq-gm-ne-"] { animation: none !important; }
        }
        html.tq-sin-animaciones .tq-gm-corre [class^="tq-gm-ne-"] { animation: none !important; }
    </style>
    <defs>
        <linearGradient id="tq-gm-ne-noche" x1="0" y1="0" x2="0" y2="1">
            <stop offset="0" stop-color="#060a1c"/>
            <stop offset="0.5" stop-color="#14204a"/>
            <stop offset="0.74" stop-color="#3a2b4f"/>
            <stop offset="0.9" stop-color="#a8613a"/>
            <stop offset="1" stop-color="#d48a45"/>
        </linearGradient>
        <radialGradient id="tq-gm-ne-brillo" cx="0.5" cy="0.5" r="0.5">
            <stop offset="0" stop-color="#fff8e1" stop-opacity="0.95"/>
            <stop offset="0.35" stop-color="#ffe9b0" stop-opacity="0.35"/>
            <stop offset="1" stop-color="#ffe9b0" stop-opacity="0"/>
        </radialGradient>
        <radialGradient id="tq-gm-ne-calor" cx="0.5" cy="0.62" r="0.6">
            <stop offset="0" stop-color="#ffe2a0"/>
            <stop offset="0.45" stop-color="#f2a65a" stop-opacity="0.85"/>
            <stop offset="1" stop-color="#7a3b22" stop-opacity="0"/>
        </radialGradient>
        <linearGradient id="tq-gm-ne-rayo" x1="0" y1="0" x2="0" y2="1">
            <stop offset="0" stop-color="#fff3cf" stop-opacity="0.22"/>
            <stop offset="1" stop-color="#fff3cf" stop-opacity="0"/>
        </linearGradient>
    </defs>

    <rect class="tq-gm-ne-cielo" width="1600" height="900" fill="url(#tq-gm-ne-noche)"/>

    <g fill="#fffaf0">
        @foreach ([[120, 90, 1.6], [260, 210, 1.2], [390, 60, 2], [520, 260, 1.3], [640, 120, 1.5], [1000, 80, 1.8], [1110, 230, 1.2], [1240, 110, 2.2], [1360, 270, 1.4], [1480, 70, 1.6], [180, 330, 1.1], [1430, 360, 1.2], [700, 300, 1], [950, 320, 1.1], [60, 200, 1.3], [1550, 190, 1.5]] as [$x, $y, $r])
            <circle class="tq-gm-ne-astro" cx="{{ $x }}" cy="{{ $y }}" r="{{ $r }}"/>
        @endforeach
    </g>

    <g transform="translate(0 80)">
    {{-- El haz de la estrella hacia el establo, apenas visible. --}}
    <polygon class="tq-gm-ne-haz" points="796,200 804,200 930,540 670,540" fill="url(#tq-gm-ne-rayo)"/>

    <g class="tq-gm-ne-estrella">
        <circle cx="800" cy="175" r="95" fill="url(#tq-gm-ne-brillo)"/>
        <path d="M800 100 L811 164 L875 175 L811 186 L800 250 L789 186 L725 175 L789 164 Z" fill="#fffaf0"/>
        <path d="M800 140 L806 169 L835 175 L806 181 L800 210 L794 181 L765 175 L794 169 Z" fill="#ffffff"/>
    </g>

    </g>

    <g transform="translate(0 -30)">
    {{-- Colinas lejanas y cercanas. --}}
    <path d="M0 720 C 220 660 420 700 600 690 C 820 676 1000 640 1220 672 C 1380 694 1500 680 1600 668 L1600 960 L0 960 Z" fill="#0d1226"/>
    <path d="M0 790 C 260 742 480 770 700 762 C 940 754 1160 736 1400 760 C 1500 770 1560 768 1600 762 L1600 960 L0 960 Z" fill="#05070f"/>

    {{-- La luz del pesebre, detrás de las figuras: es la que las recorta. --}}
    <rect class="tq-gm-ne-luz" x="612" y="540" width="376" height="226" fill="url(#tq-gm-ne-calor)"/>
    <ellipse class="tq-gm-ne-resplandor" cx="800" cy="700" rx="120" ry="70" fill="url(#tq-gm-ne-brillo)"/>

    {{-- El establo, a contraluz. --}}
    <g fill="#04050c">
        <polygon points="548,548 800,438 1052,548 1052,566 800,462 548,566"/>
        <rect x="596" y="540" width="18" height="230"/>
        <rect x="986" y="540" width="18" height="230"/>
        <rect x="596" y="540" width="408" height="10"/>
        <rect x="560" y="762" width="480" height="14"/>
    </g>

    {{-- José, María y el Niño en el pesebre: siluetas sin rostro. --}}
    <g class="tq-gm-ne-figuras" fill="#04050c">
        {{-- José, de pie, con su cayado. --}}
        <circle cx="690" cy="600" r="17"/>
        <path d="M672 622 C 668 660 660 712 652 764 L 730 764 C 724 712 716 660 708 622 Z"/>
        <rect x="723" y="575" width="6" height="190" transform="rotate(4 726 670)"/>
        {{-- María, de rodillas, con el manto sobre la cabeza. --}}
        <path d="M905 640 C 890 640 882 654 884 668 C 870 690 862 726 856 764 L 948 764 C 946 724 934 690 922 668 C 926 652 918 640 905 640 Z"/>
        {{-- El pesebre. --}}
        <path d="M748 716 L852 716 L838 740 L762 740 Z"/>
        <path d="M766 740 L752 764 L760 764 L772 744 Z M834 740 L848 764 L840 764 L828 744 Z"/>
        <ellipse cx="800" cy="712" rx="26" ry="10"/>
    </g>

    {{-- Dos ovejas en la colina lejana, recortadas contra el horizonte. --}}
    <g fill="#0d1226">
        <ellipse cx="1300" cy="668" rx="26" ry="14"/>
        <ellipse cx="1326" cy="660" rx="9" ry="7"/>
        <rect x="1288" y="676" width="4" height="10"/>
        <rect x="1308" y="676" width="4" height="10"/>
        <ellipse cx="1372" cy="672" rx="22" ry="12"/>
        <ellipse cx="1394" cy="665" rx="8" ry="6"/>
        <rect x="1362" y="678" width="4" height="9"/>
        <rect x="1380" y="678" width="4" height="9"/>
    </g>
    </g>
</svg>
