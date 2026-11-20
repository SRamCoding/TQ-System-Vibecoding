<x-filament::section heading="Flujo de caja por mes" description="Lo real sale de los movimientos; desde este mes se suma lo proyectado: lo por cobrar según su vencimiento y lo por pagar según su fecha programada o su vencimiento.">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead><tr class="text-left text-gray-500 dark:text-gray-400"><th class="py-1.5">Mes</th><th class="py-1.5 text-right">Ingresos</th><th class="py-1.5 text-right">Egresos</th><th class="py-1.5 text-right">Neto</th><th class="py-1.5 text-right">Acumulado</th></tr></thead>
            <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                @php $acum = '0'; @endphp
                @foreach ($filas as $f)
                    @php $acum = bcadd($acum, $f['neto'], 2); @endphp
                    <tr @class(['italic text-gray-500 dark:text-gray-400' => $f['proyectado']])>
                        <td class="py-1.5">{{ \Carbon\CarbonImmutable::parse($f['mes'].'-01')->translatedFormat('F Y') }}{{ $f['proyectado'] ? ' · proyectado' : '' }}</td>
                        <td class="py-1.5 text-right font-mono">{{ number_format((float) $f['ingresos'], 2) }}</td>
                        <td class="py-1.5 text-right font-mono">{{ number_format((float) $f['egresos'], 2) }}</td>
                        <td @class(['py-1.5 text-right font-mono', 'text-danger-600' => (float) $f['neto'] < 0])>{{ number_format((float) $f['neto'], 2) }}</td>
                        <td @class(['py-1.5 text-right font-mono', 'text-danger-600' => (float) $acum < 0])>{{ number_format((float) $acum, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</x-filament::section>
