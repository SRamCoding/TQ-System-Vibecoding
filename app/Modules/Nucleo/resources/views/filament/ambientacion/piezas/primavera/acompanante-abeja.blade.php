{{-- Primavera · acompañante · Abeja: llega volando hacia la flor. --}}
<style>
    .tq-corre .tq-pz-abeja { animation: tq-pz-vuela 1.6s ease-out both; }
    .tq-corre .tq-pz-ala { transform-origin: center bottom; transform-box: fill-box; animation: tq-pz-zumba 0.12s linear 12 alternate both; }
    @keyframes tq-pz-vuela { 0% { transform: translate(-20px, -10px); opacity: 0; } 40% { transform: translate(-8px, -14px); opacity: 1; } to { transform: none; } }
    @keyframes tq-pz-zumba { from { transform: scaleY(1); } to { transform: scaleY(0.4); } }
</style>
<g class="tq-pz-abeja">
    <ellipse class="tq-pz-ala" cx="20" cy="27" rx="3.4" ry="4.4" fill="#EEF7FB" stroke="#A6DAE5" stroke-width="0.6"/>
    <ellipse class="tq-pz-ala" cx="25" cy="27" rx="3.4" ry="4.4" fill="#EEF7FB" stroke="#A6DAE5" stroke-width="0.6"/>
    <ellipse cx="22.5" cy="33" rx="6.5" ry="4.6" fill="#F6C944"/>
    <path d="M20 29v8M23.5 28.6v8.8" stroke="#3D4772" stroke-width="1.6"/>
    <circle cx="28" cy="32.5" r="1" fill="#3D4772"/>
</g>
