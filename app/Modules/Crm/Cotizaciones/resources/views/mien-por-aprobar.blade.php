{{-- Observación 9.5 (2): «Por aprobar» en Mien (lienzo `oportunidades-mejoras/Aprobacion.dc.html`). --}}
<div style="display: flex; flex-direction: column; gap: 12px">
    @forelse ($items as $c)
        <div wire:key="pa-{{ $c['id'] }}" x-data="{ rechazando: false, motivo: '' }" style="border: 1px solid var(--tq-borde, #E5E0D6); border-radius: 12px; padding: 12px 14px; display: flex; flex-direction: column; gap: 6px">
            <div style="font-weight: 700">{{ $c['descuento'] === null ? 'Margen bajo el mínimo' : 'Descuento de '.$c['descuento'].' %' }} · margen {{ $c['margen'] }} %</div>
            <div style="font-size: 12px; color: var(--gray-500)">
                <a href="{{ $url($c['id']) }}" wire:navigate class="fi-link" style="font-size: 12px">{{ $c['numero'] }}</a> · {{ $c['cliente'] }}@if ($c['pide']) · pide {{ $c['pide'] }}@endif · mínimo {{ rtrim(rtrim((string) $c['minimo'], '0'), '.') }} %
            </div>
            @if ($c['motivo'])
                <div style="font-size: 13px; font-style: italic">«{{ \Illuminate\Support\Str::limit($c['motivo'], 160) }}»</div>
            @endif
            <div x-show="! rechazando" style="display: flex; gap: 8px; justify-content: flex-end; margin-top: 4px">
                <x-filament::button size="sm" color="gray" x-on:click="rechazando = true">Rechazar</x-filament::button>
                <x-filament::button size="sm" color="success" wire:click="aprobar({{ $c['id'] }}, {{ $c['version'] }})">Aprobar</x-filament::button>
            </div>
            <div x-show="rechazando" x-cloak style="display: flex; flex-direction: column; gap: 6px; margin-top: 4px">
                <textarea x-model="motivo" rows="2" placeholder="¿Por qué se rechaza? (vuelve a borrador)" style="width: 100%; border: 1px solid var(--tq-borde, #E5E0D6); border-radius: 8px; padding: 6px 8px; font-size: 13px; background: var(--tq-superficie, white); color: inherit"></textarea>
                <div style="display: flex; gap: 8px; justify-content: flex-end">
                    <x-filament::button size="sm" color="gray" x-on:click="rechazando = false">Cancelar</x-filament::button>
                    <x-filament::button size="sm" color="danger" x-bind:disabled="motivo.trim() === ''" x-on:click="$wire.rechazar({{ $c['id'] }}, {{ $c['version'] }}, motivo)">Rechazar</x-filament::button>
                </div>
            </div>
        </div>
    @empty
        <p class="text-sm text-gray-500 dark:text-gray-400">Ningún descuento espera aprobación.</p>
    @endforelse
</div>
