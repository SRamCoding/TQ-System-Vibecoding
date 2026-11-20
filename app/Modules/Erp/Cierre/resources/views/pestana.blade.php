@php $n = fn ($m) => number_format((float) $m, 2); @endphp
<div class="space-y-6">
    <x-filament::section :heading="$estado === 'cerrado' ? 'Lista de verificación al cerrar' : 'Cerrar proyecto · '.(count($bloqueos) === 0 ? 'nada pendiente' : count($bloqueos).' pendientes')"
        description="El proyecto termina cuando no queda nada abierto con el cliente, con los proveedores ni con el banco.">
        <x-slot name="afterHeader">{{ $this->cerrarAction }}</x-slot>
        <table class="w-full text-sm">
            <thead><tr class="text-left text-gray-500 dark:text-gray-400"><th class="py-1">Condición</th><th class="py-1">Estado</th></tr></thead>
            <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                @foreach ($verificaciones as $v)
                    <tr>
                        <td class="py-1.5">{{ $v['que'] }}@unless ($v['bloquea'])<span class="text-gray-400"> · aviso</span>@endunless</td>
                        <td @class(['py-1.5', 'text-success-600 dark:text-success-400' => $v['resuelto'], 'text-danger-600 dark:text-danger-400 font-medium' => ! $v['resuelto'] && $v['bloquea'], 'text-warning-600' => ! $v['resuelto'] && ! $v['bloquea']])>{{ $v['resuelto'] ? '✓ ' : '' }}{{ $v['detalle'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </x-filament::section>

    @if ($cierre)
        <x-filament::section heading="Lo que quedó congelado" :description="'Cerrado el '.\Carbon\Carbon::parse($cierre->fecha)->format('d/m/Y').' · vendido '.$n($cierre->vendido).' · facturado '.$n($cierre->facturado).' · costo real '.$n($cierre->costo).' · margen real '.$n($cierre->margen_real).($cierre->margen_pct !== null ? ' ('.$cierre->margen_pct.' %)' : '')">
            <table class="w-full text-sm">
                <thead><tr class="text-left text-gray-500 dark:text-gray-400"><th class="py-1">Partida</th><th class="py-1 text-right">Presupuestado</th><th class="py-1 text-right">Real</th><th class="py-1 text-right">Diferencia</th></tr></thead>
                <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                    @foreach ($partidas as $p)
                        <tr><td class="py-1"><span class="font-mono">{{ $p['codigo'] }}</span> {{ $p['nombre'] }}</td><td class="py-1 text-right font-mono">{{ $n($p['presupuestado']) }}</td><td class="py-1 text-right font-mono">{{ $n($p['ejecutado']) }}</td>
                            <td @class(['py-1 text-right font-mono', 'text-danger-600' => (float) $p['diferencia'] > 0, 'text-success-600' => (float) $p['diferencia'] < 0])>{{ (float) $p['diferencia'] > 0 ? '+' : '' }}{{ $n($p['diferencia']) }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </x-filament::section>

        <x-filament::section heading="Lo aprendido" description="Queda para Preventa como histórico de proyectos similares al armar el siguiente presupuesto.">
            <x-slot name="afterHeader">{{ $this->leccionesAction }}</x-slot>
            <p class="whitespace-pre-line text-sm">{{ $cierre->lecciones ?: 'Todavía no se escribe.' }}</p>
        </x-filament::section>
    @endif

    @if ($garantias->isNotEmpty())
        <x-filament::section heading="Garantías" description="Al terminar el plazo que la condiciona, la carta fianza se libera y se registra su devolución.">
            <ul class="divide-y divide-gray-100 text-sm dark:divide-white/5">
                @foreach ($garantias as $g)
                    <li class="flex items-center justify-between py-2">
                        <span>{{ ucfirst(str_replace('_', ' ', $g->tipo)) }} · {{ $g->banco }} {{ $g->numero }} · {{ $n($g->monto) }} · vence {{ \Carbon\Carbon::parse($g->vencimiento)->format('d/m/Y') }} · <strong>{{ $g->estado }}</strong></span>
                        @if ($estado === 'cerrado' && in_array($g->estado, ['vigente', 'renovada', 'liberada'], true))
                            {{ ($this->devolverAction)(['garantia' => $g->id]) }}
                        @endif
                    </li>
                @endforeach
            </ul>
        </x-filament::section>
    @endif

    <x-filament-actions::modals />
</div>
