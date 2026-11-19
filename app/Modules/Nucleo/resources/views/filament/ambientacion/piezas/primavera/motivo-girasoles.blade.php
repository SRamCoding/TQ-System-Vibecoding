{{-- Primavera · motivo · Girasoles: dos girasoles que miran al cielo. --}}
<style>
    .tq-corre .tq-pz-girasol { animation: tq-pz-gs 1.2s ease-out both; transform-box: fill-box; transform-origin: center; }
    @keyframes tq-pz-gs { from { transform: rotate(-90deg) scale(0.4); } to { transform: none; } }
</style>
@foreach ([[34, 40, 7, 0], [50, 46, 5.5, 0.2]] as [$x, $y, $r, $d])
    <path d="M{{ $x }} 70V{{ $y + $r }}" stroke="#4E8F48" stroke-width="2" stroke-linecap="round"/>
    <path d="M{{ $x }} {{ $y + $r + 10 }}c-6 0-8-3-8-6 5 0 8 3 8 6z" fill="#6DB062"/>
    <g class="tq-pz-girasol" style="animation-delay: {{ $d }}s">
        @foreach ([0, 30, 60, 90, 120, 150, 180, 210, 240, 270, 300, 330] as $g)
            <ellipse cx="{{ $x }}" cy="{{ $y - $r }}" rx="{{ $r * 0.32 }}" ry="{{ $r * 0.6 }}" fill="#F6C944" transform="rotate({{ $g }} {{ $x }} {{ $y }})"/>
        @endforeach
        <circle cx="{{ $x }}" cy="{{ $y }}" r="{{ $r * 0.62 }}" fill="#8A5A2B"/>
    </g>
@endforeach
