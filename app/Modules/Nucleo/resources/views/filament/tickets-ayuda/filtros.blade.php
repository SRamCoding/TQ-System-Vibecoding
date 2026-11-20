{{--
    MV-040: la franja fija de filtros de Tickets de ayuda, arriba de la lista. A la izquierda, el estado como pestañas con
    contador; a la derecha, el buscador y los filtros en botones: el activo, lleno con su valor y «✕»; los demás, con
    borde punteado y «+». Lo activo, en el azul de resaltado (MV-042).
--}}
@php
    $nombres = \App\Modules\Nucleo\Filament\Pages\TicketsAyuda::PESTANAS;
    $fechas = \App\Modules\Nucleo\Filament\Pages\TicketsAyuda::FECHAS;
    $valorFecha = $this->fecha === 'rango' ? trim(($this->desde ? \Carbon\CarbonImmutable::parse($this->desde)->format('d/m') : '…').' – '.($this->hasta ? \Carbon\CarbonImmutable::parse($this->hasta)->format('d/m') : '…')) : ($fechas[$this->fecha] ?? null);
    $chips = array_filter([
        ['clave' => 'proceso', 'nombre' => 'Proceso', 'valor' => $procesos[$this->proceso] ?? null, 'opciones' => $procesos],
        $soporte ? ['clave' => 'quien', 'nombre' => 'Quién lo abrió', 'valor' => $personas[$this->quien] ?? null, 'opciones' => $personas] : null,
        ['clave' => 'fecha', 'nombre' => 'Fecha', 'valor' => $valorFecha, 'opciones' => $fechas],
        ['clave' => 'esperando', 'nombre' => 'Esperando más de', 'valor' => $this->esperando === null ? null : "{$this->esperando} días", 'opciones' => [2 => '2 días', 7 => '7 días']],
    ]);
@endphp
<div class="tq-tickets-franja">
    <style>
        .tq-tickets-franja { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 0.75rem 1.5rem; padding: 0.75rem 1rem; border-radius: 0.75rem; background: white; box-shadow: 0 0 0 1px var(--gray-200); }
        .dark .tq-tickets-franja { background: var(--gray-900); box-shadow: 0 0 0 1px var(--gray-700); }
        .tq-tickets-derecha { display: flex; flex-wrap: wrap; align-items: center; gap: 0.5rem; }
        .tq-chip { position: relative; }
        .tq-chip-boton { display: inline-flex; align-items: center; gap: 0.35rem; padding: 0.25rem 0.65rem; border-radius: 9999px; font-size: 0.8rem; font-weight: 600; border: 1px dashed var(--gray-400); color: var(--gray-600); }
        .dark .tq-chip-boton { border-color: var(--gray-500); color: var(--gray-300); }
        .tq-chip-boton.tq-activo { border-style: solid; border-color: var(--primary-600); background: var(--primary-600); color: white; }
        .dark .tq-chip-boton.tq-activo { border-color: var(--primary-400); background: var(--primary-400); color: var(--gray-950); }
        .tq-chip-menu { position: absolute; z-index: 20; top: calc(100% + 0.35rem); left: 0; min-width: 12rem; max-height: 18rem; overflow: auto; padding: 0.35rem; border-radius: 0.6rem; background: white; box-shadow: 0 10px 25px rgb(0 0 0 / 0.15), 0 0 0 1px var(--gray-200); }
        .dark .tq-chip-menu { background: var(--gray-800); box-shadow: 0 10px 25px rgb(0 0 0 / 0.4), 0 0 0 1px var(--gray-700); }
        .tq-chip-menu button { display: block; width: 100%; padding: 0.35rem 0.6rem; border-radius: 0.4rem; text-align: left; font-size: 0.85rem; }
        .tq-chip-menu button:hover, .tq-chip-menu button:focus-visible { background: color-mix(in oklab, var(--primary-600) 10%, transparent); }
        .tq-chip-menu input { width: 100%; margin-top: 0.25rem; padding: 0.25rem 0.4rem; border-radius: 0.35rem; border: 1px solid var(--gray-300); font-size: 0.8rem; background: transparent; }
        .tq-limpiar { font-size: 0.8rem; color: var(--primary-600); text-decoration: underline; }
        .dark .tq-limpiar { color: var(--primary-400); }
    </style>

    <x-filament::tabs>
        @foreach ($nombres as $clave => $nombre)
            <x-filament::tabs.item :active="$this->pestana === $clave" :badge="$cuentas[$clave]" wire:click="poner('pestana', '{{ $clave }}')">{{ $nombre }}</x-filament::tabs.item>
        @endforeach
    </x-filament::tabs>

    <div class="tq-tickets-derecha">
        <x-filament::input.wrapper prefix-icon="heroicon-o-magnifying-glass" style="min-width: 15rem">
            <x-filament::input type="search" wire:model.live.debounce.300ms="buscar" placeholder="Buscar código, resumen o conversación" aria-label="Buscar tickets" />
        </x-filament::input.wrapper>

        @foreach ($chips as $chip)
            <div class="tq-chip" x-data="{ abierto: false }" x-on:click.outside="abierto = false" x-on:keydown.escape="abierto = false">
                @if ($chip['valor'] !== null)
                    <button type="button" class="tq-chip-boton tq-activo" wire:click="poner('{{ $chip['clave'] }}', null)" aria-label="Quitar el filtro {{ $chip['nombre'] }}">
                        {{ $chip['nombre'] }}: {{ $chip['valor'] }} <span aria-hidden="true">✕</span>
                    </button>
                @else
                    <button type="button" class="tq-chip-boton" x-on:click="abierto = ! abierto" x-bind:aria-expanded="abierto">
                        <span aria-hidden="true">+</span> {{ $chip['nombre'] }}
                    </button>
                    <div class="tq-chip-menu" x-show="abierto" x-cloak x-transition.opacity>
                        @foreach ($chip['opciones'] as $valor => $texto)
                            @if ($chip['clave'] === 'fecha' && $valor === 'rango')
                                <div class="px-2 pt-1 text-xs font-semibold text-gray-500">Rango</div>
                                <div class="px-2 pb-1">
                                    <input type="date" wire:model.live="desde" aria-label="Desde">
                                    <input type="date" wire:model.live="hasta" aria-label="Hasta">
                                    <button type="button" x-on:click="abierto = false" wire:click="poner('fecha', 'rango')">Aplicar</button>
                                </div>
                            @else
                                <button type="button" x-on:click="abierto = false" wire:click="poner('{{ $chip['clave'] }}', @js((string) $valor))">{{ $texto }}</button>
                            @endif
                        @endforeach
                    </div>
                @endif
            </div>
        @endforeach

        @if ($hayFiltros)
            <button type="button" class="tq-limpiar" wire:click="limpiar">Limpiar filtros</button>
        @endif
    </div>
</div>
