{{--
    Observación 6.11: el resumen rápido del contrato en el panel deslizante de `/contratos`: lo de «Lo que hay que mirar
    todos los días» de la ficha (plazo, penalidad diaria con su tope, aplicada y en riesgo, garantía), la barra doble y
    los hitos. Recibe `$t`, una tarjeta de `TableroContratos::tarjeta()`.
--}}
@php $soles = fn ($m): string => 'S/ '.number_format((float) $m, 2); @endphp
<div class="tq-ct" style="gap: 1.1rem">
    <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 0.5rem">
        <x-filament::badge :color="$t['estado_color']">{{ $t['estado_etiqueta'] }}</x-filament::badge>
        <span class="tq-ct-suave" style="font-size: 0.85rem">{{ $t['cliente'] }}</span>
    </div>

    <dl style="display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 0.9rem 1.25rem; font-size: 0.875rem">
        <div>
            <dt class="tq-ct-suave">Plazo</dt>
            <dd @class(['font-semibold', 'tq-ct-coral' => $t['vencido']])>{{ $t['plazo_texto'] }}</dd>
            <dd class="tq-ct-suave" style="font-size: 0.75rem">{{ $t['plazo'] }} días · fin {{ $t['fin']->format('d/m/Y') }}</dd>
        </div>
        <div>
            <dt class="tq-ct-suave">Monto vigente</dt>
            <dd class="font-semibold" style="font-family: ui-monospace, monospace">{{ $soles($t['monto']) }}</dd>
            <dd class="tq-ct-suave" style="font-size: 0.75rem">{{ $t['numero_entidad'] ?? 'Sin número de la entidad' }}</dd>
        </div>
        <div>
            <dt class="tq-ct-suave">Penalidad diaria</dt>
            <dd class="font-semibold" style="font-family: ui-monospace, monospace">{{ $soles($t['diaria']) }}</dd>
            <dd class="tq-ct-suave" style="font-size: 0.75rem">tope {{ $soles($t['tope']) }}</dd>
        </div>
        <div>
            <dt class="tq-ct-suave">Penalidad aplicada</dt>
            <dd @class(['font-semibold', 'tq-ct-coral' => bccomp($t['aplicada'], '0', 2) > 0]) style="font-family: ui-monospace, monospace">{{ $soles($t['aplicada']) }}</dd>
            @if (bccomp($t['riesgo'], '0', 2) > 0)
                <dd class="tq-ct-coral" style="font-size: 0.75rem">en riesgo {{ $soles($t['riesgo']) }}</dd>
            @endif
        </div>
        <div style="grid-column: 1 / -1">
            <dt class="tq-ct-suave">Garantía</dt>
            <dd class="tq-ct-semaforo">
                <span class="tq-ct-luz" data-tono="{{ $t['fianza']['tono'] }}" aria-hidden="true"></span>
                <span @class(['font-semibold', 'tq-ct-coral' => $t['fianza']['tono'] === 'coral'])>{{ $t['fianza']['texto'] }}</span>
            </dd>
            @if ($t['fianza']['banco'])
                <dd class="tq-ct-suave" style="font-size: 0.75rem">{{ $t['fianza']['banco'] }} · {{ $soles($t['fianza']['monto']) }} · vence {{ $t['fianza']['vence'] }}</dd>
            @endif
        </div>
    </dl>

    @include('contratos::barras', ['t' => $t])

    @if ($t['hitos']['proximo'])
        <div class="tq-ct-suave" style="font-size: 0.8rem">Próximo hito: {{ $t['hitos']['proximo'] }}</div>
    @endif
</div>
