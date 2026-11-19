{{-- Fechas · acompañante común · Confeti que cae. --}}
<style>
    .tq-corre .tq-pz-confeti rect { animation: tq-pz-cf 1.8s ease-in both; }
    @keyframes tq-pz-cf { from { transform: translateY(-14px) rotate(0); opacity: 0; } 20% { opacity: 1; } to { transform: none; opacity: 1; } }
</style>
<g class="tq-pz-confeti">
    @foreach ([[12, 20, '#E8414F'], [22, 30, '#F6C944'], [30, 16, '#4FA8C8'], [52, 22, '#8CC084'], [62, 34, '#F29BBF'], [70, 18, '#B9A3EC'], [18, 40, '#F6C944'], [66, 44, '#E8414F']] as $i => [$x, $y, $c])
        <rect x="{{ $x }}" y="{{ $y }}" width="2.2" height="1.2" fill="{{ $c }}" transform="rotate({{ $i * 37 }} {{ $x }} {{ $y }})" style="animation-delay: {{ $i * 0.1 }}s"/>
    @endforeach
</g>
