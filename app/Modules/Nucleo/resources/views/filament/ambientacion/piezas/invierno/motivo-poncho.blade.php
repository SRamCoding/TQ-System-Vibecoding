{{-- Invierno · motivo · Poncho tejido con flecos. --}}
<style>
    .tq-corre .tq-pz-poncho { animation: tq-pz-po 1s ease-out both; transform-origin: 40px 40px; }
    @keyframes tq-pz-po { from { transform: scaleY(0.2); opacity: 0; } to { transform: none; opacity: 1; } }
</style>
<g class="tq-pz-poncho">
    <path d="M40 40l-18 20h36z" fill="#C0392B"/>
    <path d="M28 54h24M31 50h18" stroke="#F2C14E" stroke-width="1.6"/>
    <path d="M33 47l3-3 4 3 4-3 3 3" stroke="#3D4772" stroke-width="1" fill="none"/>
    @foreach ([24, 28, 32, 36, 40, 44, 48, 52, 56] as $x)
        <path d="M{{ $x }} 60v4" stroke="#C0392B" stroke-width="0.8"/>
    @endforeach
</g>
