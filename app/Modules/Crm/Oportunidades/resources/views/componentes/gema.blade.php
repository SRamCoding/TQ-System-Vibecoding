{{--
    La gema de una oportunidad (plan 2.21c, D3): SVG en línea, sin imágenes. Uso:
    <x-oportunidades::gema :talla="TallaDeGema::desdeEtapa($fila->etapa)" :monto="$fila->montoEnSoles" :semilla="$fila->id" />
    - talla: TallaDeGema o su valor ('en_bruto'…'fisurada').
    - monto: en soles (texto decimal, entero o nulo; un float falla); el tamaño sale de EscalaDeGema entre `minimo` y `maximo` (píxeles de ancho).
    - tamano: un ancho fijo (tablas, encabezados, la ficha); manda sobre el monto.
    - semilla: varía un poco la piedra en bruto y desbastada (la misma oportunidad, la misma piedra); suele ser su id.
    - decorativa: true si el texto de al lado ya dice la talla (aria-hidden); si no, role="img" con «Gema tallada».
    Ids únicos por gema: varias en la misma página no comparten su recorte. El reflejo y los destellos se repiten pocas
    veces (no hay bucles) y vuelven al pasar el mouse o con el foco sobre la gema o sobre un contenedor con
    `data-tq-gema-anfitrion`; con «reducir movimiento» o «reducir animaciones», quietos.
--}}
@props([
    'talla',
    'monto' => null,
    'tamano' => null,
    'minimo' => \App\Modules\Crm\Oportunidades\Filament\EscalaDeGema::MINIMO,
    'maximo' => \App\Modules\Crm\Oportunidades\Filament\EscalaDeGema::MAXIMO,
    'semilla' => 0,
    'decorativa' => false,
])
@php
    $talla = $talla instanceof \App\Modules\Crm\Oportunidades\Enums\TallaDeGema ? $talla : \App\Modules\Crm\Oportunidades\Enums\TallaDeGema::from((string) $talla);
    $dibujo = \App\Modules\Crm\Oportunidades\Filament\DibujoDeGema::de($talla, (int) $semilla);
    // El dinero nunca es float: un monto que no sea texto decimal, entero o nulo es un error de quien llama.
    if (! ($monto === null || is_string($monto) || is_int($monto))) {
        throw new \InvalidArgumentException('El monto de la gema va como texto decimal, entero o nulo; llegó '.get_debug_type($monto).'.');
    }
    $ancho = $tamano !== null ? (int) $tamano : \App\Modules\Crm\Oportunidades\Filament\EscalaDeGema::tamano($monto === null ? null : (string) $monto, (int) $minimo, (int) $maximo);
    $alto = (int) round($ancho * 0.88);
    $recorte = 'tq-gema-'.\Illuminate\Support\Str::lower(\Illuminate\Support\Str::random(12));
@endphp
@once
    <style>
        .tq-gema { display: inline-block; flex: none; overflow: visible; vertical-align: middle; }
        .tq-gema-brillante { filter: drop-shadow(0 0 6px rgba(0, 218, 255, 0.55)); }
        .tq-gema-fisurada { opacity: 0.85; }
        .tq-gema-reflejo { animation: tq-gema-reflejo 3.2s ease-in-out 2 both; }
        .tq-gema:hover .tq-gema-reflejo,
        [data-tq-gema-anfitrion]:is(:hover, :focus-visible) .tq-gema-reflejo { animation-name: tq-gema-reflejo-otra; }
        @keyframes tq-gema-reflejo { 0%, 55% { transform: translateX(-120px); } 100% { transform: translateX(140px); } }
        @keyframes tq-gema-reflejo-otra { 0%, 55% { transform: translateX(-120px); } 100% { transform: translateX(140px); } }
        .tq-gema-destello { transform-box: fill-box; transform-origin: center; opacity: 0.8; animation: tq-gema-destello 2.2s ease-in-out 3; }
        .tq-gema:hover .tq-gema-destello,
        [data-tq-gema-anfitrion]:is(:hover, :focus-visible) .tq-gema-destello { animation-name: tq-gema-destello-otra; }
        @keyframes tq-gema-destello { 0%, 100% { opacity: 0.8; transform: scale(1); } 30% { opacity: 0; transform: scale(0.3); } 60% { opacity: 1; transform: scale(1.1); } }
        @keyframes tq-gema-destello-otra { 0%, 100% { opacity: 0.8; transform: scale(1); } 30% { opacity: 0; transform: scale(0.3); } 60% { opacity: 1; transform: scale(1.1); } }
        @media (prefers-reduced-motion: reduce) {
            .tq-gema .tq-gema-reflejo { animation: none !important; visibility: hidden; }
            .tq-gema .tq-gema-destello { animation: none !important; }
        }
        html.tq-sin-animaciones .tq-gema .tq-gema-reflejo { animation: none !important; visibility: hidden; }
        html.tq-sin-animaciones .tq-gema .tq-gema-destello { animation: none !important; }
    </style>
@endonce
<svg {{ $attributes->class(['tq-gema', 'tq-gema-'.$talla->value]) }} width="{{ $ancho }}" height="{{ $alto }}" viewBox="0 0 100 88" data-talla="{{ $talla->value }}"
    @if ($decorativa) aria-hidden="true" focusable="false" @else role="img" aria-label="{{ $talla->textoAccesible() }}" @endif
>
    <defs><clipPath id="{{ $recorte }}"><polygon points="{{ $dibujo->contorno }}" /></clipPath></defs>
    <ellipse cx="{{ $dibujo->sombra['cx'] }}" cy="{{ $dibujo->sombra['cy'] }}" rx="{{ $dibujo->sombra['rx'] }}" ry="{{ $dibujo->sombra['ry'] }}" fill="{{ $dibujo->sombra['relleno'] }}" />
    @foreach ($dibujo->caras as $cara)
        <polygon points="{{ $cara['puntos'] }}" fill="{{ $cara['relleno'] }}" @if ($cara['trazo'] !== 'none') stroke="{{ $cara['trazo'] }}" stroke-width="{{ $cara['grosor'] }}" @endif />
    @endforeach
    <polygon points="{{ $dibujo->contorno }}" fill="none" stroke="{{ $dibujo->borde }}" stroke-width="1" />
    @foreach ($dibujo->grieta as $linea)
        <polyline points="{{ $linea['puntos'] }}" fill="none" stroke="#3F3F46" stroke-width="{{ $linea['grosor'] }}" stroke-linejoin="round" />
    @endforeach
    @if ($dibujo->reflejo)
        <g clip-path="url(#{{ $recorte }})"><g transform="skewX(-20)"><rect class="tq-gema-reflejo" x="-30" y="-10" width="22" height="110" fill="rgba(255,255,255,.55)" style="animation-delay: {{ $dibujo->retrasoReflejo }}" /></g></g>
    @endif
    @foreach ($dibujo->destellos as $destello)
        <path class="tq-gema-destello" d="{{ $destello['d'] }}" fill="#fff" style="animation-delay: {{ $destello['retraso'] }}" />
    @endforeach
</svg>
