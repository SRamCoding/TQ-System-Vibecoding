{{-- Observación 6.7 (MV-067): el detalle de una persona en el panel deslizante: en qué proyectos está cada semana y con qué porcentaje. --}}
@if ($t === null)
    <p>Esta persona ya no está activa.</p>
@else
    <div style="display: flex; flex-direction: column; gap: 1.25rem">
        <div style="display: flex; align-items: center; gap: 1rem">
            @include('personal::avatar', ['a' => $t['avatar'], 'tam' => 72])
            <div>
                <div style="font-size: 0.9rem">{{ $t['categoria'] }} · {{ $t['vinculo'] }}</div>
                <div style="font-size: 0.8rem; font-weight: 600; color: {{ $t['sctr_mal'] ? '#C2532F' : 'var(--tq-texto-suave, var(--gray-500))' }}">{{ $t['sctr'] }}</div>
            </div>
        </div>
        @foreach ($t['semanas'] as $s)
            <section style="display: flex; flex-direction: column; gap: 0.4rem">
                <div style="display: flex; justify-content: space-between; font-size: 0.85rem; font-weight: 600">
                    <span>{{ $s['etiqueta'] }}</span>
                    <span style="font-variant-numeric: tabular-nums; color: {{ $s['tono'] === 'sobre' ? '#C2532F' : ($s['tono'] === 'libre' ? 'var(--gray-400)' : 'inherit') }}">{{ $s['total'] }} %{{ $s['tono'] === 'sobre' ? ' · sobreasignado' : '' }}</span>
                </div>
                @forelse ($s['proyectos'] as $p)
                    <div style="display: flex; justify-content: space-between; gap: 0.75rem; font-size: 0.8rem; padding: 0.45rem 0.65rem; border-radius: 0.5rem; border: 1px solid var(--tq-borde, var(--gray-200))">
                        <span style="min-width: 0"><span style="font-family: ui-monospace, monospace">{{ $p['codigo'] }}</span> · {{ $p['nombre'] }}<br><span style="color: var(--tq-texto-suave, var(--gray-500))">{{ $p['vigencia'] }}</span></span>
                        <span style="font-weight: 600; font-variant-numeric: tabular-nums; white-space: nowrap">{{ $p['porcentaje'] }} %</span>
                    </div>
                @empty
                    <div style="font-size: 0.8rem; color: var(--tq-texto-suave, var(--gray-500))">Sin asignar</div>
                @endforelse
            </section>
        @endforeach
    </div>
@endif
