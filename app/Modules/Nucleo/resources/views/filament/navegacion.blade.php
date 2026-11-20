{{--
    Barra superior y barra lateral (plan 1.3d, docs/convenciones/interfaz.md, «Barras»).
    - La barra superior, del logo al avatar, con fondo propio: perla en modo claro, gris azulado en modo oscuro
      (ColoresMarca). El buscador conserva su fondo. Con la apariencia según el día (plan 1.4ac), la barra y el fondo
      toman el tinte de la temperatura y la hora, y la barra lleva la luz del sol o la luna junto al buscador (LuzDelDia).
    - En modo oscuro, los grises de fondo, tarjetas y bordes suben a un gris carbón (plan 1.4t, ColoresMarca).
    - El ancho de la barra lateral es el que guardó la persona (`--sidebar-width`, desde el proveedor del panel). Si la
      guardó plegada, se le dice a Filament antes de que Alpine lea su estado del navegador: la base manda.
    - Sin la flecha de plegar de Filament: la barra se pliega solo arrastrando el borde (pedido del desarrollador).
    - El borde que se arrastra lo dibuja `nucleo::filament.borde-barra-lateral`, dentro de la barra.
    Barra de módulos (plan 1.4f, D5 a D7, BarraModulos):
    - El servidor dibuja todos los permitidos. Los ocultos llevan `tq-oculto`; un área sin ítems a la vista se esconde.
    - Apagada, `<html>` lleva `tq-barra-apagada` antes de dibujarse la página (sin parpadeo), y se esconde la barra con
      tres clases internas de Filament (`fi-sidebar`, `fi-topbar-open-sidebar-btn`, `fi-sidebar-close-overlay`;
      excepción anotada en docs/convenciones/interfaz.md). La barra y el contenido van en fila: al esconderla, el
      contenido ocupa el ancho solo.
    - En el celular (D9) se esconden la barra, su botón y sus ítems del menú; en escritorio, la casa (D10). El corte
      sale de BarraLateral::CORTE_ESCRITORIO.
    - Al guardar desde el menú del avatar llega el evento `tq-barra-modulos` y el script aplica lo mismo sin recargar;
      los ítems que recién se muestran entran uno por uno (D7). Con «reducir movimiento», sin animación.
--}}
@php
    $usuario = filament()->auth()->user();
    $navegacion = app(\App\Modules\Nucleo\Services\NavegacionService::class);
    $plegada = $usuario instanceof \App\Modules\Nucleo\Models\User && $navegacion->anchoBarra($usuario) === \App\Modules\Nucleo\Navegacion\BarraLateral::PLEGADA;
    $barraApagada = ! ($usuario instanceof \App\Modules\Nucleo\Models\User && $navegacion->barraVisible($usuario));
    $luz = $usuario instanceof \App\Modules\Nucleo\Models\User ? \App\Modules\Nucleo\Navegacion\LuzDelDia::para($usuario) : null;
    $estacionDeLaBarra = $usuario instanceof \App\Modules\Nucleo\Models\User ? \App\Modules\Nucleo\Navegacion\EstacionDeLaApariencia::para($usuario) : null;
    $deNoche = \App\Modules\Nucleo\Saludo\Piezas\RegistroPiezasDibujo::luz(\App\Modules\Nucleo\Desarrollo\ModoDesarrollo::ahoraDecorativo()) === \App\Modules\Nucleo\Enums\MomentoDia::Noche;
@endphp
@if ($deNoche && $estacionDeLaBarra !== null)
    {{-- Plan 1.4u, D2: de noche, sin sol (la luz de la hora del dibujo, con la hora decorativa). --}}
    <script>document.documentElement.dataset.tqNoche = ''</script>
