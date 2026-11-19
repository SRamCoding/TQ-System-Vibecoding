{{-- Primavera · motivo · Brotes: una hilera de brotes recién nacidos. --}}
<style>
    .tq-corre .tq-pz-brote { animation: tq-pz-asoma 0.8s ease-out both; transform-origin: center bottom; transform-box: fill-box; }
    @keyframes tq-pz-asoma { from { transform: translateY(6px) scale(0.4); opacity: 0; } to { transform: none; opacity: 1; } }
</style>
@foreach ([[22, 0], [34, 0.15], [46, 0.3], [58, 0.45]] as [$x, $r])
    <g class="tq-pz-brote" style="animation-delay: {{ $r }}s">
        <path d="M{{ $x }} 70v-9" stroke="#4E8F48" stroke-width="1.8" stroke-linecap="round"/>
        <path d="M{{ $x }} 63c-5 0-7-3-7-6 4 0 7 2 7 6zM{{ $x }} 62c4 0 6-3 6-6-4 0-6 2-6 6z" fill="#7CC36C"/>
    </g>
@endforeach
