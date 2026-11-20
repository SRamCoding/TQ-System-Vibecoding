<x-filament::section heading="Demanda de los próximos 90 días" description="Por categoría: activos, ocupados al 100 % la próxima semana, libres, lo que piden las vacantes abiertas y la brecha a cubrir.">
    <table class="w-full text-sm">
        <thead><tr class="text-left text-gray-500 dark:text-gray-400"><th class="py-1">Categoría</th><th class="py-1 text-right">Activos</th><th class="py-1 text-right">Ocupados</th><th class="py-1 text-right">Libres</th><th class="py-1 text-right">Pedidos</th><th class="py-1 text-right">Brecha</th></tr></thead>
        <tbody class="divide-y divide-gray-100 dark:divide-white/5">
            @foreach ($filas as $f)
                <tr><td class="py-1">{{ $f['categoria'] }}</td><td class="py-1 text-right">{{ $f['activos'] }}</td><td class="py-1 text-right">{{ $f['ocupados'] }}</td><td class="py-1 text-right">{{ $f['libres'] }}</td><td class="py-1 text-right">{{ $f['pedidos'] }}</td>
                    <td @class(['py-1 text-right font-semibold', 'text-danger-600' => $f['brecha'] > 0])>{{ $f['brecha'] > 0 ? 'faltan '.$f['brecha'] : '—' }}</td></tr>
            @endforeach
        </tbody>
    </table>
</x-filament::section>
