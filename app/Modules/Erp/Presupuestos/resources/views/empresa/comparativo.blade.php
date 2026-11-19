{{-- MVP rápido (t2-presupuesto-empresa): el comparativo por sede y mes, con la desviación y el umbral alcanzado. --}}
@php
    $meses = [1 => 'Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Set', 'Oct', 'Nov', 'Dic'];
    $f = fn ($n) => number_format((float) $n, 2);
@endphp
<x-filament::section heading="Comparativo por sede y mes" description="Presupuestado contra comprometido y ejecutado. Alertas al 75, 90 y 100 % del presupuesto del mes." collapsible>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-gray-500 dark:text-gray-400">
                    <th class="py-2 pr-3">Sede</th><th class="py-2 pr-3">Mes</th>
                    <th class="py-2 pr-3 text-right">Presupuestado</th><th class="py-2 pr-3 text-right">Comprometido</th>
                    <th class="py-2 pr-3 text-right">Ejecutado</th><th class="py-2 pr-3 text-right">Variación</th><th class="py-2 text-right">Consumo</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                @foreach ($filas as $fila)
                    @continue(bccomp($fila['presupuestado'], '0', 2) === 0 && bccomp($fila['comprometido'], '0', 2) === 0)
                    <tr>
                        <td class="py-1.5 pr-3">{{ $fila['sede'] }}</td>
                        <td class="py-1.5 pr-3">{{ $meses[$fila['mes']] }}</td>
                        <td class="py-1.5 pr-3 text-right font-mono">{{ $f($fila['presupuestado']) }}</td>
                        <td class="py-1.5 pr-3 text-right font-mono">{{ $f($fila['comprometido']) }}</td>
                        <td class="py-1.5 pr-3 text-right font-mono">{{ $f($fila['ejecutado']) }}</td>
                        <td @class(['py-1.5 pr-3 text-right font-mono', 'text-danger-600 dark:text-danger-400' => bccomp($fila['variacion'], '0', 2) < 0])>{{ $f($fila['variacion']) }}</td>
                        <td class="py-1.5 text-right">
                            @if ($fila['consumo'] === null)
                                <span class="text-danger-600">sin presupuesto</span>
                            @else
                                <span @class([
                                    'rounded px-1.5 py-0.5 font-mono',
                                    'bg-danger-100 text-danger-700 dark:bg-danger-500/20 dark:text-danger-300' => $fila['umbral'] === 100,
                                    'bg-warning-100 text-warning-700 dark:bg-warning-500/20 dark:text-warning-300' => $fila['umbral'] === 90,
                                    'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300' => $fila['umbral'] === 75,
                                ])>{{ $fila['consumo'] }} %</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</x-filament::section>
