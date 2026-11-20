{{--
    MV-023 y MV-024: la portada de Oportunidades tiene tres pestañas. «Por calificar» son los leads, la primera etapa
    del embudo; «Licitaciones», las licitaciones con sus plazos. Se incluye en las tres listas.
--}}
@php
    $porCalificar = \Illuminate\Support\Facades\DB::table('leads')->whereIn('estado', ['nuevo', 'contactado'])->count();
    $licitaciones = \Illuminate\Support\Facades\DB::table('licitaciones')->whereIn('estado', ['registrada', 'en_preparacion', 'presentada'])->count();
@endphp
<x-filament::tabs>
    <x-filament::tabs.item tag="a" href="/leads" :active="$actual === 'leads'" icon="heroicon-o-inbox-arrow-down" :badge="$porCalificar">Por calificar</x-filament::tabs.item>
    <x-filament::tabs.item tag="a" href="/oportunidades" :active="$actual === 'oportunidades'" icon="heroicon-o-presentation-chart-line">Oportunidades</x-filament::tabs.item>
    <x-filament::tabs.item tag="a" href="/licitaciones" :active="$actual === 'licitaciones'" icon="heroicon-o-building-library" :badge="$licitaciones">Licitaciones</x-filament::tabs.item>
</x-filament::tabs>
