{{--
    MV-059: todos los colores siguen al ambiente (clima, hora y estación), como en el lienzo «Colores que siguen al
    ambiente» (`docs/vibe/pedidos/lienzos/ambiente/`). Tres piezas:
    1. Las superficies nacen del fondo del ambiente (`--tq-luz-fondo`, de LuzDelDia): superficie, borde, superficie
       elevada y textos se calculan con `color-mix(in oklch, …)`; nada de colores escritos a mano.
    2. Los colores de las franjas y el azul de resaltado se mezclan con el tinte del ambiente (frío de mañana o de noche,
       cálido al atardecer: sale de `--tq-calor`) al 13 %.
    3. Vidrio en lo que flota: muelle, globo de Mien, agenda, paneles deslizantes, ventanas y menús.
    Sin la apariencia según el día, el fondo es el de siempre y no hay tinte. No cambian los íconos de aplicación, el logo
    del encabezado ni las ilustraciones.
--}}
@php
    $usuario = auth()->user();
    $conAmbiente = $usuario instanceof \App\Modules\Nucleo\Models\User && \App\Modules\Nucleo\Navegacion\EstacionDeLaApariencia::para($usuario) !== null;
    $C = \App\Modules\Nucleo\Navegacion\ColoresMarca::class;
@endphp
<style>
    html:root {
        /* El tinte del ambiente: de frío (#8FB8FF, mañana o invierno) a cálido (#FFB36B, atardecer o verano) según el calor. */
        --tq-tinte: color-mix(in oklch, #FFB36B calc(var(--tq-calor, 0.5) * 100%), #8FB8FF);
        --tq-tinte-p: {{ $conAmbiente ? '13%' : '0%' }};
        --tq-fondo: var(--tq-luz-fondo, var(--gray-50));
        /* Modo claro: las superficies van hacia el blanco (si no, se ven como huecos); bordes y textos, hacia el negro. */
        --tq-superficie: color-mix(in oklch, var(--tq-fondo) 30%, #FFFFFF);
        --tq-superficie-elevada: color-mix(in oklch, var(--tq-fondo) 12%, #FFFFFF);
        --tq-borde: color-mix(in oklch, var(--tq-fondo) 86%, #000000 14%);
        --tq-texto-suave: color-mix(in oklch, var(--tq-fondo) 40%, #000000 60%);
        --tq-texto: color-mix(in oklch, {{ $C::TINTA }} 94%, var(--tq-tinte) 6%);
        --tq-vidrio: color-mix(in oklch, var(--tq-fondo) 70%, transparent);
        /* Los colores de las franjas y el resaltado, con temperatura. */
        --tq-franja-misionales: color-mix(in oklch, {{ $C::TURQUESA_TEXTO }} calc(100% - var(--tq-tinte-p)), var(--tq-tinte));
        --tq-franja-estrategicos: color-mix(in oklch, {{ $C::AZUL_NOCHE }} calc(100% - var(--tq-tinte-p)), var(--tq-tinte));
        /* En claro, bases algo más oscuras para el morado, el gris y el oro: mezcladas con el tinte más cálido siguen en 4,5:1
           o más como texto (sin el ajuste, el gris quedaba en 4,0 y el oro en 4,4). */
        --tq-franja-apoyo: color-mix(in oklch, #8A3FA8 calc(100% - var(--tq-tinte-p)), var(--tq-tinte));
        --tq-franja-plataforma: color-mix(in oklch, #5E666F calc(100% - var(--tq-tinte-p)), var(--tq-tinte));
        --tq-oro: color-mix(in oklch, #7A5500 calc(100% - var(--tq-tinte-p)), var(--tq-tinte));
        --primary-600: color-mix(in oklch, oklch(0.245 0.169 264.466) calc(100% - var(--tq-tinte-p)), var(--tq-tinte));
        --primary-400: color-mix(in oklch, {{ $C::AZUL_NOCHE_OSCURO }} calc(100% - var(--tq-tinte-p)), var(--tq-tinte));
        /* Los de estado, con un toque leve (6 %): siguen distinguiéndose entre sí. */
        --tq-tinte-leve: calc(var(--tq-tinte-p) * 0.45);
    }
    html.dark {
        --tq-fondo: var(--tq-luz-fondo, var(--gray-950));
        /* Modo oscuro, como el lienzo: superficie 7 %, elevada 12 %, borde 14 % y texto suave 55 % de blanco. */
        --tq-superficie: color-mix(in oklch, var(--tq-fondo) 93%, #FFFFFF 7%);
        --tq-superficie-elevada: color-mix(in oklch, var(--tq-fondo) 88%, #FFFFFF 12%);
        --tq-borde: color-mix(in oklch, var(--tq-fondo) 86%, #FFFFFF 14%);
        --tq-texto-suave: color-mix(in oklch, var(--tq-fondo) 45%, #FFFFFF 55%);
        --tq-texto: color-mix(in oklch, #F3F1EC 94%, var(--tq-tinte) 6%);
        --tq-vidrio: color-mix(in oklch, var(--tq-fondo) 55%, transparent);
        --tq-franja-misionales: color-mix(in oklch, {{ $C::TURQUESA }} calc(100% - var(--tq-tinte-p)), var(--tq-tinte));
        /* En oscuro, el azul un punto más claro (#7FA8FF): con el tinte más cálido, #6F9BFF quedaba en 4,4:1. */
        --tq-franja-estrategicos: color-mix(in oklch, #7FA8FF calc(100% - var(--tq-tinte-p)), var(--tq-tinte));
        --tq-franja-apoyo: color-mix(in oklch, {{ $C::LILA_OSCURO }} calc(100% - var(--tq-tinte-p)), var(--tq-tinte));
        --tq-franja-plataforma: color-mix(in oklch, {{ $C::GRIS_PLATAFORMA_OSCURO }} calc(100% - var(--tq-tinte-p)), var(--tq-tinte));
        --tq-oro: color-mix(in oklch, {{ $C::DORADO_OSCURO }} calc(100% - var(--tq-tinte-p)), var(--tq-tinte));
        --primary-600: color-mix(in oklch, {{ $C::principalModoOscuro() }} calc(100% - var(--tq-tinte-p)), var(--tq-tinte));
    }
    @if ($conAmbiente)
        /* Los colores de estado de Filament, con un toque leve del ambiente. */
        html:root { --success-500: color-mix(in oklch, oklch(0.723 0.219 149.579) calc(100% - var(--tq-tinte-leve)), var(--tq-tinte)); --warning-500: color-mix(in oklch, oklch(0.769 0.188 70.08) calc(100% - var(--tq-tinte-leve)), var(--tq-tinte)); --danger-500: color-mix(in oklch, oklch(0.637 0.237 25.331) calc(100% - var(--tq-tinte-leve)), var(--tq-tinte)); }
    @endif

    /* 1. Las superficies de Filament y las propias nacen del fondo. */
    .fi-section, .fi-ta-ctn, .fi-wi-stats-overview-stat, .fi-fo-field-wrp .fi-input-wrp, .fi-tabs:not(.fi-contained), .fi-in-entry-wrp,
    .tq-proceso-cuerpo, .tq-tickets-franja, .tq-embudo-cifra, .tq-embudo-panel, .tq-embudo-paso {
        background-color: var(--tq-superficie) !important;
    }
    .tq-proceso-cuerpo, .tq-tickets-franja { box-shadow: 0 0 0 1px var(--tq-borde) !important; }
    .tq-proceso { --tq-proceso-fondo: var(--tq-superficie) !important; }
    .tq-proceso-caja { color: var(--tq-texto) !important; box-shadow: 1px 0 0 var(--tq-borde), 0 1px 0 var(--tq-borde) !important; }
    .tq-proceso-caja[aria-current="page"] { color: var(--tq-f-texto) !important; }
    .fi-section, .fi-ta-ctn { --tw-ring-color: var(--tq-borde); }
    .fi-ta-header-cell, .fi-ta-row:hover { background-color: var(--tq-superficie-elevada); }
    .fi-header-subheading, .fi-ta-header-cell-label { color: var(--tq-texto-suave); }

    /* 2. Las franjas con temperatura: las cajas de proceso, la barra y la frase toman su color de aquí. */
    .tq-proceso[data-area="erp"], .tq-area[data-area="erp"], .tq-portada-proceso[data-area="erp"] { --tq-f: var(--tq-franja-misionales) !important; --tq-f-texto: var(--tq-franja-misionales) !important; }
    .tq-proceso[data-area="nucleo"] { --tq-f: var(--tq-franja-estrategicos) !important; --tq-f-texto: var(--tq-franja-estrategicos) !important; }
    .tq-proceso[data-area="crm"] { --tq-f: var(--tq-franja-apoyo) !important; --tq-f-texto: var(--tq-franja-apoyo) !important; }
    .tq-proceso[data-area="plataforma"] { --tq-f: var(--tq-franja-plataforma) !important; --tq-f-texto: var(--tq-franja-plataforma) !important; }
    .tq-proceso[data-area="personal"] { --tq-f: var(--tq-oro) !important; --tq-f-texto: var(--tq-oro) !important; }
    .tq-area[data-area="nucleo"] { --tq-area-color: var(--tq-franja-estrategicos) !important; }
    .tq-area[data-area="crm"] { --tq-area-texto: var(--tq-franja-apoyo) !important; }
    .tq-area[data-area="erp"] { --tq-area-texto: var(--tq-franja-misionales) !important; }
    .tq-area[data-area="plataforma"] { --tq-area-color: var(--tq-franja-plataforma) !important; }
    [data-tq-area="erp"] { --tq-area-activo: var(--tq-franja-misionales) !important; --tq-area-activo-oscuro: var(--tq-franja-misionales) !important; }
    [data-tq-area="nucleo"] { --tq-area-activo: var(--tq-franja-estrategicos) !important; --tq-area-activo-oscuro: var(--tq-franja-estrategicos) !important; }
    [data-tq-area="crm"] { --tq-area-activo: var(--tq-franja-apoyo) !important; --tq-area-activo-oscuro: var(--tq-franja-apoyo) !important; }
    [data-tq-area="plataforma"] { --tq-area-activo: var(--tq-franja-plataforma) !important; --tq-area-activo-oscuro: var(--tq-franja-plataforma) !important; }

    /* 3. Vidrio en lo que flota: deja ver el ambiente. */
    /* Observación 8.7: los paneles, las ventanas y la agenda van sólidos (`paneles.blade.php`); el vidrio, solo en lo pequeño. */
    .tq-apps-flota, .tq-globo, .fi-dropdown-panel, .tq-chip-menu {
        background-color: var(--tq-vidrio) !important;
        -webkit-backdrop-filter: blur(14px) saturate(1.2); backdrop-filter: blur(14px) saturate(1.2);
        border-color: var(--tq-borde) !important;
    }
    .tq-agenda { background-color: var(--tq-superficie-elevada) !important; border-color: var(--tq-borde) !important; }
    .tq-agenda-angosto { background: var(--tq-superficie) !important; border-color: var(--tq-borde) !important; }
    .tq-globo-punta { background: var(--tq-vidrio) !important; }
</style>
