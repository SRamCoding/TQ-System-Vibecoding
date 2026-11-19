{{-- Semana Santa · motivo · El amanecer de la Resurrección detrás del cerro. --}}
<style>
    .tq-corre .tq-pz-amanecer { animation: tq-pz-am 2.2s ease-out both; }
    @keyframes tq-pz-am { from { transform: translateY(10px); opacity: 0.3; } to { transform: none; opacity: 1; } }
</style>
<g class="tq-pz-amanecer">
    <circle cx="40" cy="58" r="10" fill="#F6D365"/>
    @foreach ([-60, -30, 0, 30, 60] as $g)
        <path d="M40 46v-8" stroke="#F6D365" stroke-width="1.6" stroke-linecap="round" transform="rotate({{ $g }} 40 58)"/>
    @endforeach
</g>
<path d="M20 64c8-6 32-6 40 0v6H20z" fill="#8E9C6A"/>
