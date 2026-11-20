{{--
    MV-050 (reemplaza el borde arrastrable del plan 1.3d, D6): la barra lateral solo se expande o se comprime, con el botón
    de Filament junto al logo. Esta pieza, invisible, guarda el último estado de la persona al cambiarlo (comprimida: 0;
    expandida: el ancho fijo). Solo en escritorio.
--}}
@php use App\Modules\Nucleo\Navegacion\BarraLateral; @endphp
<div
    x-data="{ guardado: {{ $ancho }}, iniciado: false }"
    x-effect="const abierta = $store.sidebar.isOpen; if (! iniciado) { iniciado = true; return } if (window.innerWidth < @js(BarraLateral::CORTE_ESCRITORIO)) return; const ancho = abierta ? {{ BarraLateral::ANCHO_INICIAL }} : 0; if (ancho !== guardado) { guardado = ancho; $wire.guardar(ancho) }"
    class="tq-borde-barra"
    aria-hidden="true"
></div>
