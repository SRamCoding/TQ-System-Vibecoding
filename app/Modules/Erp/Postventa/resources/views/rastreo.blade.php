<x-filament-panels::page>
    <div class="flex gap-2">
        <x-filament::input.wrapper class="flex-1"><x-filament::input type="text" wire:model.live.debounce.400ms="serie" placeholder="Número de serie completo o su final (DS8K…4415)" /></x-filament::input.wrapper>
    </div>
    @php $s = $this->equipo(); $f = fn ($d) => $d ? \Carbon\CarbonImmutable::parse($d)->format('d/m/Y') : '—'; @endphp
    @if ($serie !== '' && $s === null)
        <p class="text-sm text-gray-500">No hay ningún equipo con esa serie.</p>
    @elseif ($s)
        <x-filament::section :heading="$s->descripcion . ' · serie ' . $s->serie" :description="$s->producto">
            <div class="grid gap-3 text-sm md:grid-cols-5">
                <div><p class="text-xs text-gray-500">Compra</p><p class="font-semibold">{{ $s->orden ?? '—' }}</p><p>{{ $s->proveedor_nombre }}</p></div>
                <div><p class="text-xs text-gray-500">Ingreso</p><p class="font-semibold">{{ $f($s->ingreso) }}</p><p>{{ $s->ubicacion }}</p></div>
                <div><p class="text-xs text-gray-500">Despacho</p><p class="font-semibold">{{ $f($s->despacho) }}</p><p>{{ $s->proyecto ?? 'en almacén' }}</p></div>
                <div><p class="text-xs text-gray-500">Instalada</p><p class="font-semibold">{{ $s->instalada_en ?? '—' }}</p><p>{{ $s->proyecto_nombre }}</p></div>
                <div><p class="text-xs text-gray-500">Garantía</p>
                    @php $g = $this->garantia($s); @endphp
                    <p @class(['font-semibold', 'text-success-600' => $g === 'vigente', 'text-info-600' => $g === 'fabricante', 'text-danger-600' => $g === 'vencida'])>{{ ['vigente' => 'vigente', 'fabricante' => 'del fabricante', 'vencida' => 'vencida'][$g] }}</p>
                    <p>hasta {{ $f($s->garantia_hasta) }}{{ $s->garantia_fabricante_hasta ? ' · fabricante '.$f($s->garantia_fabricante_hasta) : '' }}</p></div>
            </div>
        </x-filament::section>
    @endif
</x-filament-panels::page>
