{{-- Observación 9.10: el atajo de una tarjeta en una ventana al medio (fondo desenfocado, MV-086); × o Esc cierran. --}}
<x-filament::modal id="tq-camp-atajo" width="5xl" :heading="$atajo['titulo'] ?: 'Campaña'">
    @if ($atajo['tipo'] !== '')
        @include('campanias::listas', ['tipo' => $atajo['tipo'], 'filas' => $atajo['filas']])
    @endif
</x-filament::modal>
