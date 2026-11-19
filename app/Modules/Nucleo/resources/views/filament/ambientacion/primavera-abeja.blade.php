{{-- Primavera · Abeja: llega volando a una flor. --}}
<svg viewBox="0 0 64 64" aria-hidden="true">
    <style>
        .tq-corre .tq-pa-abeja { animation: tq-pa-vuela 1.4s ease-out both; }
        .tq-corre .tq-pa-ala { transform-origin: center bottom; animation: tq-pa-zumba 0.12s linear 10 alternate both; }
        @keyframes tq-pa-vuela { 0% { transform: translate(-26px, -14px); opacity: 0; } 40% { transform: translate(-10px, -18px); opacity: 1; } 70% { transform: translate(4px, -6px); } to { transform: none; } }
        @keyframes tq-pa-zumba { from { transform: scaleY(1); } to { transform: scaleY(0.4); } }
    </style>
    <path d="M40 62V44" stroke="#5E9E57" stroke-width="2.5" stroke-linecap="round"/>
    <path d="M40 54c5 0 8-3 9-7-5 0-8 3-9 7z" fill="#8CC084"/>
    @foreach ([0, 60, 120, 180, 240, 300] as $giro)
        <ellipse cx="40" cy="34" rx="3.6" ry="6" fill="#F2A7C3" transform="rotate({{ $giro }} 40 40)"/>
    @endforeach
    <circle cx="40" cy="40" r="4" fill="#F6D365"/>
    <g class="tq-pa-abeja">
        <ellipse class="tq-pa-ala" cx="20" cy="17" rx="4" ry="5" fill="#E8F4F8" stroke="#A6DAE5"/>
        <ellipse class="tq-pa-ala" cx="26" cy="17" rx="4" ry="5" fill="#E8F4F8" stroke="#A6DAE5"/>
        <ellipse cx="23" cy="25" rx="8" ry="5.5" fill="#F2C14E"/>
        <path d="M20 20.5v9M24 19.6v10.8" stroke="#3D4772" stroke-width="2"/>
        <circle cx="30" cy="24" r="1.2" fill="#3D4772"/>
    </g>
</svg>
