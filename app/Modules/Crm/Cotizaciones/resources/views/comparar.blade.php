{{--
    El comparador de dos versiones (D19): total y margen de cada una, y las líneas nuevas, quitadas y cambiadas. El margen
    y el costo solo con su permiso (D15): se quitan aquí con `$veMargen` y `$veCosto`, que llegan del servidor. Recibe
    `$c` (de `ComparadorVersiones::comparar`), `$moneda`, `$veMargen` y `$veCosto`.
--}}
@php
    $dinero = fn (string $monto): string => $moneda.' '.number_format((float) $monto, 2);
    $porcentaje = fn (?string $p): string => $p === null ? '—' : $p.' %';
@endphp
<div class="flex flex-col gap-4 text-sm" data-tq-comparador>
    <table class="w-full">
        <thead>
            <tr class="text-left text-gray-500 dark:text-gray-400">
                <th class="py-1"></th>
                <th class="py-1 text-right">Versión {{ $c['antes']['version'] }}</th>
                <th class="py-1 text-right">Versión {{ $c['despues']['version'] }}</th>
            </tr>
        </thead>
        <tbody class="font-mono">
            <tr>
                <td class="py-1 font-sans">Total con IGV</td>
                <td class="py-1 text-right">{{ $dinero($c['antes']['total']) }}</td>
                <td class="py-1 text-right">{{ $dinero($c['despues']['total']) }}</td>
            </tr>
            @if ($veMargen)
                <tr>
                    <td class="py-1 font-sans">Margen</td>
                    <td class="py-1 text-right">{{ $porcentaje($c['antes']['margen']) }}</td>
                    <td class="py-1 text-right">{{ $porcentaje($c['despues']['margen']) }}</td>
                </tr>
            @endif
        </tbody>
    </table>

    @if ($c['nuevas'] === [] && $c['quitadas'] === [] && $c['cambiadas'] === [])
        <p class="text-gray-500 dark:text-gray-400">Las líneas son iguales.</p>
    @endif

    @if ($c['nuevas'] !== [])
        <section data-tq-comparar="nuevas">
            <h3 class="font-semibold">Líneas nuevas</h3>
            <ul class="list-disc pl-5">
                @foreach ($c['nuevas'] as $l)
                    <li>{{ $l['descripcion'] }} · {{ $l['cantidad'] }} · {{ $dinero($l['subtotal']) }}@if ($veMargen) · margen {{ $porcentaje($l['margen']) }}@endif</li>
                @endforeach
            </ul>
        </section>
    @endif

    @if ($c['quitadas'] !== [])
        <section data-tq-comparar="quitadas">
            <h3 class="font-semibold">Líneas quitadas</h3>
            <ul class="list-disc pl-5">
                @foreach ($c['quitadas'] as $l)
                    <li>{{ $l['descripcion'] }} · {{ $l['cantidad'] }} · {{ $dinero($l['subtotal']) }}</li>
                @endforeach
            </ul>
        </section>
    @endif

    @if ($c['cambiadas'] !== [])
        <section data-tq-comparar="cambiadas">
            <h3 class="font-semibold">Líneas cambiadas</h3>
            <ul class="list-disc pl-5">
                @foreach ($c['cambiadas'] as $l)
                    @php
                        $cambios = array_values(array_filter($l['cambios'], fn (array $x): bool => ! $x['sensible'] || ($x['campo'] === 'Costo unitario' ? $veCosto : $veMargen)));
                    @endphp
                    @if ($cambios !== [])
                        <li>{{ $l['descripcion'] }}:
                            @foreach ($cambios as $x)
                                {{ $x['campo'] }} {{ $x['antes'] }} → {{ $x['despues'] }}@if (! $loop->last); @endif
                            @endforeach
                        </li>
                    @endif
                @endforeach
            </ul>
        </section>
    @endif
</div>
