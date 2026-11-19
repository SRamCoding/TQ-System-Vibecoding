{{--
    En los días del aniversario, entre el logo y el buscador (plan 1.4b, D10): el agradecimiento a Dios por los años de
    Techquk, en letra cursiva. Corto (hasta 60 letras). En pantallas angostas no se muestra: no cabe. Recibe `$texto`.
--}}
<style>
    @font-face { font-family: 'Great Vibes'; font-style: normal; font-weight: 400; font-display: swap; src: url('{{ asset('fonts/great-vibes/great-vibes-latin.woff2') }}') format('woff2'); unicode-range: U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA, U+02DC, U+0304, U+0308, U+0329, U+2000-206F, U+20AC, U+2122, U+2191, U+2193, U+2212, U+2215, U+FEFF, U+FFFD; }
    @font-face { font-family: 'Great Vibes'; font-style: normal; font-weight: 400; font-display: swap; src: url('{{ asset('fonts/great-vibes/great-vibes-latin-ext.woff2') }}') format('woff2'); unicode-range: U+0100-02BA, U+02BD-02C5, U+02C7-02CC, U+02CE-02D7, U+02DD-02FF, U+0304, U+0308, U+0329, U+1D00-1DBF, U+1E00-1E9F, U+1EF2-1EFF, U+2020, U+20A0-20AB, U+20AD-20C0, U+2113, U+2C60-2C7F, U+A720-A7FF; }
    .tq-agradecimiento {
        display: none; margin-inline-start: 1.5rem; max-width: 26rem; overflow: hidden; white-space: nowrap; text-overflow: ellipsis;
        font-family: 'Great Vibes', 'Segoe Script', 'Brush Script MT', cursive; font-size: 1.6rem; line-height: 1.2;
        background: linear-gradient(90deg, {{ \App\Modules\Nucleo\Navegacion\ColoresMarca::MORADO }}, {{ \App\Modules\Nucleo\Navegacion\ColoresMarca::AZUL_NOCHE }}); -webkit-background-clip: text; background-clip: text; color: transparent;
    }
    .dark .tq-agradecimiento { background-image: linear-gradient(90deg, {{ \App\Modules\Nucleo\Navegacion\ColoresMarca::TURQUESA }}, {{ \App\Modules\Nucleo\Navegacion\ColoresMarca::MORADO }}); }
    @media (min-width: 768px) { .tq-agradecimiento { display: inline-block; } }
</style>
<span class="tq-agradecimiento" data-agradecimiento>{{ $texto }}</span>