@endif
<style>
    :root { --tq-barra-superior: {{ \App\Modules\Nucleo\Navegacion\ColoresMarca::PERLA }}; }
    html.dark { --tq-barra-superior: {{ \App\Modules\Nucleo\Navegacion\ColoresMarca::barraSuperiorOscura() }}; }
    /* El oscuro atenuado (plan 1.4t): gris carbón en vez de casi negro, en todas las pantallas. */
    html.dark { @foreach (\App\Modules\Nucleo\Navegacion\ColoresMarca::grisesModoOscuro() as $tono => $gris)--gray-{{ $tono }}: {{ $gris }}; @endforeach}
    html .fi-topbar { background-color: var(--tq-barra-superior); }
    /* Plan 1.4x, D7: con avisos sin leer (Filament pone su contador dentro del botón), la campana se enciende: rellena,
       del color principal y sobre un fondo suave; en oscuro, además, brilla. Fija: «ningún bucle». */
    html .fi-topbar-database-notifications-btn:has(.fi-icon-btn-badge-ctn) > .fi-icon path { fill: currentColor; }
    html .fi-topbar-database-notifications-btn:has(.fi-icon-btn-badge-ctn) { color: var(--primary-600); background-color: color-mix(in oklch, var(--primary-600) 13%, transparent); }
    html.dark .fi-topbar-database-notifications-btn:has(.fi-icon-btn-badge-ctn) { color: var(--primary-400); background-color: color-mix(in oklch, var(--primary-400) 16%, transparent); }
    html.dark .fi-topbar-database-notifications-btn:has(.fi-icon-btn-badge-ctn) > .fi-icon { filter: drop-shadow(0 0 3px color-mix(in oklch, var(--primary-400) 85%, transparent)) drop-shadow(0 0 9px color-mix(in oklch, var(--primary-400) 50%, transparent)); }
    @if ($estacionDeLaBarra !== null)
        /* El clima ajusta los efectos de la estación (plan 1.4u, D3). `--tq-sol` es la perilla de todos los reflejos:
           entera con despejado de día, más con calor, nada con nubes, niebla, lluvia o de noche; cambia con un fundido. */
        @property --tq-sol { syntax: '<number>'; inherits: true; initial-value: 1; }
        html { --tq-sol: 1; transition: --tq-sol 1.2s ease; }
        html[data-tq-temperatura="calor"] { --tq-sol: 1.35; }
        html:is([data-tq-noche], [data-tq-clima="nublado"], [data-tq-clima="niebla"], [data-tq-clima="llovizna"], [data-tq-clima="lluvia"], [data-tq-clima="tormenta"]) { --tq-sol: 0; }
        html.tq-sin-animaciones { transition: none; }
        @media (prefers-reduced-motion: reduce) { html { transition: none; } }
        html .fi-topbar { position: relative; isolation: isolate; }
        /* Lluvia: gotas quietas en la barra, como sobre un vidrio (más con lluvia y tormenta que con llovizna). Niebla: bruma. */
        html:is([data-tq-clima="llovizna"], [data-tq-clima="lluvia"], [data-tq-clima="tormenta"], [data-tq-clima="niebla"]) .fi-topbar::after { content: ''; position: absolute; inset: 0; z-index: -1; pointer-events: none; }
        html[data-tq-clima="llovizna"] .fi-topbar::after { background-image: radial-gradient(circle at 40% 35%, rgb(255 255 255 / 0.9) 0 0.7px, rgb(0 0 0 / 0.16) 1.2px 1.9px, transparent 2.3px); background-size: 53px 31px; background-position: 7px 5px; }
        html:is([data-tq-clima="lluvia"], [data-tq-clima="tormenta"]) .fi-topbar::after { background-image: radial-gradient(circle at 40% 35%, rgb(255 255 255 / 0.9) 0 0.8px, rgb(0 0 0 / 0.15) 1.3px 2.2px, transparent 2.6px), radial-gradient(circle at 40% 35%, rgb(255 255 255 / 0.85) 0 0.6px, rgb(0 0 0 / 0.15) 1.1px 1.6px, transparent 2.0px), radial-gradient(circle at 40% 35%, rgb(255 255 255 / 0.8) 0 0.7px, rgb(0 0 0 / 0.15) 1.2px 1.9px, transparent 2.3px), radial-gradient(circle at 40% 35%, rgb(255 255 255 / 0.9) 0 0.5px, rgb(0 0 0 / 0.15) 1.0px 1.4px, transparent 1.8px); background-size: 89px 41px, 131px 53px, 173px 37px, 61px 59px; background-position: 13px 6px, 71px 29px, 37px 17px, 5px 44px; }
        html.dark:is([data-tq-clima="llovizna"], [data-tq-clima="lluvia"], [data-tq-clima="tormenta"]) .fi-topbar::after { opacity: 0.6; }
        html[data-tq-clima="niebla"] .fi-topbar::after { background-image: linear-gradient(90deg, rgb(255 255 255 / 0.4), rgb(255 255 255 / 0.18) 50%, rgb(255 255 255 / 0.4)); }
        html.dark[data-tq-clima="niebla"] .fi-topbar::after { opacity: 0.25; }
    @endif
    @if ($luz !== null)
        /* La luz del día (plan 1.4ac, valores de los simuladores aprobados): el tinte del fondo y de la barra por la
           temperatura y la hora, y el halo del sol o de la luna arriba a la derecha, junto al buscador. Reemplaza al
           tinte por estación (1.4s) y al destello de primavera y verano (1.4t). */
        {!! $luz !!}
        /* El oscuro del simulador: logo lila y buscador translúcido. */
        html.dark .tq-logo-nombre { color: #C9C8FF; }
        html.dark .fi-global-search-field .fi-input-wrp { background-color: rgb(255 255 255 / 0.07); border-color: rgb(255 255 255 / 0.1); }
        html.dark .fi-global-search-field .fi-input { color: #A1A1AA; }
    @endif
    /* La barra se pliega solo arrastrando su borde: sin la flecha de plegar de Filament junto al logo. */
    html .fi-topbar-collapse-sidebar-btn-ctn { display: none; }

    .tq-borde-barra { display: none; }
    @media {{ \App\Modules\Nucleo\Navegacion\BarraLateral::consultaEscritorio() }} {
        .tq-borde-barra { display: block; position: absolute; inset-block: 0; inset-inline-end: -4px; z-index: 31; width: 8px; cursor: col-resize; touch-action: none; }
        .tq-borde-barra::after { content: ''; position: absolute; inset-block: 0; inset-inline-start: 3px; width: 2px; background: var(--gray-200); transition: background-color 0.15s; }
        .dark .tq-borde-barra::after { background: var(--gray-800); }
        .tq-borde-barra:hover::after, .tq-borde-barra.tq-arrastrando::after { background: var(--primary-500); }
        html.tq-arrastrando-barra, html.tq-arrastrando-barra * { cursor: col-resize !important; user-select: none !important; }
    }

    html.tq-barra-apagada .fi-sidebar,
    html.tq-barra-apagada .fi-topbar-open-sidebar-btn,
    html.tq-barra-apagada .fi-sidebar-close-overlay { display: none !important; }

    /* Plan 1.4f, D9: en el celular no hay barra ni sus ítems del menú; en escritorio, no hay casa. Las preferencias quedan
       intactas: solo no se aplican. `display: none` para que el teclado y los lectores tampoco lleguen. */
    @media {{ \App\Modules\Nucleo\Navegacion\BarraLateral::consultaCelular() }} {
        .fi-sidebar,
        .fi-topbar-open-sidebar-btn,
        .fi-sidebar-close-overlay,
        .tq-solo-escritorio { display: none !important; }
    }
    @media {{ \App\Modules\Nucleo\Navegacion\BarraLateral::consultaEscritorio() }} {
        .tq-solo-celular { display: none !important; }
    }
    [data-tq-barra].tq-oculto { display: none !important; }
    {{-- Las cajas de una subparte (los catálogos; después, portadas como la de Personas y empresas): el ícono va blanco
         en modo oscuro y, al pasar el mouse o con el foco del teclado, la caja entera crece un poco (y su ícono adentro).
         Las del inicio (`tq-caja-modulo`) crecen más, para distinguirlas (pedido del desarrollador, 2026-10-23). La que
         crece queda encima de sus vecinas, sin empujarlas. Quietas con animaciones reducidas. Comentario de Blade: no
         llega a la página (nombraría módulos a quien no los ve). --}}
    .tq-caja-sub, .tq-caja-sub .tq-caja-sub-icono { transition: transform 150ms ease-out; }
    .tq-caja-sub:hover, .tq-caja-sub:focus-visible { position: relative; z-index: 1; transform: scale(1.04); }
    .tq-caja-sub:hover .tq-caja-sub-icono, .tq-caja-sub:focus-visible .tq-caja-sub-icono { transform: scale(1.1); }
    .dark .tq-caja-sub .tq-caja-sub-icono { color: #FFFFFF; }
    @media (prefers-reduced-motion: reduce) {
        .tq-caja-sub, .tq-caja-sub .tq-caja-sub-icono { transition: none; transform: none !important; }
    }
    html.tq-sin-animaciones .tq-caja-sub, html.tq-sin-animaciones .tq-caja-sub .tq-caja-sub-icono { transition: none; transform: none !important; }
    /* «Barra de módulos» en el menú de la foto: un interruptor típico. Prendido, en el azul del tema (700 en modo claro,
       400 en oscuro, como el resto del azul); apagado, con el color de los demás íconos. Al pasar el mouse, la perilla
       amaga ir al otro lado. */
    .tq-interruptor-prendido .fi-icon { color: var(--primary-700); }
    .dark .tq-interruptor-prendido .fi-icon { color: var(--primary-400); }
    /* Plan 1.4v (pedido del desarrollador, 2026-10-28): al pasar el mouse, el ícono de un módulo de la barra crece como el de
       las cajas del inicio. Con «reducir movimiento» o «reducir animaciones», quieto. */
    .fi-sidebar-item-btn > .fi-icon { transition: transform 180ms ease-out; }
    .fi-sidebar-item-btn:is(:hover, :focus-visible) > .fi-icon { transform: scale(1.2); }
    @media (prefers-reduced-motion: reduce) {
        .fi-sidebar-item-btn > .fi-icon { transition: none; transform: none !important; }
    }
    html.tq-sin-animaciones .fi-sidebar-item-btn > .fi-icon { transition: none; transform: none !important; }
    .tq-interruptor .tq-interruptor-perilla { transition: transform 150ms ease-out; }
    .tq-interruptor:hover .tq-interruptor-perilla { transform: translateX(1.5px); }
    .tq-interruptor-prendido:hover .tq-interruptor-perilla { transform: translateX(-1.5px); }
    @media (prefers-reduced-motion: reduce) {
        .tq-interruptor .tq-interruptor-perilla { transition: none; transform: none !important; }
    }
    html.tq-sin-animaciones .tq-interruptor .tq-interruptor-perilla { transition: none; transform: none !important; }
    /* «Mien» (plan 1.4l, D2): en la barra, solo su ícono va dorado, también sin abrirlo. */
    [data-modulo="mien"] .fi-icon { color: var(--dorado-600); }
    .dark [data-modulo="mien"] .fi-icon { color: var(--dorado-400); }
    /* El módulo abierto se pinta con el color de su área, no siempre con el azul (pedido del desarrollador,
       2026-10-23): Personal dorado, Núcleo azul noche (el de Filament), CRM morado, ERP turquesa. Mismos tonos que
       Filament usa para el azul: el 700 en claro (el 600 del dorado, que es el exacto) y el 400 en oscuro. */
    [data-tq-area="personal"] { --tq-area-activo: var(--dorado-600); --tq-area-activo-oscuro: var(--dorado-400); }
    [data-tq-area="crm"] { --tq-area-activo: var(--morado-700); --tq-area-activo-oscuro: {{ \App\Modules\Nucleo\Navegacion\ColoresMarca::LILA_OSCURO }}; }
    [data-tq-area="erp"] { --tq-area-activo: {{ \App\Modules\Nucleo\Navegacion\ColoresMarca::TURQUESA_TEXTO }}; --tq-area-activo-oscuro: {{ \App\Modules\Nucleo\Navegacion\ColoresMarca::TURQUESA }}; }
    /* MV-033: Estratégicos (azul noche) y Plataforma (gris), también en la barra. */
    [data-tq-area="nucleo"] { --tq-area-activo: {{ \App\Modules\Nucleo\Navegacion\ColoresMarca::AZUL_NOCHE }}; --tq-area-activo-oscuro: {{ \App\Modules\Nucleo\Navegacion\ColoresMarca::AZUL_NOCHE_OSCURO }}; }
    [data-tq-area="plataforma"] { --tq-area-activo: {{ \App\Modules\Nucleo\Navegacion\ColoresMarca::GRIS_PLATAFORMA }}; --tq-area-activo-oscuro: {{ \App\Modules\Nucleo\Navegacion\ColoresMarca::GRIS_PLATAFORMA_OSCURO }}; }
    [data-tq-area] .fi-sidebar-item.fi-active > .fi-sidebar-item-btn > :is(.fi-icon, .fi-sidebar-item-label) { color: var(--tq-area-activo); }
    .dark [data-tq-area] .fi-sidebar-item.fi-active > .fi-sidebar-item-btn > :is(.fi-icon, .fi-sidebar-item-label) { color: var(--tq-area-activo-oscuro); }
    [data-tq-area]:not(:has([data-tq-barra]:not(.tq-oculto))) { display: none !important; }

    @keyframes tq-entrar-barra { from { opacity: 0; transform: translateY(-6px); } to { opacity: 1; transform: none; } }
    [data-tq-barra].tq-entrando { animation: tq-entrar-barra 200ms ease-out both; animation-delay: var(--tq-retraso, 0ms); }
    @media (prefers-reduced-motion: reduce) {
        [data-tq-barra].tq-entrando { animation: none; }
    }
    /* La preferencia «reducir animaciones» (plan 1.4g, D5), aunque el equipo no lo pida. */
    html.tq-sin-animaciones [data-tq-barra].tq-entrando { animation: none; }
</style>
@if ($barraApagada)
    <script>document.documentElement.classList.add('tq-barra-apagada')</script>
@endif
<script>
    window.addEventListener(@js(\App\Modules\Nucleo\Filament\BarraModulos::EVENTO), (evento) => {
        const { visible, ocultos } = evento.detail ?? {}
        const raiz = document.documentElement
        const items = [...document.querySelectorAll('[data-tq-barra]')]
        const aLaVista = (item) => ! raiz.classList.contains('tq-barra-apagada') && ! item.classList.contains('tq-oculto')
        const antes = new Set(items.filter(aLaVista))

        raiz.classList.toggle('tq-barra-apagada', ! visible)
        items.forEach((item) => item.classList.toggle('tq-oculto', (ocultos ?? []).includes(item.dataset.modulo)))

        // D7: entran de arriba abajo los que recién se ven; el último termina antes de 600 ms aunque sean muchos.
        const entran = items.filter((item) => aLaVista(item) && ! antes.has(item))
        const paso = entran.length > 1 ? Math.min(60, 400 / (entran.length - 1)) : 0

        entran.forEach((item, i) => {
            item.style.setProperty('--tq-retraso', Math.round(i * paso) + 'ms')
            item.classList.remove('tq-entrando')
            void item.offsetWidth
            item.classList.add('tq-entrando')
            item.addEventListener('animationend', () => item.classList.remove('tq-entrando'), { once: true })
        })
    })
</script>
@if ($usuario !== null)
    <script>
        if (window.innerWidth >= @js(\App\Modules\Nucleo\Navegacion\BarraLateral::CORTE_ESCRITORIO)) {
            try {
                localStorage.setItem('_x_isOpen', @js(json_encode(! $plegada)));
                localStorage.setItem('_x_isOpenDesktop', @js(json_encode(! $plegada)));
            } catch (e) {}
        }
    </script>
@endif
