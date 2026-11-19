{{-- Primavera · motivo · Campanillas: un tallo curvo con campanillas azules. --}}
<style>
    .tq-corre .tq-pz-campanilla { animation: tq-pz-cp 1.2s ease-in-out both; transform-box: fill-box; transform-origin: top center; }
    @keyframes tq-pz-cp { 0% { transform: rotate(-20deg) scale(0); } 60% { transform: rotate(12deg) scale(1); } to { transform: none; } }
</style>
<path d="M32 70c0-14 4-24 14-28 6-2 10 1 10 5" stroke="#4E8F48" stroke-width="1.8" stroke-linecap="round" fill="none"/>
<path d="M33 64c-4-1-6-4-5-7 3 1 5 4 5 7z" fill="#6DB062"/>
@foreach ([[38, 50, 0], [45, 44, 0.15], [53, 44, 0.3], [56, 49, 0.45]] as [$x, $y, $r])
    <path class="tq-pz-campanilla" style="animation-delay: {{ $r }}s" d="M{{ $x - 3 }} {{ $y + 6 }}c0-4 1-6 3-6s3 2 3 6l1 1h-8z" fill="#6E9FE0"/>
@endforeach
