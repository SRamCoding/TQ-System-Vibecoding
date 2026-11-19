{{--
    Panel «Avanzar» de una oportunidad (plan 2.21, D11.6): lo que exige la etapa destino, acumulado (RequisitosDeEtapa),
    con lo cumplido y lo faltante, y lo que cambia al avanzar. La calificación que falte se completa en el formulario de
    abajo. El color va siempre junto a su texto. Recibe `$oportunidadId`, `$requisitos` (texto, cumple, requisito, forma)
    y `$cambios` (que, antes, despues).
    Plan 2.21d, D8 (la compuerta se rellena ahí mismo): un requisito que falta y que su módulo deja cumplir desde aquí
    lleva su botón (la acción registrada del módulo, `cumplirRequisito`) o su enlace (cuando la acción pide más, como
    aceptar con el acta). La próxima acción no se pide hasta CRM-05 (D14): su lugar queda en este panel.
--}}
<div class="tq-avanzar">
    <style>
        .tq-avanzar { display: flex; flex-direction: column; gap: 1rem; font-size: 0.875rem; }
        .tq-avanzar h3 { font-weight: 600; margin-bottom: 0.375rem; }
        .tq-avanzar ul { display: flex; flex-direction: column; gap: 0.25rem; }
        .tq-avanzar li { display: flex; gap: 0.5rem; align-items: baseline; }
        .tq-avanzar-cumple { color: var(--success-600); font-weight: 600; }
        .tq-avanzar-falta { color: var(--warning-600); font-weight: 600; }
        .dark .tq-avanzar-cumple { color: var(--success-400); }
        .dark .tq-avanzar-falta { color: var(--warning-400); }
        .tq-avanzar table { width: 100%; border-collapse: collapse; }
        .tq-avanzar th, .tq-avanzar td { text-align: left; padding: 0.25rem 0.5rem 0.25rem 0; }
        .tq-avanzar th { font-weight: 500; color: var(--gray-500); }
    </style>

    <section>
        <h3>Esta etapa exige</h3>
        @if ($requisitos === [])
            <p>Nada en particular.</p>
        @else
            <ul>
                @foreach ($requisitos as $requisito)
                    <li data-requisito="{{ $requisito['cumple'] ? 'cumple' : 'falta' }}">
                        @if ($requisito['cumple'])
                            <span class="tq-avanzar-cumple">✓ Cumplido</span>
                        @else
                            <span class="tq-avanzar-falta">• Falta</span>
                        @endif
                        <span>{{ $requisito['texto'] }}</span>
                        @if (! $requisito['cumple'] && ($requisito['forma'] ?? null) !== null)
                            @if ($requisito['forma']->accion !== null)
                                <x-filament::link tag="button" size="sm" icon="heroicon-m-bolt" wire:click="cumplirRequisito(@js($requisito['requisito']), {{ $oportunidadId }})" data-cumplir="{{ class_basename($requisito['requisito']) }}">{{ $requisito['forma']->etiqueta }}</x-filament::link>
                            @else
                                <x-filament::link :href="$requisito['forma']->url" target="_blank" size="sm" icon="heroicon-m-arrow-top-right-on-square" data-cumplir="{{ class_basename($requisito['requisito']) }}">{{ $requisito['forma']->etiqueta }}</x-filament::link>
                            @endif
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    <section>
        <h3>Qué cambia al avanzar</h3>
        <table>
            <thead><tr><th></th><th>Ahora</th><th>Al avanzar</th></tr></thead>
            <tbody>
                @foreach ($cambios as $cambio)
                    <tr><th>{{ $cambio['que'] }}</th><td>{{ $cambio['antes'] }}</td><td><strong>{{ $cambio['despues'] }}</strong></td></tr>
                @endforeach
            </tbody>
        </table>
    </section>
</div>
