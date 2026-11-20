{{-- MVP rápido (t3-compras): el cuadro comparativo: un proveedor por columna, y la decisión con su motivo. --}}
@foreach ($cuadros as $cuadro)
    @php
        $lineas = \App\Modules\Erp\Compras\Models\RequerimientoLinea::query()->whereIn('id', $cuadro->cotizaciones->flatMap->precios->pluck('requerimiento_linea_id')->unique())->orderBy('orden')->get();
        $nombre = fn ($c) => \Illuminate\Support\Facades\DB::table('parties')->where('id', $c->proveedor_party_id)->value('razon_social');
        $s = fn ($n) => number_format((float) $n, 2);
    @endphp
    <div class="mb-6">
        <p class="mb-2 text-sm font-semibold">{{ $cuadro->codigo }} · {{ $cuadro->cotizaciones->count() }} ofertas de proveedor · {{ $cuadro->estado === 'adjudicado' ? 'adjudicado' : 'abierto' }}</p>
        @if ($cuadro->cotizaciones->isEmpty())
            <p class="text-sm text-gray-500">Todavía no hay ofertas: usa «Agregar oferta de proveedor».</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-gray-500 dark:text-gray-400">
                            <th class="py-1.5 pr-3"></th>
                            @foreach ($cuadro->cotizaciones as $c)
                                <th @class(['py-1.5 pr-3 text-right', 'text-success-600 dark:text-success-400' => $cuadro->cotizacion_elegida_id === $c->id])><span class="block font-mono text-xs">{{ $c->codigo }}</span>{{ $nombre($c) }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                        @foreach ($lineas as $l)
                            <tr>
                                <td class="py-1.5 pr-3">{{ $l->descripcion }} · {{ rtrim(rtrim((string) $l->cantidad, '0'), '.') }}</td>
                                @foreach ($cuadro->cotizaciones as $c)
                                    <td class="py-1.5 pr-3 text-right font-mono">{{ ($p = $c->precios->firstWhere('requerimiento_linea_id', $l->id)) ? $s($p->precio_unitario) : '—' }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                        <tr><td class="py-1.5 pr-3">Total</td>@foreach ($cuadro->cotizaciones as $c)<td class="py-1.5 pr-3 text-right font-mono font-semibold">{{ $s($c->precios->sum(fn ($p) => (float) $p->precio_unitario * (float) $lineas->firstWhere('id', $p->requerimiento_linea_id)?->cantidad)) }}</td>@endforeach</tr>
                        <tr><td class="py-1.5 pr-3">Plazo de entrega</td>@foreach ($cuadro->cotizaciones as $c)<td class="py-1.5 pr-3 text-right">{{ $c->plazo_dias }} días</td>@endforeach</tr>
                        <tr><td class="py-1.5 pr-3">Forma de pago</td>@foreach ($cuadro->cotizaciones as $c)<td class="py-1.5 pr-3 text-right">{{ $c->forma_pago }}</td>@endforeach</tr>
                        <tr><td class="py-1.5 pr-3">Evaluación histórica</td>@foreach ($cuadro->cotizaciones as $c)<td class="py-1.5 pr-3 text-right">{{ $c->evaluacion === null ? '—' : $c->evaluacion.' / 5' }}</td>@endforeach</tr>
                        <tr><td class="py-1.5 pr-3">Decisión</td>@foreach ($cuadro->cotizaciones as $c)<td class="py-1.5 pr-3 text-right">{{ $cuadro->cotizacion_elegida_id === $c->id ? 'elegido' : '' }}</td>@endforeach</tr>
                    </tbody>
                </table>
            </div>
            @if ($cuadro->motivo)
                <p class="mt-2 text-sm"><span class="font-semibold">Motivo:</span> {{ $cuadro->motivo }}</p>
            @endif
        @endif
    </div>
@endforeach
