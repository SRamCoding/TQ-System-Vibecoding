{{-- Plan 1.12, D6: sin servidores (capa de TI), sin uso por persona (datos personales) y sin caché. --}}
<x-pulse>
    <livewire:pulse.queues cols="4" />

    <livewire:pulse.slow-queries cols="8" />

    <livewire:pulse.exceptions cols="6" />

    <livewire:pulse.slow-requests cols="6" />

    <livewire:pulse.slow-jobs cols="6" />

    <livewire:pulse.slow-outgoing-requests cols="6" />
</x-pulse>
