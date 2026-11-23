{{-- Observación 7.6: en la ficha de la campaña, su retorno, sus gastos reales y (si es a clientes) el seguimiento con su plantilla para copiar. --}}
@php $t = $tarjeta; $soles = fn ($m) => 'S/ '.number_format((float) $m, 2); @endphp
<div style="display: flex; flex-direction: column; gap: 16px">
    <x-filament::section heading="Retorno" compact>
        <div style="display: flex; flex-wrap: wrap; gap: 28px; font-variant-numeric: tabular-nums">
            <div><div class="text-xs text-gray-500">Gastado / presupuesto</div><div class="font-semibold" @if ($t['sePaso']) style="color: var(--danger-600)" @endif>{{ $soles($t['gastado']) }} / {{ $soles($t['presupuesto']) }}</div></div>
            <div><div class="text-xs text-gray-500">Ganado</div><div class="font-semibold">{{ $soles($t['ganado']) }} · {{ $t['ganadas'] }} {{ $t['ganadas'] === 1 ? 'proyecto' : 'proyectos' }}</div></div>
            <div><div class="text-xs text-gray-500">Margen de lo ganado{{ $t['margenPrevisto'] ? ' (previsto)' : '' }}</div><div class="font-semibold">{{ $soles($t['margen']) }}</div></div>
            <div><div class="text-xs text-gray-500">Retorno</div><div class="font-semibold" style="color: {{ $t['retorno'] !== null && (float) $t['retorno'] >= 1 ? 'var(--success-600)' : 'var(--danger-600)' }}">{{ $t['retorno'] === null ? '—' : $t['retorno'].'×' }}</div></div>
        </div>
        @if ($gastos !== [])
            <ul class="mt-3 text-sm divide-y divide-gray-100 dark:divide-white/5">
                @foreach ($gastos as $g)<li class="flex justify-between py-1"><span>{{ $g['numero'] }} · {{ $g['descripcion'] }} · {{ $g['proveedor'] }}</span><span class="font-mono">{{ $soles($g['monto']) }}</span></li>@endforeach
            </ul>
        @endif
    </x-filament::section>

    @if ($destinatarios !== [])
        <x-filament::section heading="Clientes y seguimiento" compact>
            <p class="text-sm text-gray-500 mb-3">No se envía nada: cada paso es una tarea en la agenda (y en Mien) del responsable. Copia el texto de cada cliente.</p>
            @foreach ($pasos as $p)
                <div class="mb-4" x-data="{ copiado: null }">
                    <div class="font-semibold text-sm mb-1">Día {{ $p['dia'] }} · {{ ['correo' => 'Correo', 'whatsapp' => 'WhatsApp', 'llamada' => 'Llamada'][$p['canal']] ?? $p['canal'] }}</div>
                    @foreach ($p['mensajes'] as $i => $m)
                        <div class="flex items-start gap-3 py-1 text-sm">
                            <span style="width: 16rem; flex: none" class="text-gray-500">{{ $m['cliente'] }}</span>
                            <span style="flex: 1">{{ $m['texto'] }}</span>
                            <x-filament::button size="xs" color="gray" x-on:click="navigator.clipboard.writeText({{ \Illuminate\Support\Js::from($m['texto']) }}); copiado = {{ $i }}"><span x-text="copiado === {{ $i }} ? 'Copiado' : 'Copiar'"></span></x-filament::button>
                        </div>
                    @endforeach
                </div>
            @endforeach
        </x-filament::section>
    @endif
</div>
