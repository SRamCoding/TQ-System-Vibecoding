{{-- Semana Santa · motivo · La cruz, sencilla y vacía, con lirios blancos al pie. --}}
<style>
    .tq-corre .tq-pz-luz-cruz { animation: tq-pz-lc 2s ease-out both; }
    @keyframes tq-pz-lc { from { opacity: 0; } to { opacity: 0.5; } }
</style>
<circle class="tq-pz-luz-cruz" cx="40" cy="42" r="15" fill="#FFF3C4" opacity="0.5"/>
<path d="M40 28v40M31 38h18" stroke="#8A6143" stroke-width="3.4" stroke-linecap="round"/>
@foreach ([[32, 66], [48, 66]] as [$x, $y])
    <path d="M{{ $x }} 70v-6" stroke="#6DB062" stroke-width="1"/>
    <path d="M{{ $x }} {{ $y }}l-3-4 3 1 3-1z" fill="#FFFFFF" stroke="#E6E1D3" stroke-width="0.4"/>
@endforeach
