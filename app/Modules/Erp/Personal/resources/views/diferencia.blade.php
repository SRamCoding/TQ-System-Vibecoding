<x-filament::section heading="Costo real de las horas contra su costo estándar" description="Las horas tareadas del mes por proyecto: al costo estándar de su categoría (el del APU, que es el que va a las partidas) y al real de planilla (costo empresa ÷ 192 h).">
    @if ($filas === [])
        <p class="text-sm text-gray-500">Sin horas tareadas en este periodo.</p>
    @else
        <table class="w-full text-sm">
            <thead><tr class="text-left text-gray-500 dark:text-gray-400"><th class="py-1">Proyecto</th><th class="py-1 text-right">Horas</th><th class="py-1 text-right">Estándar</th><th class="py-1 text-right">Real</th><th class="py-1 text-right">Diferencia</th></tr></thead>
            <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                @foreach ($filas as $f)
                    <tr><td class="py-1">{{ $f['proyecto'] }}</td><td class="py-1 text-right">{{ $f['horas'] }}</td><td class="py-1 text-right font-mono">{{ number_format((float) $f['estandar'], 2) }}</td><td class="py-1 text-right font-mono">{{ number_format((float) $f['real'], 2) }}</td>
                        <td @class(['py-1 text-right font-mono', 'text-danger-600' => (float) $f['diferencia'] > 0])>{{ number_format((float) $f['diferencia'], 2) }}</td></tr>
                @endforeach
            </tbody>
        </table>
    @endif
</x-filament::section>
