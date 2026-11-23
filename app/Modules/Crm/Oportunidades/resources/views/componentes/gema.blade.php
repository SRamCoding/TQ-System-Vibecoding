{{--
    La gema de una oportunidad (plan 2.21c, D3; MV-058: el dibujo del lienzo «Gema», de 32 × 32, cada talla más
    trabajada que la anterior): SVG en línea, sin imágenes. Uso:
    <x-oportunidades::gema :talla="TallaDeGema::desdeEtapa($fila->etapa)" :monto="$fila->montoEnSoles" :semilla="$fila->id" />
    - talla: TallaDeGema o su valor ('en_bruto'…'fisurada').
    - monto: en soles (texto decimal, entero o nulo; un float falla); el tamaño sale de EscalaDeGema entre `minimo` y `maximo` (píxeles de ancho).
    - tamano: un ancho fijo (tablas, encabezados, la ficha); manda sobre el monto.
    - semilla: ya no cambia el dibujo (MV-058); se acepta para no romper a quien la pasa.
    - decorativa: true si el texto de al lado ya dice la talla (aria-hidden); si no, role="img" con «Gema tallada».
    Ids únicos por gema: varias en la misma página no comparten sus degradados.
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
    // El dinero nunca es float: un monto que no sea texto decimal, entero o nulo es un error de quien llama.
    if (! ($monto === null || is_string($monto) || is_int($monto))) {
        throw new \InvalidArgumentException('El monto de la gema va como texto decimal, entero o nulo; llegó '.get_debug_type($monto).'.');
    }
    $ancho = $tamano !== null ? (int) $tamano : \App\Modules\Crm\Oportunidades\Filament\EscalaDeGema::tamano($monto === null ? null : (string) $monto, (int) $minimo, (int) $maximo);
    $id = 'tqg'.\Illuminate\Support\Str::lower(\Illuminate\Support\Str::random(8));
@endphp
<svg {{ $attributes->class(['tq-gema', 'tq-gema-'.$talla->value]) }} width="{{ $ancho }}" height="{{ $ancho }}" viewBox="0 0 32 32" data-talla="{{ $talla->value }}"
    @if ($decorativa) aria-hidden="true" focusable="false" @else role="img" aria-label="{{ $talla->textoAccesible() }}" @endif
    style="display: inline-block; flex: none; vertical-align: middle; overflow: visible">
    @switch ($talla->value)
        @case('en_roca')
            {{-- Observación 7.1: la roca sin abrir, gris y redondeada, con la veta turquesa que promete algo adentro. --}}
            <path d="M4.5 18.5 7 11.5 13 7.5 21 8 27 13 27.5 20.5 22 26 12 26.5 6 23.5z" fill="#6F6A66" stroke="#9C958F" stroke-width="1.2" stroke-linejoin="round"/>
            <path d="M9 13.5 12.5 12M19 23l3.5-1.5M8 20.5l2 1" stroke="#9C958F" stroke-width=".8" opacity=".6" stroke-linecap="round" fill="none"/>
            <path d="M8.5 17.5 13 15.5 15.5 17 19.5 13.5 23.5 14.5" stroke="#4DDDF5" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" fill="none"/>
            <path d="M15.5 17 16.5 20.5" stroke="#7FE9FF" stroke-width="1" stroke-linecap="round" fill="none"/>
            @break
        @case('en_bruto')
            <path d="M7 13.5 11.5 6.5 20 5.5 26.5 11.5 25 22 15.5 27 6 21z" fill="#4E6B72" stroke="#86A9B1" stroke-width="1.2" stroke-linejoin="round"/>
            <path d="M11.5 6.5 14 14 7 13.5M14 14l11 -2.5M14 14l1.5 13M14 14l-8 7" stroke="#86A9B1" stroke-width=".8" opacity=".55" fill="none"/>
            @break
        @case('desbastada')
            <path d="M8 11 12.5 6 19.5 6 24 11 23 21 16 27 9 21z" fill="#2E97A8" stroke="#7FD9E6" stroke-width="1.2" stroke-linejoin="round"/>
            <path d="M8 11h16M12.5 6 14 11M19.5 6 18 11M14 11l2 16M18 11l-2 16" stroke="#BFF1F8" stroke-width=".8" opacity=".55" fill="none"/>
            @break
        @case('tallada')
            <defs><linearGradient id="{{ $id }}" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#9CF1FF"/><stop offset="1" stop-color="#0FA9C6"/></linearGradient></defs>
            <path d="M5 12 10 6h12l5 6-11 15z" fill="url(#{{ $id }})" stroke="#E4FBFF" stroke-width="1" stroke-linejoin="round"/>
            <path d="M5 12h22M10 6l3 6 3-6 3 6 3-6M13 12l3 15 3-15" stroke="#E4FBFF" stroke-width=".8" opacity=".75" fill="none"/>
            @break
        @case('pulida')
            <defs><linearGradient id="{{ $id }}" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#E4FBFF"/><stop offset=".45" stop-color="#5FE3F7"/><stop offset="1" stop-color="#0B97B3"/></linearGradient></defs>
            <path d="M5 12 10 6h12l5 6-11 15z" fill="url(#{{ $id }})" stroke="#FFFFFF" stroke-width="1" stroke-linejoin="round"/>
            <path d="M5 12h22M10 6l3 6 3-6 3 6 3-6M13 12l3 15 3-15" stroke="#FFFFFF" stroke-width=".8" opacity=".85" fill="none"/>
            <path d="M11.5 8.2 13 7.2" stroke="#FFFFFF" stroke-width="1.4" stroke-linecap="round"/>
            @break
        @case('brillante')
            <defs>
                <linearGradient id="{{ $id }}" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#FFFFFF"/><stop offset=".5" stop-color="#B9F6FF"/><stop offset="1" stop-color="#36C9E4"/></linearGradient>
                <radialGradient id="{{ $id }}h" cx=".5" cy=".5" r=".5"><stop offset="0" stop-color="#9ED3A4" stop-opacity=".55"/><stop offset="1" stop-color="#9ED3A4" stop-opacity="0"/></radialGradient>
            </defs>
            <circle cx="16" cy="16" r="15.5" fill="url(#{{ $id }}h)"/>
            <path d="M5 12 10 6h12l5 6-11 15z" fill="url(#{{ $id }})" stroke="#FFFFFF" stroke-width="1" stroke-linejoin="round"/>
            <path d="M5 12h22M10 6l3 6 3-6 3 6 3-6M13 12l3 15 3-15" stroke="#FFFFFF" stroke-width=".8" opacity=".9" fill="none"/>
            <path d="M26.5 3.5v3.4M24.8 5.2h3.4M4.5 21.5v2.6M3.2 22.8h2.6" stroke="#FFFFFF" stroke-width="1.1" stroke-linecap="round"/>
            @break
        @default
            <path d="M5 12 10 6h12l5 6-11 15z" fill="#6B5A57" stroke="#B89089" stroke-width="1" stroke-linejoin="round"/>
            <path d="M5 12h22M13 12l3 15 3-15" stroke="#B89089" stroke-width=".8" opacity=".6" fill="none"/>
            <path d="M18.5 6.5 16.5 11l2.5 2.5-3 4 1.5 3" stroke="#F2A58C" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round" fill="none"/>
    @endswitch
</svg>
