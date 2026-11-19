{{-- Aniversario · motivo · La T de Techquk que se arma pieza por pieza. --}}
<style>
    .tq-corre .tq-pz-logo-pieza { animation: tq-pz-logo-arma 0.7s cubic-bezier(0.3, 1.5, 0.5, 1) both; }
    @keyframes tq-pz-logo-arma { from { transform: translateY(-10px); opacity: 0; } to { transform: none; opacity: 1; } }
</style>
<g transform="translate(26 34) scale(0.24)">
    @foreach ([[\App\Modules\Nucleo\Saludo\LogoTechquk::PIEZAS['izquierda'], \App\Modules\Nucleo\Navegacion\ColoresMarca::TURQUESA, 0], [\App\Modules\Nucleo\Saludo\LogoTechquk::PIEZAS['derecha'], \App\Modules\Nucleo\Navegacion\ColoresMarca::MORADO, 0.2], [\App\Modules\Nucleo\Saludo\LogoTechquk::PIEZAS['abajo'], '#3D4772', 0.4]] as [$puntos, $color, $r])
        <polygon class="tq-pz-logo-pieza" style="animation-delay: {{ $r }}s" points="{{ $puntos }}" fill="{{ $color }}"/>
    @endforeach
</g>
