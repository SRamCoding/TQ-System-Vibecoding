{{-- Primavera · acompañante · Mariposa: aletea en el aire. --}}
<style>
    .tq-corre .tq-pz-mariposa { animation: tq-pz-flota 2s ease-in-out both; }
    .tq-corre .tq-pz-alas { transform-origin: 62px 30px; animation: tq-pz-aletea 0.3s ease-in-out 6 alternate both; }
    @keyframes tq-pz-flota { from { transform: translate(8px, 10px); opacity: 0; } to { transform: none; opacity: 1; } }
    @keyframes tq-pz-aletea { from { transform: scaleX(1); } to { transform: scaleX(0.35); } }
</style>
<g class="tq-pz-mariposa">
    <g class="tq-pz-alas">
        <path d="M62 30c-4-6-10-6-10-1 0 4 5 5 10 1zM62 30c4-6 10-6 10-1 0 4-5 5-10 1z" fill="#B9A3EC"/>
        <path d="M62 31c-3 3-7 5-7 2s4-3 7-2zM62 31c3 3 7 5 7 2s-4-3-7-2z" fill="#F2A7C3"/>
    </g>
    <path d="M62 26v8" stroke="#3D4772" stroke-width="1.4" stroke-linecap="round"/>
</g>
