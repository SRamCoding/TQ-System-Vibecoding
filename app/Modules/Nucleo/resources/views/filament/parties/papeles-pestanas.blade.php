{{-- MV-022: dentro de una de las dos cajas, el paso a la otra. --}}
<x-filament::tabs>
    @foreach ($papeles as $clave => $papel)
        <x-filament::tabs.item tag="a" :href="'/personas?papel='.$clave" :active="$clave === $actual" :icon="$papel['icono']">{{ $papel['nombre'] }}</x-filament::tabs.item>
    @endforeach
</x-filament::tabs>
