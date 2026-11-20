{{--
    MV-032: Mien sin caja: una estrella dorada grande y centrada, con su halo que late, tres destellos y el número de lo
    que espera a la persona. Toda la estrella es un enlace a Mien. Sin pendientes: sin halo ni número, más pálida.
    En la barra lateral, Mien sigue con su ícono de siempre.
--}}
@php
    /** @var \App\Modules\Nucleo\Navegacion\CajaModulo $caja */
    /** @var array{total: int, resumen: string} $pendientes */
    $alDia = $pendientes['total'] === 0;
    $id = 'tqm'.substr(md5(uniqid('', true)), 0, 6);
    $punta = 'M56 6l14.7 29.8 32.9 4.8-23.8 23.2 5.6 32.7L56 81 26.6 96.5l5.6-32.7L8.4 40.6l32.9-4.8z';
    $aria = 'Mien: '.($alDia ? 'todo al día' : $pendientes['resumen']);
@endphp
<div class="tq-mien-centro">
    <style>
        .tq-mien-centro { display: flex; justify-content: center; margin-bottom: 0.5rem; }
        .tq-mien-estrella { --tq-oro: {{ \App\Modules\Nucleo\Navegacion\ColoresMarca::DORADO }}; display: flex; flex-direction: column; align-items: center; gap: 0.25rem; text-decoration: none; border-radius: 1rem; padding: 0.25rem 1rem; }
        .dark .tq-mien-estrella { --tq-oro: {{ \App\Modules\Nucleo\Navegacion\ColoresMarca::DORADO_OSCURO }}; }
        .tq-mien-estrella:focus-visible { outline: 2px solid var(--tq-oro); outline-offset: 4px; }
        .tq-mien-dibujo { position: relative; width: 168px; height: 150px; display: grid; place-items: center; }
        .tq-mien-halo { position: absolute; inset: -9px 0 0; margin: auto; width: 168px; height: 168px; border-radius: 9999px;
            background: radial-gradient(circle, color-mix(in oklab, #F9D77E 70%, transparent) 0%, color-mix(in oklab, #F2C14E 30%, transparent) 40%, transparent 70%);
            animation: tq-mien-halo 4s ease-in-out infinite; transition: filter 200ms ease, opacity 200ms ease; }
        .dark .tq-mien-halo { opacity: 0.7; }
        @keyframes tq-mien-halo { 50% { transform: scale(1.08); filter: brightness(1.15); } }
        .tq-mien-svg { position: relative; width: 112px; height: 112px; transition: transform 180ms ease-out; filter: drop-shadow(0 4px 8px color-mix(in oklab, #D9A21F 35%, transparent)); }
        .tq-mien-estrella:is(:hover, :focus-visible) .tq-mien-svg { transform: scale(1.06); }
        .tq-mien-estrella:is(:hover, :focus-visible) .tq-mien-halo { filter: brightness(1.3); }
        .tq-mien-destello { position: absolute; width: 14px; height: 14px; color: #F2C14E; animation: tq-mien-destello 3s ease-in-out infinite; }
        @keyframes tq-mien-destello { 0%, 100% { opacity: 0.2; transform: scale(0.7); } 50% { opacity: 1; transform: scale(1); } }
        .tq-mien-numero { position: absolute; top: 18px; right: 26px; min-width: 1.75rem; height: 1.75rem; padding: 0 0.4rem; border-radius: 9999px; display: grid; place-items: center;
            font-size: 0.85rem; font-weight: 800; color: white; background: {{ \App\Modules\Nucleo\Navegacion\ColoresMarca::TINTA }}; box-shadow: 0 0 0 2px white; }
        .dark .tq-mien-numero { color: {{ \App\Modules\Nucleo\Navegacion\ColoresMarca::TINTA }}; background: {{ \App\Modules\Nucleo\Navegacion\ColoresMarca::DORADO_OSCURO }}; box-shadow: 0 0 0 2px var(--gray-900); }
        .tq-mien-nombre { font-size: 1.25rem; font-weight: 800; color: var(--tq-oro); }
        .tq-mien-resumen { font-size: 0.875rem; color: var(--gray-500); }
        .dark .tq-mien-resumen { color: var(--gray-400); }
        .tq-mien-estrella.tq-al-dia .tq-mien-svg { opacity: 0.55; filter: saturate(0.6); }
        @media (prefers-reduced-motion: reduce) { .tq-mien-halo, .tq-mien-destello { animation: none; } .tq-mien-svg { transition: none; } }
        html.tq-sin-animaciones .tq-mien-halo, html.tq-sin-animaciones .tq-mien-destello { animation: none; }
        html.tq-sin-animaciones .tq-mien-svg { transition: none; }
    </style>
    <a href="{{ $caja->url }}" wire:navigate @class(['tq-mien-estrella', 'tq-al-dia' => $alDia]) data-modulo="mien" aria-label="{{ $aria }}">
        <span class="tq-mien-dibujo" aria-hidden="true">
            @unless ($alDia)
                <span class="tq-mien-halo"></span>
            @endunless
            <svg class="tq-mien-svg" viewBox="0 0 112 104" focusable="false">
                <defs><linearGradient id="{{ $id }}" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#F9D77E"/><stop offset="1" stop-color="#D9A21F"/></linearGradient></defs>
                <path d="{{ $punta }}" fill="url(#{{ $id }})" stroke="var(--tq-oro)" stroke-width="2" stroke-linejoin="round"/>
                {{-- El brillo pequeño en la punta de arriba. --}}
                <path d="M56 14l4 9" stroke="#fff" stroke-width="3" stroke-linecap="round" opacity=".75"/>
            </svg>
            @foreach ([['16px', '28px', '0s'], ['auto', '22px', '1s'], ['30px', 'auto', '2s']] as $n => [$top, $lado, $retraso])
                <svg class="tq-mien-destello" viewBox="0 0 24 24" fill="currentColor" focusable="false"
                    style="{{ $n === 0 ? "top: {$top}; left: {$lado};" : ($n === 1 ? "bottom: 18px; right: {$lado};" : "top: 64px; right: 8px;") }} animation-delay: {{ $retraso }}">
                    <path d="M12 0c.7 5.6 2.4 7.3 8 8-5.6.7-7.3 2.4-8 8-.7-5.6-2.4-7.3-8-8 5.6-.7 7.3-2.4 8-8z" transform="translate(0 4)"/>
                </svg>
            @endforeach
            @unless ($alDia)
                <span class="tq-mien-numero">{{ $pendientes['total'] }}</span>
            @endunless
        </span>
        <span class="tq-mien-nombre">Mien</span>
        <span class="tq-mien-resumen">{{ $pendientes['resumen'] }}</span>
    </a>
</div>
