{{-- Año Nuevo · Fuegos sobre la ciudad (ficha op-gran-momento): la ciudad de noche y fuegos que se abren despacio, sin
     destellos (cada estallido crece y se apaga en 2 s, nunca parpadea). Quieta con «reducir». --}}
<svg viewBox="0 0 1600 900" preserveAspectRatio="xMidYMid slice" role="presentation">
    <style>
        .tq-gm-corre .tq-gm-fu-fuego { transform-box: fill-box; transform-origin: center; opacity: 0; animation: tq-gm-fu-abre 2.4s ease-out both; }
        @keyframes tq-gm-fu-abre { 0% { opacity: 0; transform: scale(.1); } 30% { opacity: 1; } 100% { opacity: 0; transform: scale(1); } }
        @media (prefers-reduced-motion: reduce) { .tq-gm-corre .tq-gm-fu-fuego { animation: none !important; opacity: .85; } }
        html.tq-sin-animaciones .tq-gm-corre .tq-gm-fu-fuego { animation: none !important; opacity: .85; }
    </style>
    <defs><linearGradient id="tq-gm-fu-cielo" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#03040f"/><stop offset="1" stop-color="#1b1f45"/></linearGradient></defs>
    <rect width="1600" height="900" fill="url(#tq-gm-fu-cielo)"/>
    @foreach ([[420, 260, '#ffd166', 0.5], [800, 200, '#ef476f', 1.6], [1180, 280, '#06d6a0', 2.7], [600, 340, '#118ab2', 3.8], [1000, 330, '#ffd166', 4.9], [800, 240, '#f4f1de', 6.0], [380, 300, '#ef476f', 7.1], [1220, 220, '#ffd166', 8.0]] as [$x, $y, $c, $d])
        <g class="tq-gm-fu-fuego" style="animation-delay: {{ $d }}s">
            @for ($i = 0; $i < 16; $i++)
                @php $a = deg2rad($i * 22.5); @endphp
                <line x1="{{ $x }}" y1="{{ $y }}" x2="{{ round($x + cos($a) * 120) }}" y2="{{ round($y + sin($a) * 120) }}" stroke="{{ $c }}" stroke-width="4" stroke-linecap="round" stroke-dasharray="6 14"/>
            @endfor
            <circle cx="{{ $x }}" cy="{{ $y }}" r="10" fill="{{ $c }}"/>
        </g>
    @endforeach
    <path d="M0 900 V700 h80 v-60 h50 v80 h70 v-120 h60 v90 h90 v-50 h40 v-90 h70 v130 h60 v-70 h90 v100 h70 v-140 h50 v60 h80 v-40 h70 v110 h60 v-80 h90 v50 h50 v-100 h70 v140 h80 v-60 h60 v80 h90 V900 Z" fill="#070914"/>
</svg>
