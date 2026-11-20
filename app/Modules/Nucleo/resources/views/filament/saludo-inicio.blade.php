{{--
    El encabezado del inicio (plan 1.4b; MV-032): centrado, la ventana con la escena del momento del día y, debajo, el
    saludo grande. Recibe `$saludo` y `$variante` (el dibujo de una fecha especial, o nulo).

    La ventana (168 × 180, arco de medio punto, alféizar, dos travesaños y un parante) recorta la escena. La escena sale
    de la transición del cielo (t3b, `LuzDelDia::fase`): amanecer, día, atardecer o noche; con nubes o lluvia según el
    clima de la sede (`html[data-tq-clima]`). En modo oscuro, siempre la noche y el marco en madera clara. El dibujo de
    una fecha especial se ve dentro de la ventana. Decorativa: `aria-hidden`.
--}}
@php
    $fase = \App\Modules\Nucleo\Navegacion\LuzDelDia::fase(\App\Modules\Nucleo\Desarrollo\ModoDesarrollo::ahoraDecorativo());
    $id = 'tqv'.substr(md5(uniqid('', true)), 0, 6);
    $forma = 'M14 172V84a70 70 0 0 1 140 0v88z';
@endphp
<span class="tq-saludo">
    <style>
        .fi-header:has(.tq-saludo) { justify-content: center; text-align: center; }
        .fi-header:has(.tq-saludo) > div { width: 100%; display: flex; flex-direction: column; align-items: center; }
        .fi-header:has(.tq-saludo) .fi-header-heading { width: 100%; }
        .tq-saludo { display: flex; flex-direction: column; align-items: center; gap: 0.75rem; }
        .tq-saludo-texto { font-size: 2.5rem; line-height: 1.15; font-weight: 800; }
        @media (max-width: 640px) { .tq-saludo-texto { font-size: 1.9rem; } }
        .fi-header:has(.tq-saludo) .fi-header-subheading { max-width: 620px; margin-inline: auto; font-style: italic; color: var(--gray-500); }
        .dark .fi-header:has(.tq-saludo) .fi-header-subheading { color: var(--gray-400); }
        .tq-ventana { position: relative; width: 168px; height: 180px; flex: none; }
        .tq-ventana svg { position: absolute; inset: 0; }
        .tq-ventana [data-escena] { display: none; }
        html:not(.dark) .tq-ventana[data-fase="amanecer"] [data-escena="amanecer"],
        html:not(.dark) .tq-ventana[data-fase="dia"] [data-escena="dia"],
        html:not(.dark) .tq-ventana[data-fase="atardecer"] [data-escena="atardecer"],
        html:not(.dark) .tq-ventana[data-fase="noche"] [data-escena="noche"],
        html.dark .tq-ventana [data-escena="noche"] { display: inline; }
        .tq-ventana-marco { stroke: #6B4A2E; }
        html.dark .tq-ventana-marco { stroke: #A07850; }
        .tq-ventana-nubes, .tq-ventana-lluvia { display: none; }
        html:is([data-tq-clima="nublado"], [data-tq-clima="niebla"], [data-tq-clima="llovizna"], [data-tq-clima="lluvia"], [data-tq-clima="tormenta"]) .tq-ventana-nubes { display: inline; }
        html:is([data-tq-clima="llovizna"], [data-tq-clima="lluvia"], [data-tq-clima="tormenta"]) .tq-ventana-lluvia { display: inline; }
        .tq-ventana-sol { animation: tq-ventana-sol 6s ease-in-out infinite; transform-box: fill-box; }
        @keyframes tq-ventana-sol { 50% { transform: translateY(-4px); } }
        .tq-ventana-titila { animation: tq-ventana-titila 3s ease-in-out infinite; }
        @keyframes tq-ventana-titila { 50% { opacity: 0.35; } }
        @media (prefers-reduced-motion: reduce) { .tq-ventana * { animation: none !important; } }
        html.tq-sin-animaciones .tq-ventana * { animation: none !important; }
        .tq-ventana-fiesta { position: absolute; inset: 0; display: flex; align-items: flex-end; justify-content: center; padding-bottom: 14px; clip-path: path('{{ $forma }}'); }
    </style>

    <span class="tq-ventana" data-fase="{{ $fase }}" aria-hidden="true">
        <svg width="168" height="180" viewBox="0 0 168 180" fill="none" stroke-linecap="round" stroke-linejoin="round" focusable="false">
            <defs>
                <clipPath id="{{ $id }}-forma"><path d="{{ $forma }}"/></clipPath>
                <linearGradient id="{{ $id }}-dia" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#BFE3F5"/><stop offset="1" stop-color="#FCEFD2"/></linearGradient>
                <linearGradient id="{{ $id }}-amanecer" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#9DB8E8"/><stop offset=".55" stop-color="#F7C6B0"/><stop offset="1" stop-color="#FFE3A8"/></linearGradient>
                <linearGradient id="{{ $id }}-atardecer" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#6E5AA8"/><stop offset=".5" stop-color="#F08A6C"/><stop offset="1" stop-color="#FFC27A"/></linearGradient>
                <linearGradient id="{{ $id }}-noche" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#0B1640"/><stop offset="1" stop-color="#26346E"/></linearGradient>
            </defs>
            <g clip-path="url(#{{ $id }}-forma)">
                {{-- Día: la escena de la propuesta. --}}
                <g data-escena="dia">
                    <rect width="168" height="180" fill="url(#{{ $id }}-dia)"/>
                    <g class="tq-ventana-sol"><circle cx="84" cy="92" r="18" fill="#F7C948"/><circle cx="84" cy="92" r="27" fill="#F7C948" opacity=".25"/></g>
                    <path d="M38 50c3-5 11-6 15-1 5-1 9 3 8 7H34c-1-3 1-6 4-6z" fill="#fff" opacity=".85"/>
                    <path d="M0 136c26-14 52-16 78-6s56 10 90-4v54H0z" fill="#9CC9A0"/>
                    <path d="M0 152c30-10 60-10 88-2s52 6 80-2v32H0z" fill="#7DB582"/>
                </g>
                {{-- Amanecer: el sol asomando a la izquierda, el cielo rosado. --}}
                <g data-escena="amanecer">
                    <rect width="168" height="180" fill="url(#{{ $id }}-amanecer)"/>
                    <g class="tq-ventana-sol"><circle cx="54" cy="128" r="18" fill="#FFB347"/><circle cx="54" cy="128" r="28" fill="#FFB347" opacity=".25"/></g>
                    <path d="M0 136c26-14 52-16 78-6s56 10 90-4v54H0z" fill="#8FB89A"/>
                    <path d="M0 152c30-10 60-10 88-2s52 6 80-2v32H0z" fill="#6FA37C"/>
                </g>
                {{-- Atardecer: el sol bajando a la derecha, cielo naranja y morado. --}}
                <g data-escena="atardecer">
                    <rect width="168" height="180" fill="url(#{{ $id }}-atardecer)"/>
                    <g class="tq-ventana-sol"><circle cx="116" cy="124" r="19" fill="#FF8C42"/><circle cx="116" cy="124" r="29" fill="#FF8C42" opacity=".25"/></g>
                    <path d="M0 136c26-14 52-16 78-6s56 10 90-4v54H0z" fill="#7C6A8A"/>
                    <path d="M0 152c30-10 60-10 88-2s52 6 80-2v32H0z" fill="#5E4E70"/>
                </g>
                {{-- Noche: cielo azul profundo, luna y estrellas. --}}
                <g data-escena="noche">
                    <rect width="168" height="180" fill="url(#{{ $id }}-noche)"/>
                    <path d="M108 52a16 16 0 1 0 14 24 13 13 0 1 1-14-24z" fill="#F4F1DE"/>
                    <g fill="#fff">
                        <circle class="tq-ventana-titila" cx="40" cy="48" r="1.6"/>
                        <circle class="tq-ventana-titila" cx="66" cy="34" r="1.2" style="animation-delay: 1s"/>
                        <circle class="tq-ventana-titila" cx="58" cy="80" r="1.4" style="animation-delay: 2s"/>
                        <circle class="tq-ventana-titila" cx="132" cy="100" r="1.2" style="animation-delay: .5s"/>
                        <circle class="tq-ventana-titila" cx="30" cy="104" r="1.1" style="animation-delay: 1.5s"/>
                    </g>
                    <path d="M0 136c26-14 52-16 78-6s56 10 90-4v54H0z" fill="#1E2B4F"/>
                    <path d="M0 152c30-10 60-10 88-2s52 6 80-2v32H0z" fill="#172242"/>
                </g>
                {{-- El clima de la sede, si el sistema lo sabe. --}}
                <g class="tq-ventana-nubes" fill="#fff" opacity=".8">
                    <path d="M30 60c4-8 16-9 21-2 7-2 13 4 11 10H26c-2-5 0-8 4-8z"/>
                    <path d="M92 44c4-7 14-8 19-2 6-1 11 4 10 9H88c-1-4 1-7 4-7z"/>
                </g>
                <g class="tq-ventana-lluvia" stroke="#9EC3E6" stroke-width="2">
                    <path d="M40 80l-4 10M64 74l-4 10M90 82l-4 10M116 76l-4 10M52 100l-4 10M102 98l-4 10"/>
                </g>
            </g>
            <path class="tq-ventana-marco" d="{{ $forma }}" stroke-width="5"/>
            <path class="tq-ventana-marco" d="M14 84h140M84 14v158M14 128h140" stroke-width="3"/>
            <path class="tq-ventana-marco" d="M6 176h156" stroke-width="5"/>
        </svg>
        @if ($variante !== null)
            {{-- La fecha especial, dentro de la ventana. En modo desarrollo, un clic sobre el dibujo deja cambiarlo (plan 1.4i, D3). --}}
            <span class="tq-ventana-fiesta" @if (\App\Modules\Nucleo\Desarrollo\ModoDesarrollo::activo()) data-tq-pieza="{{ \App\Modules\Nucleo\Desarrollo\Piezas\DibujoDelSaludo::CLAVE }}" title="Cambiar el dibujo" @endif>
                @include('nucleo::filament.ambientacion.dibujo', ['variante' => $variante, 'animado' => true, 'tamano' => 80])
            </span>
        @endif
    </span>
    <span class="tq-saludo-texto" lang="{{ $saludo->idioma->value }}">{{ $saludo->saludo }}</span>
</span>
