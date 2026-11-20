{{--
    Pestaña «Cuenta comercial» en la ficha de Personas y empresas (plan 2.20, D13). Recibe `$cuenta` (ResumenCuenta o
    nulo), `$tipo` («Pública · nivel» o «Privada»), `$motivo` (si está inactiva), `$urlCuenta`, `$urlCrear` y
    `$fichaActiva`.
--}}
<div class="tq-cuenta-comercial">
    @if ($cuenta !== null)
        <x-filament::section heading="Es cuenta del CRM" icon="heroicon-o-building-storefront" compact>
            <dl class="grid gap-3 sm:grid-cols-2 text-sm">
                <div><dt class="text-gray-500">Ejecutivo</dt><dd>{{ $cuenta->ejecutivoNombre }}</dd></div>
                <div><dt class="text-gray-500">Cliente</dt><dd>{{ $cuenta->clienteDesde === null ? 'Prospecto' : 'Cliente desde '.$cuenta->clienteDesde->format('d/m/Y') }}</dd></div>
                <div><dt class="text-gray-500">Tipo</dt><dd>{{ $tipo }}</dd></div>
                @if ($cuenta->motivoNoUsable !== null)
                    <div><dt class="text-gray-500">Estado</dt><dd>{{ $cuenta->motivoNoUsable->etiqueta() }}{{ $motivo === null ? '' : ' · '.$motivo }}</dd></div>
                @endif
            </dl>
            <div class="mt-4">
                <x-filament::button tag="a" :href="$urlCuenta" icon="heroicon-o-pencil-square" color="gray">Editar, reasignar o marcar cliente</x-filament::button>
            </div>
        </x-filament::section>

        {{-- MV-022: lo que tenía la ficha de la cuenta. --}}
        <x-filament::section heading="Actividad" icon="heroicon-o-chat-bubble-left-right" compact class="mt-4">
            @if (($actividades ?? []) === [])
                <p class="text-sm text-gray-500">Sin visitas, llamadas, correos ni reuniones registradas.</p>
            @else
                <ul class="divide-y divide-gray-100 text-sm dark:divide-white/5">
                    @foreach ($actividades as $a)
                        <li class="flex flex-wrap items-baseline justify-between gap-2 py-2">
                            <span><strong>{{ $a['tipo'] }}</strong> · {{ $a['asunto'] }}@if ($a['oportunidad']) <span class="text-gray-500">· {{ $a['oportunidad'] }}</span>@endif
                                @if ($a['resultado'])<br><span class="text-gray-500">{{ $a['resultado'] }}</span>@endif</span>
                            <span @class(['whitespace-nowrap', 'text-danger-600 dark:text-danger-400' => ! $a['hecha'] && $a['fecha']->isPast(), 'text-gray-500' => $a['hecha']])>
                                {{ $a['fecha']->format('d/m/Y H:i') }} · {{ $a['quien'] }} · {{ $a['hecha'] ? 'hecha' : ($a['fecha']->isPast() ? 'vencida' : 'programada') }}
                            </span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-filament::section>

        @if (($historico['filas'] ?? []) !== [])
            <x-filament::section heading="Histórico de precios de la entidad" icon="heroicon-o-chart-bar" compact class="mt-4"
                :description="$historico['minimo'] === null ? null : 'Adjudica entre el '.$historico['minimo'].' % y el '.$historico['maximo'].' % del referencial · '.count($historico['filas']).' convocatorias.'">
                <table class="w-full text-sm">
                    <thead class="text-left text-gray-500"><tr><th class="py-1">Código</th><th>Objeto</th><th class="text-right">Adjudicado</th><th class="text-right">% del ref.</th><th>Ganó</th><th>Nosotros</th></tr></thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                        @foreach ($historico['filas'] as $f)
                            <tr>
                                <td class="py-1 font-mono">{{ $f['codigo'] }}</td><td>{{ $f['objeto'] }}</td>
                                <td class="text-right font-mono">{{ $f['adjudicado'] === null ? '—' : number_format((float) $f['adjudicado'], 2) }}</td>
                                <td class="text-right">{{ $f['porcentaje'] === null ? '—' : $f['porcentaje'].' %' }}</td>
                                <td>{{ $f['ganador'] ?? '—' }}</td><td>{{ $f['nosotros'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </x-filament::section>
        @endif
    @else
        <x-filament::section heading="Esta empresa no es cuenta del CRM" icon="heroicon-o-building-storefront" compact>
            @if ($urlCrear !== null)
                <x-filament::button tag="a" :href="$urlCrear" icon="heroicon-o-plus">Crear cuenta</x-filament::button>
            @elseif (! $fichaActiva)
                <p class="text-sm text-gray-500">La ficha está inactiva: reactívala antes de crear su cuenta.</p>
            @endif
        </x-filament::section>
    @endif
</div>
