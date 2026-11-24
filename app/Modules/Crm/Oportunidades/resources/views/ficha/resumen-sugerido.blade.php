{{-- Observación 9.5 (10), lienzo `oportunidades-mejoras/Ficha.dc.html`: el resumen y el siguiente paso sugeridos. --}}
<div style="border: 1px solid color-mix(in oklab, var(--primary-500) 30%, var(--tq-borde, #E5E0D6)); background: color-mix(in oklab, var(--primary-500) 6%, var(--tq-superficie, white)); border-radius: 14px; padding: 14px 18px; display: flex; gap: 14px; align-items: flex-start">
    <span style="flex: none; width: 34px; height: 34px; border-radius: 10px; display: inline-flex; align-items: center; justify-content: center; font: 700 12px Inter, sans-serif; background: color-mix(in oklab, var(--primary-500) 16%, transparent); color: var(--primary-600)">IA</span>
    <div style="flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 8px">
        @if ($r['texto'])
            <div style="font-size: 14px; line-height: 1.5"><strong>Resumen:</strong> {{ $r['texto'] }}</div>
            @if ($r['paso'])
                <div style="font-size: 14px; line-height: 1.5"><strong>Siguiente paso sugerido:</strong> {{ $r['paso'] }}</div>
            @endif
            <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap">
                @if ($r['paso'])<x-filament::button size="sm" icon="heroicon-o-calendar-days" wire:click="programar">Programar el paso</x-filament::button>@endif
                <span style="font-size: 12px; color: var(--gray-500)">sugerido · lo confirmas tú{{ $r['cuando'] ? ' · '.$r['cuando'] : '' }}</span>
                <span style="flex: 1"></span>
                <x-filament::link tag="button" size="sm" icon="heroicon-o-arrow-path" wire:click="volverASugerir" :tooltip="$ia ? null : 'La IA no está configurada'">Volver a sugerir</x-filament::link>
            </div>
        @else
            <div style="font-size: 14px; color: var(--gray-500)">Todavía no hay un resumen sugerido de este negocio.</div>
            <div><x-filament::button size="sm" color="gray" icon="heroicon-o-sparkles" wire:click="volverASugerir">{{ $ia ? 'Sugerir con IA' : 'Sugerir (la IA no está configurada)' }}</x-filament::button></div>
        @endif
    </div>
</div>
