{{-- Otoño · motivo · Racimo de uvas de la vendimia. --}}
<style>
    .tq-corre .tq-pz-uva { animation: tq-pz-uv 0.5s ease-out both; transform-box: fill-box; transform-origin: center; }
    @keyframes tq-pz-uv { from { transform: scale(0); } to { transform: none; } }
</style>
<path d="M40 34v4M40 36c4-4 9-3 10 0-4 1-7 1-10 0z" stroke="#6B4A34" stroke-width="1" fill="#6DB062"/>
@foreach ([[35, 42], [40, 42], [45, 42], [37.5, 47], [42.5, 47], [40, 52], [32.5, 47], [47.5, 47], [35, 52], [45, 52], [37.5, 57], [42.5, 57], [40, 62]] as $i => [$x, $y])
    <circle class="tq-pz-uva" style="animation-delay: {{ $i * 0.05 }}s" cx="{{ $x }}" cy="{{ $y }}" r="2.8" fill="#7B4FA0"/>
@endforeach
