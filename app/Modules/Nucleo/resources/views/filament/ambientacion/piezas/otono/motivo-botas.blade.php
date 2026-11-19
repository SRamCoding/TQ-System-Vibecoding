{{-- Otoño · motivo · Botas de lluvia junto a un charco. --}}
<style>
    .tq-corre .tq-pz-onda { animation: tq-pz-od 1.2s ease-out 2 both; transform-box: fill-box; transform-origin: center; }
    @keyframes tq-pz-od { from { transform: scale(0.4); opacity: 1; } to { transform: scale(1.3); opacity: 0; } }
</style>
<ellipse cx="44" cy="70" rx="14" ry="3" fill="#8FC9E3"/>
<ellipse class="tq-pz-onda" cx="50" cy="70" rx="4" ry="1" fill="none" stroke="#D7EEF8" stroke-width="0.6"/>
@foreach ([[32, 0], [42, 0]] as [$x])
    <path d="M{{ $x }} 52h7v14h4a2 2 0 0 1 0 4H{{ $x }}z" fill="#F6C944"/>
    <rect x="{{ $x }}" y="52" width="7" height="2" fill="#E0A82C"/>
@endforeach
