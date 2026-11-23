{{-- MV-070: antes de cerrar la semana, lo que se cargará a cada partida (horas y monto) y los avisos pendientes. --}}
<div style="display: flex; flex-direction: column; gap: 1.25rem">
    <section style="display: flex; flex-direction: column; gap: 0.5rem">
        <div style="font-size: 0.8rem; font-weight: 600; color: var(--tq-texto-suave, var(--gray-500))">Se carga a cada partida</div>
        @forelse ($r['partidas'] as $p)
            <div style="display: flex; justify-content: space-between; gap: 0.75rem; font-size: 0.85rem; padding: 0.5rem 0.7rem; border-radius: 0.5rem; border: 1px solid var(--tq-borde, var(--gray-200))">
                <span>{{ $p['partida'] }}</span>
                <span style="white-space: nowrap; font-variant-numeric: tabular-nums">{{ $p['horas'] }} h · <span style="font-family: ui-monospace, monospace">S/ {{ number_format((float) $p['monto'], 2) }}</span></span>
            </div>
        @empty
            <div style="font-size: 0.85rem">Sin horas.</div>
        @endforelse
        <div style="display: flex; justify-content: space-between; font-weight: 700; font-size: 0.9rem; padding: 0 0.7rem">
            <span>Total</span><span style="font-variant-numeric: tabular-nums">{{ $r['horas'] }} h · S/ {{ number_format((float) $r['total'], 2) }}</span>
        </div>
    </section>
    @if ($r['avisos'] !== [])
        <section style="display: flex; flex-direction: column; gap: 0.35rem">
            <div style="font-size: 0.8rem; font-weight: 600; color: #C2532F">Avisos pendientes</div>
            @foreach ($r['avisos'] as $a)
                <div style="font-size: 0.82rem">• {{ $a }}</div>
            @endforeach
        </section>
    @endif
</div>
