{{-- Observación 9.5 (8): quién decide, con su papel y su postura. --}}
@php
    $tonoPapel = ['decide' => 'var(--primary-600)', 'influye' => 'var(--gray-600)', 'usa' => 'var(--gray-500)', 'bloquea' => 'color-mix(in oklch, #C2453C calc(100% - var(--tq-tinte-leve, 0%)), var(--tq-tinte, #C2453C))'];
    $tonoPostura = ['a_favor' => 'color-mix(in oklch, #2F7D4F calc(100% - var(--tq-tinte-leve, 0%)), var(--tq-tinte, #2F7D4F))', 'neutral' => 'var(--gray-500)', 'en_contra' => 'color-mix(in oklch, #C2453C calc(100% - var(--tq-tinte-leve, 0%)), var(--tq-tinte, #C2453C))'];
@endphp
<div style="border: 1px solid var(--tq-borde, #E5E0D6); background: var(--tq-superficie, white); border-radius: 14px; padding: 14px 16px; display: flex; flex-direction: column; gap: 10px">
    <div style="display: flex; align-items: center"><strong style="flex: 1">Quién decide</strong>
        @if (! $agregando)<x-filament::link tag="button" size="sm" icon="heroicon-o-plus" wire:click="$set('agregando', true)">Persona</x-filament::link>@endif
    </div>
    @forelse ($personas as $p)
        <div wire:key="dec-{{ $p['id'] }}" style="display: flex; gap: 10px; align-items: center; border-top: 1px solid var(--tq-borde, #EFEBE3); padding-top: 8px">
            <span style="flex: none; width: 30px; height: 30px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font: 700 11px Inter, sans-serif; background: var(--gray-100); color: var(--gray-700)">{{ $p['iniciales'] }}</span>
            <span style="flex: 1; min-width: 0"><span style="display: block; font-weight: 600; font-size: 14px">{{ $p['nombre'] }}</span><span style="display: block; font-size: 12px; color: var(--gray-500)">{{ $p['cargo'] ?? '—' }}{{ $p['nota'] ? ' · '.$p['nota'] : '' }}</span></span>
            <span style="font-size: 12px; font-weight: 700; color: {{ $tonoPapel[$p['papel']] }}">{{ $papeles[$p['papel']] }}</span>
            <span style="font-size: 12px; font-weight: 600; color: {{ $tonoPostura[$p['postura']] }}">{{ $posturas[$p['postura']] }}</span>
            <button type="button" wire:click="quitar({{ $p['id'] }})" wire:confirm="¿Quitar a {{ $p['nombre'] }} de este negocio?" title="Quitar" style="color: var(--gray-400)"><x-filament::icon icon="heroicon-m-x-mark" class="h-4 w-4" /></button>
        </div>
    @empty
        @if (! $agregando)<div style="font-size: 13px; color: var(--gray-500)">Nadie registrado todavía: ¿quién decide, quién influye y quién podría bloquear?</div>@endif
    @endforelse
    @if ($agregando)
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px; border-top: 1px solid var(--tq-borde, #EFEBE3); padding-top: 10px">
            <select wire:model.live="contactoId" style="grid-column: 1 / -1" class="fi-select-input rounded-lg border border-gray-300 dark:border-gray-600 bg-transparent text-sm">
                <option value="">Una persona nueva (escribe su nombre)</option>
                @foreach ($contactos as $id => $etiqueta)<option value="{{ $id }}">{{ $etiqueta }}</option>@endforeach
            </select>
            @if (! is_numeric($contactoId))
                <input wire:model="nombre" placeholder="Nombre" class="rounded-lg border border-gray-300 dark:border-gray-600 bg-transparent text-sm px-3 py-1.5">
                <input wire:model="cargo" placeholder="Cargo" class="rounded-lg border border-gray-300 dark:border-gray-600 bg-transparent text-sm px-3 py-1.5">
            @endif
            <select wire:model="papel" class="rounded-lg border border-gray-300 dark:border-gray-600 bg-transparent text-sm">@foreach ($papeles as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select>
            <select wire:model="postura" class="rounded-lg border border-gray-300 dark:border-gray-600 bg-transparent text-sm">@foreach ($posturas as $k => $v)<option value="{{ $k }}">{{ ucfirst($v) }}</option>@endforeach</select>
            <input wire:model="nota" placeholder="Nota (p. ej. prefiere al proveedor actual)" style="grid-column: 1 / -1" class="rounded-lg border border-gray-300 dark:border-gray-600 bg-transparent text-sm px-3 py-1.5">
            <div style="grid-column: 1 / -1; display: flex; gap: 8px; justify-content: flex-end">
                <x-filament::button size="sm" color="gray" wire:click="$set('agregando', false)">Cancelar</x-filament::button>
                <x-filament::button size="sm" wire:click="guardar">Agregar</x-filament::button>
            </div>
        </div>
    @endif
</div>
