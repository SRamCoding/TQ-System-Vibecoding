{{--
    Un dibujo por piezas (plan 1.4e2): el cielo con la luz de la hora (mañana, tarde, noche), el sol o la luna, las capas
    de clima (todas dibujadas y escondidas: el navegador prende la que corresponde, D5), y encima el fondo, el motivo
    y el acompañante. Lienzo de 80 × 80. Recibe `$variante` (su `datos`: `combinacion`, `luz`, `clima_forzado`,
    `clima_activo`) y `$animado`. El clima lo pone la vista `nucleo::filament.clima` (plan 1.4u).
--}}
@php
    /** @var \App\Modules\Nucleo\Saludo\Piezas\CombinacionDibujo $combinacion */
    $combinacion = $variante->datos['combinacion'];
    $luz = $variante->datos['luz'];
    $id = 'tqc'.substr(md5($combinacion->clave().$luz), 0, 6);
    $cielo = match ($luz) {
        'manana' => ['#BFE4F6', '#EEF8FC'],
        'tarde' => ['#F4B97A', '#FCE6C8'],
        default => ['#1C2747', '#3A4778'],
    };
@endphp
<svg
    viewBox="0 0 80 80"
    aria-hidden="true"
    class="tq-compuesto"
    data-luz="{{ $luz }}"
    @if ($variante->datos['clima_forzado'] !== null) data-clima="{{ $variante->datos['clima_forzado'] }}" data-clima-forzado @endif
    @if ($variante->datos['clima_activo']) data-clima-activo @endif
