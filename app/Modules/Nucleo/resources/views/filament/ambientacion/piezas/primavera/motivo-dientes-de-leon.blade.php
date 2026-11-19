{{-- Primavera · motivo · Dientes de león: uno amarillo y uno en semilla que suelta vilanos. --}}
<style>
    .tq-corre .tq-pz-vilano { animation: tq-pz-vl 2s ease-out both; }
    @keyframes tq-pz-vl { from { transform: none; opacity: 1; } to { transform: translate(14px, -16px); opacity: 0; } }
</style>
<path d="M32 70V48M48 70V44" stroke="#4E8F48" stroke-width="1.6" stroke-linecap="round"/>
<path d="M32 64c-6 0-8-3-8-6 5 0 8 3 8 6zM48 66c6 0 8-3 8-6-5 0-8 3-8 6z" fill="#6DB062"/>
@foreach ([0, 30, 60, 90, 120, 150, 180, 210, 240, 270, 300, 330] as $g)
    <path d="M32 44v-4" stroke="#F6C944" stroke-width="2" stroke-linecap="round" transform="rotate({{ $g }} 32 46)"/>
@endforeach
<circle cx="32" cy="46" r="2.4" fill="#F2B530"/>
<circle cx="48" cy="40" r="5.5" fill="#FFFFFF" opacity="0.9" stroke="#E5E7EB" stroke-width="0.5"/>
<circle cx="48" cy="40" r="1.2" fill="#A3A3A3"/>
<g class="tq-pz-vilano" stroke="#E5E7EB" stroke-width="0.6" fill="#FFFFFF">
    <path d="M55 36l3-2"/><circle cx="58.5" cy="33.5" r="0.9"/>
</g>
