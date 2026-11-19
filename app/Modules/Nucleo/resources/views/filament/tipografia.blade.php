{{--
    Escala de letra del sistema (plan 1.4ab, D1; pedido del desarrollador, 2026-11-15, con el prototipo de Proyectos
    aprobado: docs/diseno/fuentes/prototipos/proyectos-portada-ficha.html). La letra sigue siendo Inter, la de Filament;
    cambian tamaños, pesos y etiquetas, en todas las pantallas:
    - Títulos de página más compactos (1.5 rem, negrita) y de sección a 0.9375 rem.
    - Etiquetas de grupo (barra lateral) chicas, en mayúsculas y espaciadas, en gris.
    - Encabezados de tabla y etiquetas de los datos de una ficha, chicos y en gris: el dato manda, no su nombre.
    - Números alineados en columna (cifras tabulares) en tablas, datos de ficha y estadísticas.
    Además (D3), la línea que separa la barra superior del contenido, más fina: media línea en pantallas de alta
    densidad, con el mismo color tenue.
    Solo CSS sobre clases de Filament, sin colores escritos a mano salvo la línea (gris de Filament con transparencia).
--}}
<style>
    html .fi-header-heading { font-size: 1.5rem; line-height: 2rem; font-weight: 700; letter-spacing: -0.01em; }
    html .fi-section-header-heading { font-size: 0.9375rem; font-weight: 600; }
    html .fi-modal-heading { font-size: 1rem; font-weight: 600; }
    html .fi-sidebar-group-label { font-size: 0.6875rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; color: var(--gray-500); }
    html .fi-ta-header-cell, html .fi-ta-header-cell .fi-ta-header-cell-sort-btn { font-size: 0.75rem; font-weight: 500; color: var(--gray-500); }
    html .fi-in-entry-label { font-size: 0.75rem; font-weight: 500; color: var(--gray-500); }
    html .fi-ta-text, html .fi-in-text, html .fi-wi-stats-overview-stat-value, html table { font-variant-numeric: tabular-nums; }
    html .fi-wi-stats-overview-stat-value { letter-spacing: -0.01em; }
    html .fi-breadcrumbs { font-size: 0.8125rem; }

    /* D3: la línea bajo la barra superior, más fina. Filament la dibuja con una sombra (anillo + sombra corta). */
    html .fi-topbar { box-shadow: 0 0.5px 0 0 rgb(3 7 18 / 0.1); }
    html.dark .fi-topbar { box-shadow: 0 0.5px 0 0 rgb(255 255 255 / 0.08); }
</style>
