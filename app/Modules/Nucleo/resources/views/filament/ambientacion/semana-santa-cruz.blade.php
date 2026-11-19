{{-- Semana Santa · Cruz: aparece la cruz y se abre la luz detrás. --}}
<svg viewBox="0 0 64 64" aria-hidden="true">
    <style>
        .tq-corre .tq-sc-luz { transform-origin: center; animation: tq-sc-luz 1.2s 0.4s ease-out both; }
        .tq-corre .tq-sc-cruz { animation: tq-sc-sube 0.7s ease-out both; }
        @keyframes tq-sc-luz { from { transform: scale(0.2) rotate(-30deg); opacity: 0; } to { transform: none; opacity: 1; } }
        @keyframes tq-sc-sube { from { transform: translateY(10px); opacity: 0; } to { transform: none; opacity: 1; } }
    </style>
    <g class="tq-sc-luz">
        @foreach (range(0, 330, 30) as $giro)
            <path d="M32 6v8" stroke="#F2C14E" stroke-width="2.4" stroke-linecap="round" opacity="0.8" transform="rotate({{ $giro }} 32 26)"/>
        @endforeach
    </g>
    <path class="tq-sc-cruz" d="M29 12h6v10h10v6H35v28h-6V28H19v-6h10z" fill="#7B5EA7"/>
</svg>
