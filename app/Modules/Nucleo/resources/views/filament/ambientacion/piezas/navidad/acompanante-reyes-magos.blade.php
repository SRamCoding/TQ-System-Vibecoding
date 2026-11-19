{{-- Navidad · acompañante · Los Reyes Magos en camino, en silueta sobre el horizonte. --}}
<style>
    .tq-corre .tq-pz-reyes { animation: tq-pz-ry 2.6s ease-out both; }
    @keyframes tq-pz-ry { from { transform: translateX(-14px); opacity: 0; } to { transform: none; opacity: 1; } }
</style>
<g class="tq-pz-reyes" fill="#4A3A2C">
    @foreach ([56, 64, 72] as $x)
        <path d="M{{ $x - 3 }} 64h6l1 3v3M{{ $x - 3 }} 64v6" stroke="#4A3A2C" stroke-width="0.8" fill="none"/>
        <path d="M{{ $x - 3 }} 64c0-3 2-4 4-4l2-2 1 1-1 2v3z"/>
        <path d="M{{ $x }} 60v-4" stroke="#4A3A2C" stroke-width="1.6"/>
        <circle cx="{{ $x }}" cy="54.6" r="1.2"/>
    @endforeach
</g>
