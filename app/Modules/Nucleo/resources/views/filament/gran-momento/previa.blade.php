{{-- La escena de la vista previa (plan 1.4d, D8): el mismo marco que verá cada persona; al cerrarse, la página lo quita. --}}
<div x-on:tq-gran-momento-cerrado="$wire.cerrar()">
    @if ($escena !== null)
        <div wire:key="tq-gm-previa-{{ $vez }}">
            @include('nucleo::filament.gran-momento.marco', ['escena' => $escena, 'animado' => $animado])
        </div>
    @endif
</div>
