{{-- MV-025: la tarjeta «Mi agenda» de Mien. --}}
<div class="space-y-3 text-sm">
    @foreach (['hoy' => ['Hoy', 'text-gray-950 dark:text-white'], 'vencidas' => ['Vencidas', 'text-danger-600 dark:text-danger-400'], 'por_vencer' => ['Por vencer', 'text-warning-600 dark:text-warning-400']] as $clave => [$nombre, $color])
        <div>
            <p class="font-semibold {{ $color }}">{{ $nombre }} · {{ count($grupos[$clave]) }}</p>
            @foreach (array_slice($grupos[$clave], 0, 3) as $a)
                <p class="text-gray-600 dark:text-gray-300">{{ $a['cuando'] }} · {{ $a['asunto'] }}@if ($a['cuenta']) <span class="text-gray-500">· {{ $a['cuenta'] }}</span>@endif</p>
            @endforeach
        </div>
    @endforeach
    <x-filament::button size="sm" color="gray" icon="tq-calendario-reloj" x-on:click="window.dispatchEvent(new CustomEvent('tq-agenda-abrir'))">Abrir la agenda</x-filament::button>
</div>
