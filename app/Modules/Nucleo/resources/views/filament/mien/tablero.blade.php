{{--
    El tablero «Mien» (plan 1.4l): las tarjetas que la persona puede ver, cada una cargada aparte. Sin ninguna, el
    mensaje de la D1: las tarjetas llegan con sus módulos.
--}}
@php /** @var list<\App\Modules\Nucleo\Mien\TarjetaMien> $tarjetas */ @endphp
<div>
    <style>
        .tq-mien-tarjetas { display: grid; gap: 1rem; grid-template-columns: repeat(auto-fill, minmax(18rem, 1fr)); }
        .tq-mien-tarjeta { padding: 1rem 1.25rem; border-top: 3px solid var(--dorado-600); }
        .dark .tq-mien-tarjeta { border-top-color: var(--dorado-400); }
        .tq-mien-tarjeta-titulo { margin-bottom: 0.5rem; font-size: 0.95rem; font-weight: 700; }
        .tq-mien-tarjeta-fallida { font-size: 0.875rem; color: var(--gray-500); }
        .tq-mien-cargando { width: 1.25rem; height: 1.25rem; color: var(--gray-400); }
    </style>
    @if ($tarjetas === [] && ($secciones ?? []) === [])
        <x-filament::empty-state
            heading="Tu tablero todavía está vacío"
            description="Aquí verás tus pendientes, tus aprobaciones y las alertas de tu trabajo cuando lleguen sus módulos."
            icon="heroicon-o-star"
            icon-color="dorado"
        />
    @else
        <div class="tq-mien-tarjetas">
            @foreach ($tarjetas as $tarjeta)
                @livewire($tarjeta->nombreLivewire(), ['titulo' => $tarjeta->titulo, 'lazy' => true], key($tarjeta->clave))
            @endforeach
        </div>
    @endif
    {{-- MV-017: debajo de las tarjetas, el tablero del rol (antes «Mi tablero») y lo que otros módulos agreguen. --}}
    @foreach ($secciones ?? [] as $seccion)
        <div style="margin-top: 1.5rem">{!! $seccion->toHtml() !!}</div>
    @endforeach
</div>
