{{-- Semana Santa · Amanecer: el sol sale detrás de la colina con la cruz. --}}
<svg viewBox="0 0 64 64" aria-hidden="true">
    <style>
        .tq-corre .tq-sa-sol { animation: tq-sa-sale 1.3s ease-out both; }
        .tq-corre .tq-sa-rayos { transform-origin: 32px 40px; transform-box: view-box; animation: tq-sa-rayos 1s 0.8s ease-out both; }
        @keyframes tq-sa-sale { from { transform: translateY(22px); } to { transform: none; } }
        @keyframes tq-sa-rayos { from { transform: scale(0.4); opacity: 0; } to { transform: none; opacity: 1; } }
    </style>
    <g class="tq-sa-rayos">
        @foreach ([-60, -30, 0, 30, 60] as $giro)
            <path d="M32 8v7" stroke="#F2C14E" stroke-width="2.4" stroke-linecap="round" transform="rotate({{ $giro }} 32 40)"/>
        @endforeach
    </g>
    <circle class="tq-sa-sol" cx="32" cy="38" r="12" fill="#F6D365"/>
    <path d="M2 60c10-14 20-18 30-18s20 4 30 18z" fill="#8CC084"/>
    <path d="M31 30h2v4h4v2h-4v8h-2v-8h-4v-2h4z" fill="#7B5EA7"/>
</svg>
