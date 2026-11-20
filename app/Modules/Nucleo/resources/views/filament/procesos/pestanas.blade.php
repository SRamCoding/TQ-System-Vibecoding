{{--
    Dentro de una pantalla de un proceso (MV-020): la miga «Proceso › Pantalla» para volver a la portada y, si el proceso
    tiene más de una pantalla, pestañas con sus hermanas.
--}}
@php /** @var array{proceso: \App\Modules\Nucleo\Navegacion\Proceso, caja: \App\Modules\Nucleo\Navegacion\CajaModulo, internas: list<\App\Modules\Nucleo\Navegacion\CajaModulo>, actual: \App\Modules\Nucleo\Navegacion\CajaModulo} $datos */ @endphp
@if ($datos['proceso'] !== \App\Modules\Nucleo\Navegacion\Proceso::Mien)
<nav class="tq-proceso-nav" aria-label="{{ $datos['proceso']->nombre() }}">
    <style>
        .tq-proceso-nav { display: flex; flex-direction: column; gap: 0.5rem; margin-bottom: 0.25rem; }
        .tq-proceso-miga { display: flex; align-items: center; gap: 0.35rem; font-size: 0.875rem; color: var(--gray-500); }
        .tq-proceso-miga a { font-weight: 600; color: var(--primary-600); }
        .dark .tq-proceso-miga a { color: var(--primary-400); }
        .tq-proceso-miga a:hover { text-decoration: underline; }
        .tq-proceso-pestanas { overflow-x: auto; }
    </style>
    <div class="tq-proceso-miga">
        <a href="{{ $datos['proceso']->url() }}">{{ $datos['proceso']->nombre() }}</a>
        <span aria-hidden="true">›</span>
        <span>{{ $datos['actual']->nombre }}</span>
    </div>
    @if (count($datos['internas']) > 1)
        <div class="tq-proceso-pestanas">
            <x-filament::tabs>
                @foreach ($datos['internas'] as $interna)
                    <x-filament::tabs.item tag="a" :href="$interna->url" :active="$interna->clave === $datos['actual']->clave">{{ $interna->nombre }}</x-filament::tabs.item>
                @endforeach
            </x-filament::tabs>
        </div>
    @endif
</nav>
@endif
