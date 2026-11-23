{{--
    Observación 6.11: la tarjeta de un contrato. Clic: el resumen en el panel deslizante; doble clic: la ficha completa.
    El borde izquierdo avisa: coral si está vencido, atrasado o con la carta fianza a 30 días; ámbar si la carta vence en
    60 o si es borrador; gris si ya terminó; turquesa (misionales) si va bien.
--}}
@php
    $alerta = match (true) {
        $t['estado'] === 'borrador' => 'ambar',
        $t['estado'] !== 'vigente' => 'gris',
        $t['vencido'] || $t['atrasado'] || $t['fianza']['tono'] === 'coral' => 'coral',
        $t['fianza']['tono'] === 'ambar' => 'ambar',
        default => 'bien',
    };
    $ficha = \App\Modules\Erp\Contratos\Filament\Resources\Contratos\Pages\VerContrato::getUrl(['record' => $t['id']]);
@endphp
<article class="tq-ct-tarjeta" data-tq-fila="contrato-{{ $t['id'] }}" wire:key="contrato-{{ $t['id'] }}" data-alerta="{{ $alerta }}" data-contrato="{{ $t['id'] }}"
    role="button" tabindex="0" aria-label="{{ $t['codigo'] }} · {{ $t['objeto'] }}"
    x-data="{ espera: null }"
    x-on:click="if ($event.target.closest('[data-sin-panel]')) return; clearTimeout(espera); espera = setTimeout(() => $wire.mountAction('resumen', { contrato: {{ $t['id'] }} }), 220)"
    x-on:dblclick="if ($event.target.closest('[data-sin-panel]')) return; clearTimeout(espera); window.location.href = @js($ficha)"
    x-on:keydown.enter.prevent="$wire.mountAction('resumen', { contrato: {{ $t['id'] }} })">
    <div class="tq-ct-cabeza">
        <div style="min-width: 0">
            <div class="tq-ct-cliente">{{ $t['cliente'] }}</div>
            <div class="tq-ct-objeto">{{ $t['objeto'] }}</div>
            <div class="tq-ct-codigo">{{ $t['codigo'] }}</div>
            <div class="tq-ct-suave" style="font-size: 0.75rem">{{ $t['numero_entidad'] ?? 'Sin número de la entidad' }}</div>
        </div>
        <div style="display: flex; flex-direction: column; align-items: flex-end; gap: 0.35rem">
            <span class="tq-ct-monto">S/ {{ number_format((float) $t['monto'], 2) }}</span>
            @if ($t['tiene_adendas'])
                <span class="tq-ct-suave" style="font-size: 0.7rem">con adendas</span>
            @endif
            <x-filament::badge :color="$t['estado_color']" size="sm">{{ $t['estado_etiqueta'] }}</x-filament::badge>
        </div>
    </div>

    @include('contratos::barras', ['t' => $t])

    <div class="tq-ct-pie">
        <span>
            Penalidad diaria <span style="font-family: ui-monospace, monospace">S/ {{ number_format((float) $t['diaria'], 2) }}</span>
            @if (bccomp($t['aplicada'], '0', 2) > 0)
                · <span class="tq-ct-coral">aplicada S/ {{ number_format((float) $t['aplicada'], 2) }}</span>
            @endif
            @if (bccomp($t['riesgo'], '0', 2) > 0)
                · <span class="tq-ct-coral">en riesgo S/ {{ number_format((float) $t['riesgo'], 2) }}</span>
            @endif
        </span>
        <span class="tq-ct-semaforo" title="{{ $t['fianza']['banco'] ? $t['fianza']['banco'].' · S/ '.number_format((float) $t['fianza']['monto'], 2).' · vence '.$t['fianza']['vence'] : '' }}">
            <span class="tq-ct-luz" data-tono="{{ $t['fianza']['tono'] }}" aria-hidden="true"></span>
            <span @class(['tq-ct-coral' => $t['fianza']['tono'] === 'coral'])>{{ $t['fianza']['texto'] }}</span>
        </span>
    </div>

    @if ($t['estado'] === 'borrador' && $gestiona)
        <div data-sin-panel>
            <x-filament::button size="sm" color="success" icon="heroicon-o-check-badge" wire:click="mountAction('activar', { contrato: {{ $t['id'] }} })">Completar y activar</x-filament::button>
        </div>
    @endif
</article>
