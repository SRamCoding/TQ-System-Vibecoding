{{-- Semana Santa · motivo · Palmas del Domingo de Ramos. --}}
<style>
    .tq-corre .tq-pz-palma { animation: tq-pz-pr 1.6s ease-in-out both; transform-origin: 40px 70px; }
    @keyframes tq-pz-pr { 0%, 100% { transform: none; } 40% { transform: rotate(-4deg); } 70% { transform: rotate(3deg); } }
</style>
@foreach ([[-14, 0], [12, 0.15]] as [$g, $r])
    <g class="tq-pz-palma" style="animation-delay: {{ $r }}s" transform="rotate({{ $g }} 40 70)">
        <path d="M40 70V34" stroke="#5E9E57" stroke-width="1.2"/>
        @foreach ([38, 43, 48, 53, 58] as $y)
            <path d="M40 {{ $y }}l-6 4M40 {{ $y }}l6 4" stroke="#6DB062" stroke-width="1.6" stroke-linecap="round"/>
        @endforeach
    </g>
@endforeach
