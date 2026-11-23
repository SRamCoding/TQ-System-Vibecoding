{{--
    Observación 6.11: la barra doble de plazo contra avance y los hitos en miniatura de un contrato (tarjeta y panel).
    Recibe `$t`, una tarjeta de `TableroContratos::tarjeta()`.
--}}
@php
    $tiempo = (int) $t['tiempo_pct'];
    $avance = $t['avance_pct'] === null ? 0 : min(100, (int) $t['avance_pct']);
@endphp
<div class="tq-ct-barras">
    <div class="tq-ct-barra-fila">
        <span class="tq-ct-barra-nombre">Plazo</span>
        <span class="tq-ct-barra" role="img" aria-label="Plazo transcurrido {{ $t['tiempo_pct'] }} %">
            <span class="tq-ct-barra-lleno" data-tono="{{ $t['vencido'] ? 'coral' : 'plazo' }}" style="width: {{ $tiempo }}%"></span>
        </span>
        <span @class(['tq-ct-barra-texto', 'tq-ct-coral' => $t['vencido']])>{{ $t['tiempo_pct'] }} % · {{ $t['plazo_texto'] }}</span>
    </div>
    <div class="tq-ct-barra-fila">
        <span class="tq-ct-barra-nombre">Avance</span>
        <span class="tq-ct-barra" role="img" aria-label="Avance valorizado {{ $t['avance_pct'] ?? 0 }} %">
            <span class="tq-ct-barra-lleno" data-tono="{{ $t['avance_pct'] === null ? 'gris' : ($t['atrasado'] ? 'coral' : 'avance') }}" style="width: {{ $avance }}%"></span>
        </span>
        <span @class(['tq-ct-barra-texto', 'tq-ct-coral' => $t['atrasado'], 'tq-ct-suave' => $t['avance_pct'] === null])>
            {{ $t['avance_pct'] ?? '0' }} %@if ($t['atrasado']) · atrasado {{ $t['atraso'] }} puntos @elseif ($t['avance_pct'] === null) · sin valorizaciones @endif
        </span>
    </div>

    @if ($t['hitos']['puntos'] !== [])
        <div class="tq-ct-hitos" aria-label="Hitos">
            <span class="tq-ct-hitos-linea"></span>
            @if ($t['hitos']['hoy'] !== null)
                <span class="tq-ct-hoy" style="left: {{ $t['hitos']['hoy'] }}%"><span>hoy</span></span>
            @endif
            @foreach ($t['hitos']['puntos'] as $h)
                <span class="tq-ct-hito" data-estado="{{ $h['estado'] }}" style="left: {{ $h['pos'] }}%" title="{{ $h['nombre'] }} · {{ $h['fecha'] }}{{ $h['estado'] === 'cumplido' ? ' · cumplido' : ($h['estado'] === 'vencido' ? ' · vencido sin cumplir' : '') }}"></span>
            @endforeach
        </div>
        <div class="tq-ct-hitos-pie">
            @foreach ($t['hitos']['puntos'] as $h)
                <span @class(['tq-ct-coral' => $h['estado'] === 'vencido', 'tq-ct-hecho' => $h['estado'] === 'cumplido'])>{{ $h['corto'] }}</span>
            @endforeach
        </div>
    @endif
</div>
