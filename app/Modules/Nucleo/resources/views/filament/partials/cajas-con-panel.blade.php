{{--
    Estilos de las pantallas que abren con cajas agrupadas, un buscador y un panel que se desliza desde la derecha encima
    (plan 1.4j, Catálogos; plan 1.10, Alertas). Las clases conservan el nombre con que nacieron en Catálogos.
--}}
<style>
        .tq-catalogos-buscar { width: 100%; max-width: 24rem; margin-bottom: 1.25rem; }
        .tq-catalogos-grupo + .tq-catalogos-grupo { margin-top: 1.5rem; }
        .tq-catalogos-grupo-titulo { margin-bottom: 0.75rem; font-size: 0.95rem; font-weight: 700; color: var(--primary-600); }
        .dark .tq-catalogos-grupo-titulo { color: var(--primary-400); }
        .tq-catalogos-cajas { display: grid; gap: 1rem; grid-template-columns: repeat(auto-fill, 9rem); grid-auto-rows: 7.5rem; } /* MV-034: el alto de dos líneas de nombre */
        .tq-caja-catalogo { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 0.5rem; height: 100%; min-width: 0; overflow: hidden; padding: 0.75rem; text-align: center; border-top: 3px solid var(--primary-600); }
        .dark .tq-caja-catalogo { border-top-color: var(--primary-400); }
        .tq-caja-catalogo:hover { --tw-ring-color: var(--primary-600); }
        .tq-caja-catalogo-icono { flex: none; width: 2rem; height: 2rem; color: var(--primary-600); }
        .tq-caja-catalogo-nombre { display: -webkit-box; -webkit-box-orient: vertical; -webkit-line-clamp: 2; line-clamp: 2; overflow: hidden; font-size: 0.875rem; font-weight: 600; line-height: 1.25rem; }
        .tq-catalogos-nada { font-size: 0.875rem; color: var(--gray-500); }
        .tq-catalogos { position: relative; }
        .tq-catalogos > div:first-of-type { transition: opacity 220ms ease-out; }
        .tq-catalogos-detras { opacity: 0.45; }
        .tq-catalogo-panel { position: absolute; top: 0; right: 0; z-index: 10; width: min(100%, 60rem); padding: 1.25rem; border-radius: 0.75rem; background: var(--color-white, #fff); box-shadow: -8px 0 28px rgb(0 0 0 / 0.18); animation: tq-catalogo-entra 200ms ease-out both; }
        .dark .tq-catalogo-panel { background: var(--gray-900); box-shadow: -8px 0 28px rgb(0 0 0 / 0.5); }
        .tq-catalogo-panel-encabezado { display: flex; align-items: center; justify-content: space-between; gap: 1rem; margin-bottom: 1rem; }
        .tq-catalogo-panel-titulo { display: flex; align-items: center; gap: 0.5rem; font-size: 1.125rem; font-weight: 700; }
        .tq-catalogo-panel-titulo .fi-icon { color: var(--primary-600); }
        .dark .tq-catalogo-panel-titulo .fi-icon { color: #FFFFFF; }
        .tq-catalogo-panel.tq-saliendo { animation: tq-catalogo-sale 200ms ease-in both; }
        @keyframes tq-catalogo-entra { from { opacity: 0; transform: translateX(3rem); } to { opacity: 1; transform: none; } }
        @keyframes tq-catalogo-sale { from { opacity: 1; transform: none; } to { opacity: 0; transform: translateX(3rem); } }
        @media (prefers-reduced-motion: reduce) { .tq-catalogo-panel, .tq-catalogo-panel.tq-saliendo { animation: none; } .tq-catalogos > div:first-of-type { transition: none; } }
        html.tq-sin-animaciones .tq-catalogo-panel, html.tq-sin-animaciones .tq-catalogo-panel.tq-saliendo { animation: none; }
        html.tq-sin-animaciones .tq-catalogos > div:first-of-type { transition: none; }
</style>
