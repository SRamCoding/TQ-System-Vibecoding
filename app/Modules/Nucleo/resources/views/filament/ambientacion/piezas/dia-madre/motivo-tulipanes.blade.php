{{-- Día de la Madre · motivo · Tulipanes en un florero. --}}
<style>
    .tq-corre .tq-pz-tulipan { animation: tq-pz-tl 0.8s ease-out both; transform-box: fill-box; transform-origin: center bottom; }
    @keyframes tq-pz-tl { from { transform: scaleY(0.3); } to { transform: none; } }
</style>
@foreach ([[34, '#E06C9F', 0], [40, '#C0392B', 0.15], [46, '#F2A7C3', 0.3]] as [$x, $c, $r])
    <g class="tq-pz-tulipan" style="animation-delay: {{ $r }}s">
        <path d="M{{ $x }} 58V44" stroke="#5E9E57" stroke-width="1.2"/>
        <path d="M{{ $x - 3 }} 44c0-5 1-6 1-6l2 2 2-2s1 1 1 6c0 3-6 3-6 0z" fill="{{ $c }}"/>
    </g>
@endforeach
<path d="M32 58h16l-2 12H34z" fill="#A6DAE5"/>
