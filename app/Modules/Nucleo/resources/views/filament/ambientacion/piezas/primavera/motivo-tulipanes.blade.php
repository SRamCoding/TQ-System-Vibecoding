{{-- Primavera · motivo · Tulipanes: tres tulipanes que crecen. --}}
<style>
    .tq-corre .tq-pz-tulipan { animation: tq-pz-crece 0.9s ease-out both; transform-origin: center bottom; transform-box: fill-box; }
    @keyframes tq-pz-crece { from { transform: scaleY(0.3); opacity: 0; } to { transform: none; opacity: 1; } }
</style>
@foreach ([[30, 46, '#F06B7A', 0], [40, 40, '#F7A23B', 0.15], [50, 47, '#C77DDB', 0.3]] as [$x, $alto, $color, $retraso])
    <g class="tq-pz-tulipan" style="animation-delay: {{ $retraso }}s">
        <path d="M{{ $x }} 70V{{ $alto + 6 }}" stroke="#4E8F48" stroke-width="2" stroke-linecap="round"/>
        <path d="M{{ $x }} {{ $alto + 16 }}c-5 0-7-3-7-7 4 0 7 3 7 7z" fill="#6DB062"/>
        <path d="M{{ $x - 5 }} {{ $alto }}l2.5 3 2.5-4 2.5 4 2.5-3v6a5 5 0 0 1-10 0z" fill="{{ $color }}"/>
    </g>
@endforeach
