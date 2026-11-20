{{--
    MV-053: el logo de Techquk en un solo color (el de la franja Estratégicos), para su caja: el símbolo de tres piezas
    (de `LogoTechquk::PIEZAS`, las mismas del logo del encabezado) achatado al 85 % del alto y, si se pide, el nombre
    TECHQUK en Audiowide con el espaciado del logo. De línea, como los demás íconos, con un relleno suave por pieza.
--}}
<span class="tq-logo-mono">
    <svg viewBox="0 0 122 119" preserveAspectRatio="none" class="tq-logo-mono-simbolo" aria-hidden="true" focusable="false">
        @foreach (\App\Modules\Nucleo\Saludo\LogoTechquk::PIEZAS as $puntos)
            <polygon points="{{ $puntos }}" fill="currentColor" fill-opacity=".16" stroke="currentColor" stroke-width="1.9" stroke-linejoin="round" vector-effect="non-scaling-stroke"/>
        @endforeach
    </svg>
    @if ($nombre ?? true)
        <span class="tq-logo-mono-nombre">TechQuk</span>
    @endif
</span>
