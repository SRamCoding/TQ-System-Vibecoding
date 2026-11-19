{{-- Primavera · motivo · Regadera: riega unos brotes. --}}
<style>
    .tq-corre .tq-pz-gota { animation: tq-pz-gt 0.7s ease-in 3 both; }
    @keyframes tq-pz-gt { from { transform: none; opacity: 1; } to { transform: translate(-3px, 9px); opacity: 0; } }
</style>
<path d="M42 58h16l-2 12H44z" fill="#7FB3D5"/>
<path d="M42 60l-12-8" stroke="#6A9FC2" stroke-width="2.4" stroke-linecap="round"/>
<path d="M27 50l4 1-1 4z" fill="#6A9FC2"/>
<path d="M58 61c5 0 5 7 0 7" stroke="#6A9FC2" stroke-width="1.8" fill="none"/>
@foreach ([[27, 55, 0], [25, 57, 0.2], [29, 58, 0.4]] as [$x, $y, $r])
    <circle class="tq-pz-gota" style="animation-delay: {{ $r }}s" cx="{{ $x }}" cy="{{ $y }}" r="0.9" fill="#8FC9E3"/>
@endforeach
@foreach ([18, 26] as $x)
    <path d="M{{ $x }} 70v-6" stroke="#4E8F48" stroke-width="1.5" stroke-linecap="round"/>
    <path d="M{{ $x }} 65c-4 0-5-2-5-4 3 0 5 1 5 4zM{{ $x }} 64c3 0 5-2 5-4-3 0-5 1-5 4z" fill="#7CC36C"/>
@endforeach
