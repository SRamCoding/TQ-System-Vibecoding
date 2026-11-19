{{-- Primavera · motivo · Cerezo: árbol de copa rosada que suelta pétalos. --}}
<style>
    .tq-corre .tq-pz-petalo { animation: tq-pz-pt 2.2s ease-in both; }
    @keyframes tq-pz-pt { from { transform: none; opacity: 1; } to { transform: translate(-8px, 18px) rotate(120deg); opacity: 0; } }
</style>
<path d="M40 70V52M40 60l-7-7M40 57l6-6" stroke="#7A5240" stroke-width="3" stroke-linecap="round"/>
<circle cx="31" cy="44" r="10" fill="#F6B8CF"/>
<circle cx="49" cy="43" r="10" fill="#F3A6C3"/>
<circle cx="40" cy="35" r="11" fill="#F9C9DA"/>
@foreach ([[30, 54, 0.2], [52, 52, 0.6], [44, 50, 1]] as [$x, $y, $r])
    <ellipse class="tq-pz-petalo" style="animation-delay: {{ $r }}s" cx="{{ $x }}" cy="{{ $y }}" rx="1.4" ry="0.9" fill="#F29BBF"/>
@endforeach
