{{-- Año Nuevo · motivo · Las doce uvas en un platito. --}}
<style>
    .tq-corre .tq-pz-uva-an { animation: tq-pz-ua 0.4s ease-out both; transform-box: fill-box; transform-origin: center; }
    @keyframes tq-pz-ua { from { transform: scale(0); } to { transform: none; } }
</style>
<ellipse cx="40" cy="66" rx="16" ry="3.4" fill="#FBF7EC" stroke="#D2C9B8" stroke-width="0.6"/>
@foreach ([[30, 62], [35, 62], [40, 62], [45, 62], [50, 62], [32.5, 58], [37.5, 58], [42.5, 58], [47.5, 58], [35, 54], [40, 54], [45, 54]] as $i => [$x, $y])
    <circle class="tq-pz-uva-an" style="animation-delay: {{ $i * 0.06 }}s" cx="{{ $x }}" cy="{{ $y }}" r="2.6" fill="#8CC084"/>
@endforeach
