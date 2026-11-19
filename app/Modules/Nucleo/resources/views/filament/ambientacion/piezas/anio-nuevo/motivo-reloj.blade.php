{{-- Año Nuevo · motivo · Reloj que llega a la medianoche. --}}
<style>
    .tq-corre .tq-pz-minutero { animation: tq-pz-mn 1.6s ease-out both; transform-origin: 40px 50px; }
    @keyframes tq-pz-mn { from { transform: rotate(-90deg); } to { transform: none; } }
</style>
<circle cx="40" cy="50" r="15" fill="#FBF7EC" stroke="#D9A83A" stroke-width="2.4"/>
@foreach ([0, 90, 180, 270] as $g)
    <path d="M40 37.5v2.4" stroke="#3D4772" stroke-width="1.4" transform="rotate({{ $g }} 40 50)"/>
@endforeach
<path d="M40 50v-8" stroke="#3D4772" stroke-width="2" stroke-linecap="round"/>
<path class="tq-pz-minutero" d="M40 50V38.5" stroke="#3D4772" stroke-width="1.2" stroke-linecap="round"/>
<circle cx="40" cy="50" r="1.4" fill="#D9A83A"/>
<path d="M36 66h8" stroke="#D9A83A" stroke-width="2" stroke-linecap="round"/>
