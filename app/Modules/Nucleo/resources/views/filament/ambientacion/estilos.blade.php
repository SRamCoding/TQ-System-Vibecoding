{{--
    Ambientación de la fecha (plan 1.4b, D8; docs/convenciones/interfaz.md, «Ambientación de la fecha»): lo común a
    todos los dibujos. Cada variante trae sus propias animaciones, que solo corren dentro de `.tq-corre`: sin esa
    clase el dibujo queda quieto en su estado final. Con «reducir movimiento», nada se anima.
--}}
<style>
    .tq-amb { display: inline-flex; flex-shrink: 0; vertical-align: middle; cursor: default; }
    .tq-amb svg { width: 100%; height: 100%; overflow: visible; }
    /* Solo las piezas animadas (las que llevan clase): una pieza dibujada con transform="rotate(… cx cy)" gira sobre el
       lienzo; con fill-box giraría sobre sí misma y el dibujo se desarmaría. */
    .tq-amb svg [class] { transform-box: fill-box; }
    .tq-franja { height: 4px; width: 100%; }
    .tq-saludo { display: inline-flex; align-items: center; gap: 0.75rem; }
    /* La frase bajo el saludo, en cursiva (pedido del desarrollador, 2026-10-23). */
    .tq-frase { font-style: italic; }
    /* Plan 1.4e2, D3: 80 px junto al saludo; 64 en pantalla angosta. */
    @media (max-width: 640px) { .tq-saludo .tq-amb { width: 64px !important; height: 64px !important; } }
    @media (prefers-reduced-motion: reduce) {
        .tq-amb svg [class] { animation: none !important; }
    }
    /* La preferencia «reducir animaciones» (plan 1.4g, D5), aunque el equipo no lo pida. */
    html.tq-sin-animaciones .tq-amb svg [class] { animation: none !important; }
</style>
