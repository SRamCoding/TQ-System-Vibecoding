{{-- Fiestas Patrias · motivo · Sol radiante sobre los cerros. --}}
<style>
    .tq-corre .tq-pz-rayos-sol { animation: tq-pz-rs3 2s ease-out both; transform-origin: 40px 50px; }
    @keyframes tq-pz-rs3 { from { transform: rotate(-45deg) scale(0.6); opacity: 0; } to { transform: none; opacity: 1; } }
</style>
<g class="tq-pz-rayos-sol">
    @foreach (range(0, 330, 30) as $g)
        <path d="M40 36v-5" stroke="{{ $g % 60 ? '#D91023' : '#F2B530' }}" stroke-width="2" stroke-linecap="round" transform="rotate({{ $g }} 40 50)"/>
    @endforeach
</g>
<circle cx="40" cy="50" r="11" fill="#F6C944"/>
<circle cx="40" cy="50" r="7" fill="#F2B530"/>
