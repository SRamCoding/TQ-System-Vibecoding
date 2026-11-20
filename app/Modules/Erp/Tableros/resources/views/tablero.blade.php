@php
    $tonos = ['danger' => 'text-danger-600 dark:text-danger-400', 'warning' => 'text-warning-600 dark:text-warning-400', 'success' => 'text-success-600 dark:text-success-400'];
@endphp
<div class="space-y-6">
    @if ($enMien ?? false)
        <h2 class="text-base font-bold">{{ count($roles) > 1 ? 'El tablero de tu rol' : 'Tablero · '.$roles[$actual] }}</h2>
    @endif
    @if (count($roles) > 1)
        <x-filament::tabs>
            @foreach ($roles as $clave => $nombre)
                <x-filament::tabs.item :active="$clave === $actual" tag="a" :href="'/mien?rol='.$clave">{{ $nombre }}</x-filament::tabs.item>
            @endforeach
        </x-filament::tabs>
    @endif

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ($t['kpis'] as $k)
            <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $k['etiqueta'] }}</p>
                <p @class(['mt-1 text-2xl font-semibold', $tonos[$k['tono'] ?? ''] ?? 'text-gray-950 dark:text-white'])>{{ $k['valor'] }}</p>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $k['nota'] }}</p>
            </div>
        @endforeach
    </div>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
        @foreach ($t['listas'] as $l)
            <x-filament::section :heading="$l['titulo']">
                @if ($l['items'] === [])
                    <p class="text-sm text-gray-500 dark:text-gray-400">Nada pendiente.</p>
                @else
                    <ul class="divide-y divide-gray-100 text-sm dark:divide-white/5">
                        @foreach ($l['items'] as $i)
                            <li class="py-2">
                                @if (isset($i['url']))
                                    <a href="{{ $i['url'] }}" @class(['font-medium hover:underline', $tonos[$i['tono'] ?? ''] ?? 'text-gray-950 dark:text-white'])>{{ $i['texto'] }}</a>
                                @else
                                    <span @class(['font-medium', $tonos[$i['tono'] ?? ''] ?? ''])>{{ $i['texto'] }}</span>
                                @endif
                                @isset($i['nota'])
                                    <p class="text-gray-500 dark:text-gray-400">{{ $i['nota'] }}</p>
                                @endisset
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-filament::section>
        @endforeach
    </div>
</div>
