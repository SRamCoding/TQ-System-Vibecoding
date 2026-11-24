{{--
    Observación 9.9: el embudo de una campaña o un canal. Contactos sin barra («15 contactos llegaron · 11 nuevos · 4 ya
    registrados», con la lista de los ya registrados al pasar el puntero); oportunidades y ganadas con barra y «N de X».
    Recibe `$t` con contactos, nuevos, yaRegistrados, listaRegistrados, oportunidades (o calificados) y ganadas.
--}}
@php
    $ops = $t['oportunidades'] ?? $t['calificados'] ?? 0;
    $pct = fn (int $a, int $b) => $b > 0 ? (int) round($a * 100 / $b) : 0;
    // Observación 9.10: en Campañas, cada cifra es un atajo (ventana al medio); en Canales, no.
    $atajo = fn (string $tipo) => isset($campaniaId) ? 'data-atajo role="button" tabindex="0" class="tq-camp-atajo" wire:click="verAtajo(\''.$tipo.'\', '.$campaniaId.')"' : '';
@endphp
<div style="display: flex; flex-direction: column; gap: 8px">
    <div class="tq-camp-llegaron">
        <span {!! $atajo('contactos') !!}><b>{{ $t['contactos'] }}</b> {{ $t['contactos'] === 1 ? 'contacto llegó' : 'contactos llegaron' }}</span>
        · {{ $t['nuevos'] }} {{ $t['nuevos'] === 1 ? 'nuevo' : 'nuevos' }}
        · <span class="tq-camp-ya" title="{{ $t['listaRegistrados'] === [] ? 'Ninguno' : implode("\n", array_slice($t['listaRegistrados'], 0, 12)).(count($t['listaRegistrados']) > 12 ? "\n…" : '') }}">{{ $t['yaRegistrados'] }} ya {{ $t['yaRegistrados'] === 1 ? 'registrado' : 'registrados' }}</span>
    </div>
    <div class="tq-camp-embudo">
        <span>{{ isset($t['calificados']) && ! isset($t['oportunidades']) ? 'Calificados' : 'Oportunidades' }}</span>
        <span class="barra" title="{{ $pct($ops, $t['contactos']) }} % de los contactos"><span style="width: {{ $ops > 0 ? max(3, $pct($ops, $t['contactos'])) : 0 }}%"></span></span>
        <b style="text-align: right; white-space: nowrap; color: var(--tq-texto, inherit)"><span {!! $atajo('oportunidades') !!}>{{ $ops }} de {{ $t['contactos'] }}</span></b>
        <span>Ganadas</span>
        <span class="barra" title="{{ $pct($t['ganadas'], $ops) }} % de las oportunidades"><span style="width: {{ $t['ganadas'] > 0 ? max(3, $pct($t['ganadas'], $ops)) : 0 }}%; background: var(--c-verde)"></span></span>
        <b style="text-align: right; white-space: nowrap; color: var(--tq-texto, inherit)"><span {!! $atajo('ganadas') !!}>{{ $t['ganadas'] }} de {{ $ops }}</span></b>
    </div>
</div>
