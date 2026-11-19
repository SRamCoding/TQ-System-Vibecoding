{{-- Aniversario · motivo · Engranajes que giran juntos. --}}
<style>
    .tq-corre .tq-pz-engranaje-a { animation: tq-pz-ea 2s ease-out both; transform-origin: 34px 52px; }
    .tq-corre .tq-pz-engranaje-b { animation: tq-pz-eb2 2s ease-out both; transform-origin: 48px 44px; }
    @keyframes tq-pz-ea { from { transform: rotate(-180deg); } to { transform: none; } }
    @keyframes tq-pz-eb2 { from { transform: rotate(240deg); } to { transform: none; } }
</style>
@foreach ([['tq-pz-engranaje-a', 34, 52, 8, '#3D4772'], ['tq-pz-engranaje-b', 48, 44, 6, \App\Modules\Nucleo\Navegacion\ColoresMarca::TURQUESA]] as [$c, $x, $y, $r, $color])
    <g class="{{ $c }}">
        @foreach (range(0, 315, 45) as $g)
            <rect x="{{ $x - 1.6 }}" y="{{ $y - $r - 2.4 }}" width="3.2" height="3.4" fill="{{ $color }}" transform="rotate({{ $g }} {{ $x }} {{ $y }})"/>
        @endforeach
        <circle cx="{{ $x }}" cy="{{ $y }}" r="{{ $r }}" fill="{{ $color }}"/>
        <circle cx="{{ $x }}" cy="{{ $y }}" r="{{ $r / 3 }}" fill="#FFFFFF"/>
    </g>
@endforeach
