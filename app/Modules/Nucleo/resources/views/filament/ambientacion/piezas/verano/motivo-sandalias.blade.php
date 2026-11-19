{{-- Verano · motivo · Sandalias sobre la arena. --}}
<style>
    .tq-corre .tq-pz-sandalia { animation: tq-pz-sd 0.6s ease-out both; }
    @keyframes tq-pz-sd { from { transform: translateY(-8px); opacity: 0; } to { transform: none; opacity: 1; } }
</style>
@foreach ([[34, 0, 0], [46, 10, 0.2]] as [$x, $g, $r])
    <g class="tq-pz-sandalia" style="animation-delay: {{ $r }}s" transform="rotate({{ $g }} {{ $x }} 64)">
        <ellipse cx="{{ $x }}" cy="64" rx="4" ry="8" fill="#4FA8C8"/>
        <path d="M{{ $x - 3.5 }} 62l3.5-4 3.5 4" stroke="#F6C944" stroke-width="1.4" fill="none"/>
    </g>
@endforeach
