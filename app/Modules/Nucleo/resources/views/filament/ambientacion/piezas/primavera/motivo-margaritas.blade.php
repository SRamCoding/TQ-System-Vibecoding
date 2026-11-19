{{-- Primavera · motivo · Margaritas: un ramillete de margaritas blancas. --}}
<style>
    .tq-corre .tq-pz-margarita { animation: tq-pz-mg 0.8s cubic-bezier(0.3, 1.4, 0.5, 1) both; transform-box: fill-box; transform-origin: center; }
    @keyframes tq-pz-mg { from { transform: scale(0.2); } to { transform: none; } }
</style>
@foreach ([[30, 48, 0], [42, 42, 0.15], [52, 50, 0.3]] as [$x, $y, $r])
    <path d="M{{ $x }} 70V{{ $y + 3 }}" stroke="#4E8F48" stroke-width="1.8" stroke-linecap="round"/>
    <g class="tq-pz-margarita" style="animation-delay: {{ $r }}s">
        @foreach ([0, 45, 90, 135, 180, 225, 270, 315] as $g)
            <ellipse cx="{{ $x }}" cy="{{ $y - 4.5 }}" rx="1.8" ry="3.6" fill="#FFFFFF" stroke="#E5E7EB" stroke-width="0.4" transform="rotate({{ $g }} {{ $x }} {{ $y }})"/>
        @endforeach
        <circle cx="{{ $x }}" cy="{{ $y }}" r="2.6" fill="#F6C944"/>
    </g>
@endforeach
<path d="M36 64c-4 0-6-3-6-6 4 0 6 3 6 6z" fill="#6DB062"/>
