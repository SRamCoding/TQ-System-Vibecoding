{{-- Primavera · motivo · Arco de flores: una pérgola cubierta de flores. --}}
<style>
    .tq-corre .tq-pz-arco circle { animation: tq-pz-ar 0.5s ease-out both; transform-box: fill-box; transform-origin: center; }
    @keyframes tq-pz-ar { from { transform: scale(0); } to { transform: none; } }
</style>
<path d="M24 70V46a16 16 0 0 1 32 0v24" stroke="#C9A97B" stroke-width="3" fill="none"/>
<path d="M24 50c4-2 6-6 6-10M56 50c-4-2-6-6-6-10M30 36c4-4 16-4 20 0" stroke="#6DB062" stroke-width="2.6" fill="none" stroke-linecap="round"/>
<g class="tq-pz-arco">
    @foreach ([[25, 47, '#F29BBF', 0], [29, 39, '#FFFFFF', 0.1], [35, 33, '#F7A23B', 0.2], [42, 31, '#F29BBF', 0.3], [49, 34, '#FFFFFF', 0.4], [53, 41, '#B9A3EC', 0.5], [55, 49, '#F29BBF', 0.6], [25, 57, '#B9A3EC', 0.7], [55, 59, '#FFFFFF', 0.8]] as [$x, $y, $c, $r])
        <circle cx="{{ $x }}" cy="{{ $y }}" r="2.2" fill="{{ $c }}" style="animation-delay: {{ $r }}s"/>
    @endforeach
</g>
