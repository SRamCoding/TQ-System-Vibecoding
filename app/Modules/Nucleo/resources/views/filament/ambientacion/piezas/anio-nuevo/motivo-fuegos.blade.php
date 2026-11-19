{{-- Año Nuevo · motivo · Fuegos artificiales. --}}
<style>
    .tq-corre .tq-pz-fuego { animation: tq-pz-fg 1.2s ease-out both; transform-box: fill-box; transform-origin: center; }
    @keyframes tq-pz-fg { from { transform: scale(0.1); opacity: 0; } 60% { opacity: 1; } to { transform: none; opacity: 1; } }
</style>
@foreach ([[34, 40, '#F2C14E', 0], [50, 48, '#E06C9F', 0.4]] as [$x, $y, $c, $r])
    <g class="tq-pz-fuego" style="animation-delay: {{ $r }}s">
        @foreach ([0, 45, 90, 135, 180, 225, 270, 315] as $g)
            <path d="M{{ $x }} {{ $y - 4 }}v-5" stroke="{{ $c }}" stroke-width="1.4" stroke-linecap="round" transform="rotate({{ $g }} {{ $x }} {{ $y }})"/>
        @endforeach
    </g>
@endforeach