>
    <style>
        .tq-compuesto [class^="tq-clima-"] { display: none; }
        .tq-compuesto[data-clima="nublado"] .tq-clima-nubes,
        .tq-compuesto[data-clima="lluvia"] .tq-clima-nubes, .tq-compuesto[data-clima="lluvia"] .tq-clima-lluvia,
        .tq-compuesto[data-clima="llovizna"] .tq-clima-nubes, .tq-compuesto[data-clima="llovizna"] .tq-clima-llovizna,
        .tq-compuesto[data-clima="niebla"] .tq-clima-niebla,
        .tq-compuesto[data-clima="tormenta"] .tq-clima-nubes-oscuras, .tq-compuesto[data-clima="tormenta"] .tq-clima-lluvia, .tq-compuesto[data-clima="tormenta"] .tq-clima-rayo { display: inline; }
        .tq-compuesto[data-clima="nublado"] .tq-astro, .tq-compuesto[data-clima="llovizna"] .tq-astro { opacity: 0.45; }
        .tq-compuesto[data-clima="lluvia"] .tq-astro, .tq-compuesto[data-clima="tormenta"] .tq-astro, .tq-compuesto[data-clima="niebla"] .tq-astro { opacity: 0.15; }
        .tq-cielo-gris { opacity: 0; }
        .tq-compuesto[data-clima="nublado"] .tq-cielo-gris { opacity: 0.18; }
        .tq-compuesto[data-clima="llovizna"] .tq-cielo-gris, .tq-compuesto[data-clima="niebla"] .tq-cielo-gris { opacity: 0.25; }
        .tq-compuesto[data-clima="lluvia"] .tq-cielo-gris { opacity: 0.38; }
        .tq-compuesto[data-clima="tormenta"] .tq-cielo-gris { opacity: 0.55; }
        .tq-compuesto[data-luz="tarde"] .tq-suelo { filter: saturate(1.1) sepia(0.12); }
        .tq-compuesto[data-luz="noche"] .tq-suelo { filter: brightness(0.62) saturate(0.8); }
        .tq-corre .tq-clima-lluvia line, .tq-corre .tq-clima-llovizna circle { animation: tq-cl-cae 0.9s linear 3 both; }
        .tq-corre .tq-clima-rayo { animation: tq-cl-rayo 1.6s ease-out both; }
        .tq-corre .tq-clima-nubes, .tq-corre .tq-clima-nubes-oscuras { animation: tq-cl-nubes 2.4s ease-out both; }
        .tq-corre .tq-astro-entra { animation: tq-cl-astro 1.2s ease-out both; }
        @keyframes tq-cl-cae { from { transform: translateY(-6px); opacity: 0; } 30% { opacity: 1; } to { transform: translateY(6px); opacity: 0.2; } }
        @keyframes tq-cl-rayo { 0%, 30%, 100% { opacity: 0; } 35%, 45% { opacity: 1; } 40% { opacity: 0.3; } }
        @keyframes tq-cl-nubes { from { transform: translateX(-5px); opacity: 0; } to { transform: none; opacity: 1; } }
        @keyframes tq-cl-astro { from { transform: translateY(5px); opacity: 0; } to { transform: none; opacity: 1; } }
    </style>
    <defs>
        <linearGradient id="{{ $id }}-cielo" x1="0" y1="0" x2="0" y2="1">
            <stop offset="0" stop-color="{{ $cielo[0] }}"/>
            <stop offset="1" stop-color="{{ $cielo[1] }}"/>
        </linearGradient>
    </defs>
    <rect width="80" height="80" rx="10" fill="url(#{{ $id }}-cielo)"/>
    {{-- El cielo se agrisa con el clima. --}}
    <rect class="tq-cielo-gris" width="80" height="80" rx="10" fill="#6B7280"/>

    {{-- El sol o la luna, y de noche las estrellas. --}}
    <g class="tq-astro"><g class="tq-astro-entra">
        @if ($luz === 'manana')
            <circle cx="62" cy="17" r="10" fill="#FFE9A8" opacity="0.6"/>
            <circle cx="62" cy="17" r="6.5" fill="#FFD45C"/>
        @elseif ($luz === 'tarde')
            <circle cx="60" cy="27" r="11" fill="#FFC38A" opacity="0.55"/>
            <circle cx="60" cy="27" r="7.5" fill="#F79A45"/>
        @else
            <circle cx="61" cy="16" r="6.5" fill="#F4F1DE"/>
            <circle cx="64.5" cy="13.5" r="5.5" fill="{{ $cielo[0] }}"/>
            @foreach ([[12, 10, 0.9], [26, 6, 0.6], [40, 14, 0.8], [18, 22, 0.5], [48, 6, 0.7], [72, 30, 0.6]] as [$x, $y, $o])
                <circle cx="{{ $x }}" cy="{{ $y }}" r="0.9" fill="#FFFFFF" opacity="{{ $o }}"/>
            @endforeach
        @endif
    </g></g>

    {{-- Capas de clima (D5): escondidas; las prende el navegador o el modo desarrollo. --}}
    <g class="tq-clima-nubes">
        <path d="M14 26a6 6 0 0 1 11-3 5 5 0 0 1 8 4H14z" fill="#FFFFFF" opacity="0.92"/>
        <path d="M46 20a7 7 0 0 1 13-3 6 6 0 0 1 9 5H46z" fill="#FFFFFF" opacity="0.95"/>
        <path d="M52 22h18a4 4 0 0 0-6-4 6 6 0 0 0-12 4z" fill="#E6EEF3"/>
    </g>
    <g class="tq-clima-nubes-oscuras">
        <path d="M10 26a7 7 0 0 1 13-3 6 6 0 0 1 9 5H10z" fill="#5B6478"/>
        <path d="M40 22a8 8 0 0 1 15-3 7 7 0 0 1 11 6H40z" fill="#4A5266"/>
    </g>
    <g class="tq-clima-lluvia" stroke="#5B9BD5" stroke-width="1.4" stroke-linecap="round">
        @foreach ([[16, 30], [24, 33], [32, 29], [46, 31], [54, 28], [62, 32], [70, 29]] as [$x, $y])
            <line x1="{{ $x }}" y1="{{ $y }}" x2="{{ $x - 2 }}" y2="{{ $y + 6 }}"/>
        @endforeach
    </g>
    <g class="tq-clima-llovizna" fill="#9CC5DF">
        @foreach ([[15, 31], [22, 35], [30, 30], [44, 33], [52, 29], [60, 34], [68, 30], [36, 37]] as [$x, $y])
            <circle cx="{{ $x }}" cy="{{ $y }}" r="0.8"/>
        @endforeach
    </g>
    <path class="tq-clima-rayo" d="M52 25l-4 8h4l-3 8 8-11h-4l3-5z" fill="#FFD45C"/>

    <g class="tq-suelo">
        @include($combinacion->fondo->vista)
        @include($combinacion->motivo->vista)
        @if ($combinacion->acompanante !== null)
            @include($combinacion->acompanante->vista)
        @endif
    </g>

    <g class="tq-clima-niebla" fill="#FFFFFF">
        <rect x="0" y="40" width="80" height="6" rx="3" opacity="0.55"/>
        <rect x="6" y="52" width="70" height="5" rx="2.5" opacity="0.5"/>
        <rect x="0" y="63" width="80" height="6" rx="3" opacity="0.45"/>
    </g>
</svg>
@if ($variante->datos['clima_activo'] && $variante->datos['clima_forzado'] === null)
    {{--
        El clima de donde está la computadora (D5). Lo consulta y lo pone en <html> la vista del encabezado
        `nucleo::filament.clima` (plan 1.4u), para todas las pantallas; aquí solo se copia al dibujo si Livewire lo vuelve
        a dibujar. Sin clima, el dibujo queda sin clima.
    --}}
    <script>
        (() => {
            const clima = document.documentElement.dataset.tqClima
            if (clima) document.querySelectorAll('.tq-compuesto[data-clima-activo]:not([data-clima-forzado]):not([data-clima])').forEach((d) => { d.dataset.clima = clima })
        })()
    </script>
@endif
