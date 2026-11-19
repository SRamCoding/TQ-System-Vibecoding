{{-- Primavera · motivo · Flor: una flor grande que se abre. --}}
<style>
    .tq-corre .tq-pz-flor-petalos { animation: tq-pz-abre 1s cubic-bezier(0.3, 1.4, 0.5, 1) 0.2s both; transform-origin: 40px 40px; }
    @keyframes tq-pz-abre { from { transform: scale(0.2) rotate(-30deg); } to { transform: none; } }
</style>
<path d="M40 70V44" stroke="#4E8F48" stroke-width="2.4" stroke-linecap="round"/>
<path d="M40 60c6 0 9-3 10-8-6 0-9 3-10 8zM40 64c-5 0-8-3-9-7 5 0 8 3 9 7z" fill="#6DB062"/>
<g class="tq-pz-flor-petalos">
    @foreach ([0, 60, 120, 180, 240, 300] as $giro)
        <ellipse cx="40" cy="33" rx="4.6" ry="7.5" fill="#F29BBF" transform="rotate({{ $giro }} 40 40)"/>
    @endforeach
    <circle cx="40" cy="40" r="4.8" fill="#F6CF4E"/>
    <circle cx="39" cy="39" r="1.4" fill="#FCE79A"/>
</g>
