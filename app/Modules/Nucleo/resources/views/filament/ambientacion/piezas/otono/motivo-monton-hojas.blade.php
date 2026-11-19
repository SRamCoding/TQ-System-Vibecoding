{{-- Otoño · motivo · Montón de hojas secas. --}}
<style>
    .tq-corre .tq-pz-salta { animation: tq-pz-sl 1s ease-out both; transform-box: fill-box; transform-origin: center bottom; }
    @keyframes tq-pz-sl { 0% { transform: none; } 30% { transform: translateY(-6px) rotate(20deg); } to { transform: none; } }
</style>
<path d="M24 70c2-8 8-12 16-12s14 4 16 12z" fill="#D9861F"/>
@foreach ([[30, 64, '#C0602B', 0], [38, 60, '#E0A040', 0.1], [46, 63, '#C0602B', 0.2], [34, 67, '#F2B84E', 0.3], [50, 67, '#E0A040', 0.4]] as [$x, $y, $c, $r])
    <ellipse class="tq-pz-salta" style="animation-delay: {{ $r }}s" cx="{{ $x }}" cy="{{ $y }}" rx="3" ry="1.6" fill="{{ $c }}" transform="rotate(-20 {{ $x }} {{ $y }})"/>
@endforeach
