{{-- Año Nuevo · Reloj: las agujas llegan a las doce y brilla. --}}
<svg viewBox="0 0 64 64" aria-hidden="true">
    <style>
        .tq-corre .tq-yr-minutero { transform-origin: 32px 34px; transform-box: view-box; animation: tq-yr-gira 1.2s ease-in-out both; }
        .tq-corre .tq-yr-brillo { transform-origin: center; animation: tq-yr-brilla 0.5s 1.2s ease-out both; }
        @keyframes tq-yr-gira { from { transform: rotate(-300deg); } to { transform: rotate(0); } }
        @keyframes tq-yr-brilla { from { transform: scale(0); opacity: 0; } to { transform: scale(1); opacity: 1; } }
    </style>
    <circle cx="32" cy="34" r="22" fill="#F6F0F0" stroke="#3D4772" stroke-width="3"/>
    @foreach (range(0, 330, 30) as $giro)
        <path d="M32 15v{{ $giro % 90 === 0 ? 4 : 2 }}" stroke="#3D4772" stroke-width="2" stroke-linecap="round" transform="rotate({{ $giro }} 32 34)"/>
    @endforeach
    <path d="M32 34V24" stroke="#3D4772" stroke-width="3" stroke-linecap="round"/>
    <path class="tq-yr-minutero" d="M32 34V17" stroke="#D9A83A" stroke-width="2.2" stroke-linecap="round"/>
    <circle cx="32" cy="34" r="2.2" fill="#3D4772"/>
    <path class="tq-yr-brillo" d="M52 6l1.5 3.5L57 11l-3.5 1.5L52 16l-1.5-3.5L47 11l3.5-1.5z" fill="#F2C14E"/>
</svg>
