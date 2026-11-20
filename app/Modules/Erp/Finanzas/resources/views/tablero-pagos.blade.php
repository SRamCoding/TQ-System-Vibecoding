<x-filament-panels::page>
    <p class="text-sm text-gray-500 dark:text-gray-400">Tesorería ordena los pagos: lo que vence primero arriba. Una factura con diferencia en el cruce de tres vías no se paga.</p>
    <div class="grid gap-4 md:grid-cols-3">
        @foreach ($this->columnas() as $clave => $col)
            <div class="rounded-xl bg-gray-50 p-3 dark:bg-white/5">
                <div class="mb-3 flex items-baseline justify-between">
                    <h3 class="text-sm font-semibold">{{ $col['titulo'] }} · {{ $col['tarjetas']->count() }}</h3>
                    <span class="font-mono text-xs text-gray-500">S/ {{ $col['total'] }}</span>
                </div>
                <div class="max-h-[70vh] space-y-2 overflow-y-auto">
                    @foreach ($col['tarjetas'] as $c)
                        @php $vencida = $c->estado !== 'pagado' && $c->vencimiento->toDateString() < \App\Modules\Nucleo\Tiempo\Calendario::hoy(); @endphp
                        <div data-tq-fila="pago-{{ $c->id }}" class="rounded-lg bg-white p-3 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                            <div class="flex justify-between gap-2 text-sm">
                                <span class="font-medium">{{ $c->razon_social }}</span>
                                <span class="font-mono">{{ number_format((float) $c->total, 2) }}</span>
                            </div>
                            <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                {{ $c->serie_numero }} · {{ \Illuminate\Support\Facades\DB::table('proyectos')->where('id', $c->proyecto_id)->value('codigo') ?? 'gasto directo' }}
                                · <span @class(['text-danger-600 font-semibold' => $vencida])>{{ $c->estado === 'programado' ? 'pago el '.$c->pago_programado?->format('d/m') : 'vence '.$c->vencimiento->format('d/m') }}</span>
                                @if ($c->cruce === 'diferencia') · <span class="text-danger-600">diferencia en el cruce</span> @endif
                            </div>
                            @if ($clave !== 'pagado' && \App\Modules\Erp\Finanzas\Filament\Pages\Base::puede(\App\Modules\Erp\Finanzas\Services\FinanzasService::PERMISO_PAGAR))
                                <div class="mt-2 flex gap-2">
                                    @if ($clave === 'por_programar')
                                        <x-filament::button size="xs" color="gray" wire:click="programar({{ $c->id }})">Programar</x-filament::button>
                                    @endif
                                    <x-filament::button size="xs" color="success" wire:click="pagar({{ $c->id }})">Pagar</x-filament::button>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>
</x-filament-panels::page>
