{{-- Navidad · motivo · Corona de Adviento con sus cuatro velas. --}}
<style>
    .tq-corre .tq-pz-llama-vela { animation: tq-pz-lv 0.6s ease-in-out 4 alternate both; transform-box: fill-box; transform-origin: center bottom; }
    @keyframes tq-pz-lv { from { transform: scaleY(0.8); } to { transform: scaleY(1.15); } }
</style>
<ellipse cx="40" cy="64" rx="18" ry="6" fill="#2F7D4F"/>
<ellipse cx="40" cy="64" rx="10" ry="3" fill="#256B41"/>
@foreach ([[28, 64, '#7B4FA0'], [36, 60, '#7B4FA0'], [44, 60, '#F29BBF'], [52, 64, '#7B4FA0']] as $i => [$x, $y, $c])
    <rect x="{{ $x - 1.8 }}" y="{{ $y - 12 }}" width="3.6" height="12" fill="{{ $c }}"/>
    <path class="tq-pz-llama-vela" style="animation-delay: {{ $i * 0.1 }}s" d="M{{ $x }} {{ $y - 17 }}c2 2 2 4 0 5-2-1-2-3 0-5z" fill="#F6C944"/>
@endforeach
<circle cx="32" cy="66" r="1.2" fill="#E8414F"/><circle cx="48" cy="66" r="1.2" fill="#E8414F"/>
