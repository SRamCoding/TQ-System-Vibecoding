{{-- MV-022: al entrar a Personas y empresas, sus dos cajas internas, como las de una portada de proceso. --}}
<div>
    @include('nucleo::filament.partials.cajas-con-panel')
    <div class="tq-catalogos-cajas">
        @foreach ($papeles as $clave => $papel)
            <a href="/personas?papel={{ $clave }}" class="fi-section tq-caja-catalogo tq-caja-sub" data-papel="{{ $clave }}" x-data
               x-tooltip="{ content: @js($papel['descripcion']), theme: $store.theme }">
                <x-filament::icon :icon="$papel['icono']" class="tq-caja-catalogo-icono tq-caja-sub-icono" />
                <span class="tq-caja-catalogo-nombre">{{ $papel['nombre'] }}</span>
            </a>
        @endforeach
    </div>
</div>
