{{-- El encabezado del inicio (plan 1.4b): el dibujo de la fecha y el saludo. Recibe `$saludo` y `$variante` (o nulo). --}}
<span class="tq-saludo">
    @if ($variante !== null)
        {{-- En modo desarrollo, un clic sobre el dibujo deja cambiarlo (plan 1.4i, D3). --}}
        <span @if (\App\Modules\Nucleo\Desarrollo\ModoDesarrollo::activo()) data-tq-pieza="{{ \App\Modules\Nucleo\Desarrollo\Piezas\DibujoDelSaludo::CLAVE }}" title="Cambiar el dibujo" @endif style="display: inline-flex">
            @include('nucleo::filament.ambientacion.dibujo', ['variante' => $variante, 'animado' => true, 'tamano' => 80])
        </span>
    @endif
    <span lang="{{ $saludo->idioma->value }}">{{ $saludo->saludo }}</span>
</span>
