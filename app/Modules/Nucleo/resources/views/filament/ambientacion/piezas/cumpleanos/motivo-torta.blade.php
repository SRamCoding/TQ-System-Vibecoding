{{-- Cumpleaños · motivo · Torta con velas encendidas. --}}
<style>
    .tq-corre .tq-pz-llama-t { animation: tq-pz-lt2 0.5s ease-in-out 4 alternate both; transform-box: fill-box; transform-origin: center bottom; }
    @keyframes tq-pz-lt2 { from { transform: scaleY(0.8); } to { transform: scaleY(1.2); } }
</style>
<rect x="26" y="56" width="28" height="13" rx="2" fill="#F2A7C3"/>
<rect x="29" y="49" width="22" height="8" rx="2" fill="#FBF7EC"/>
<path d="M26 60c3 2 4 0 7 2s4 0 7 2 4 0 7 2 4 0 7-2" stroke="#FBF7EC" stroke-width="1.6" fill="none"/>
@foreach ([34, 40, 46] as $i => $x)
    <rect x="{{ $x - 0.8 }}" y="42" width="1.6" height="7" fill="{{ ['#4FA8C8', '#E06C9F', '#F6C944'][$i] }}"/>
    <path class="tq-pz-llama-t" style="animation-delay: {{ $i * 0.1 }}s" d="M{{ $x }} 37c1.6 1.6 1.6 3.4 0 4.4-1.6-1-1.6-2.8 0-4.4z" fill="#F2B530"/>
@endforeach
