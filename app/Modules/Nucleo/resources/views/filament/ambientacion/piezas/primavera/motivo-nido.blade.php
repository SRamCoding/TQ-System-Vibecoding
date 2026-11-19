{{-- Primavera · motivo · Nido: una rama con un nido y tres huevitos celestes. --}}
<style>
    .tq-corre .tq-pz-huevo { animation: tq-pz-hv 1.2s ease-in-out both; transform-box: fill-box; transform-origin: center bottom; }
    @keyframes tq-pz-hv { 0%, 100% { transform: none; } 30% { transform: rotate(-12deg); } 60% { transform: rotate(10deg); } }
</style>
<path d="M8 50c14 2 30 4 64-2" stroke="#7A5240" stroke-width="3" stroke-linecap="round" fill="none"/>
<path d="M20 51c-2-4 0-6 3-7M60 49c2-4 6-5 9-4" stroke="#6DB062" stroke-width="2" stroke-linecap="round" fill="none"/>
<ellipse cx="62" cy="44" rx="4" ry="2.4" fill="#6DB062"/>
<path d="M28 50c0 7 6 10 12 10s12-3 12-10z" fill="#A87A55"/>
<path d="M28 50h24" stroke="#8A6143" stroke-width="2" stroke-linecap="round"/>
@foreach ([[35, 0], [40, 0.2], [45, 0.4]] as [$x, $r])
    <ellipse class="tq-pz-huevo" style="animation-delay: {{ $r }}s" cx="{{ $x }}" cy="47" rx="2.6" ry="3.2" fill="#A6DAE5"/>
@endforeach
