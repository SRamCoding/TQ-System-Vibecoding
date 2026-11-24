{{--
    Observación 9.10 (MV-104): la ficha de la campaña. Cabecera (nombre, tipo, canal, fechas, responsable, estado), las
    cifras de la tarjeta en grande y pestañas Contactos · Oportunidades · Gastos · Seguimiento · Actividad.
--}}
@php
    $t = $tarjeta;
    $soles = fn ($m) => 'S/ '.number_format((float) $m, 2);
    $veces = fn (?string $r) => $r === null ? 'sin gasto' : rtrim(rtrim(number_format((float) $r, 1), '0'), '.').'×';
    $estados = ['planificada' => ['Planificada', 'var(--gray-500)'], 'en_curso' => ['En curso', 'var(--primary-600)'], 'terminada' => ['Terminada', 'var(--gray-500)']];
    $f = $ficha;
@endphp
<div class="tq-cf" x-data="{ pestana: 'contactos' }" style="display: flex; flex-direction: column; gap: 16px">
    <style>
        .tq-cf-card { background: var(--tq-superficie, white); border: 1px solid var(--tq-borde, var(--gray-200)); border-radius: 14px; padding: 16px 18px; }
        .dark .tq-cf-card { background: var(--tq-superficie, var(--gray-900)); border-color: var(--tq-borde, rgb(255 255 255 / .1)); }
        .tq-cf-cifras { display: grid; grid-template-columns: repeat(auto-fit, minmax(10rem, 1fr)); gap: 12px; }
        .tq-cf-cifra .t { font-size: 12px; color: var(--gray-500); }
        .tq-cf-cifra .v { font-size: 22px; font-weight: 700; font-variant-numeric: tabular-nums; }
        .tq-cf-cifra .d { font-size: 12px; color: var(--gray-500); }
        .tq-cf-tabs { display: flex; gap: 4px; flex-wrap: wrap; border-bottom: 1px solid var(--tq-borde, var(--gray-200)); }
        .tq-cf-tabs button { padding: 8px 12px; font-size: 14px; color: var(--gray-500); border-bottom: 2px solid transparent; }
        .tq-cf-tabs button[aria-selected="true"] { color: var(--primary-700); border-color: var(--primary-600); font-weight: 600; }
        .dark .tq-cf-tabs button[aria-selected="true"] { color: var(--primary-300); }
    </style>

    <div><a href="{{ $volver }}" wire:navigate style="font-size: 13px; color: var(--primary-600)">← Volver a Campañas</a></div>

    <section class="tq-cf-card" style="display: flex; gap: 16px; align-items: flex-start; flex-wrap: wrap">
        <div style="flex: 1; min-width: 16rem">
            <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap">
                <h1 style="font-size: 22px; font-weight: 700">{{ $c->nombre }}</h1>
                <span style="font-size: 12px; font-weight: 600; border-radius: 999px; padding: 2px 10px; border: 1px solid currentColor; color: {{ $estados[$estado][1] }}">{{ $estados[$estado][0] }}</span>
            </div>
            <div style="font-size: 13px; color: var(--gray-500); margin-top: 4px; display: flex; gap: 10px; flex-wrap: wrap; align-items: center">
                <span>{{ \App\Modules\Crm\Campanias\Models\Campania::TIPOS[$c->tipo] ?? $c->tipo }}</span>
                @if ($canal)<span style="display: inline-flex; gap: 4px; align-items: center"><x-filament::icon :icon="$canal['icono']" class="h-4 w-4" />{{ $canal['nombre'] }}</span>@endif
                <span>{{ $c->desde?->format('d/m/Y') }} – {{ $c->hasta?->format('d/m/Y') ?? 'sin fin' }}</span>
                <span>Responsable: {{ $responsable ?? '—' }}</span>
            </div>
            @if ($c->objetivo)<p style="font-size: 14px; margin-top: 8px">{{ $c->objetivo }}</p>@endif
        </div>
        <div style="text-align: end">
            <div style="font-size: 12px; color: var(--gray-500)">Retorno</div>
            <div>@include('campanias::retorno', ['r' => $t['retorno'], 'e' => $t['esperado'], 'ganadas' => $t['ganadas'], 'detalle' => $t['esperadoDetalle'], 'gastado' => $t['gastado'], 'tamano' => 28])</div>
        </div>
    </section>

    <section class="tq-cf-cifras">
        <div class="tq-cf-card tq-cf-cifra"><div class="t">Invertido / presupuesto</div><div class="v" @if ($t['sePaso']) style="color: var(--danger-600)" @endif>{{ $soles($t['gastado']) }}</div><div class="d">de {{ $soles($t['presupuesto']) }}</div></div>
        <div class="tq-cf-card tq-cf-cifra"><div class="t">Contactos</div><div class="v">{{ $t['contactos'] }}</div><div class="d">{{ $t['nuevos'] }} nuevos · {{ $t['yaRegistrados'] }} ya registrados</div></div>
        <div class="tq-cf-card tq-cf-cifra"><div class="t">Oportunidades</div><div class="v">{{ $t['oportunidades'] }}</div><div class="d">{{ $t['oportunidades'] }} de {{ $t['contactos'] }}</div></div>
        <div class="tq-cf-card tq-cf-cifra"><div class="t">Ganadas</div><div class="v">{{ $t['ganadas'] }}</div><div class="d">{{ $soles($t['ganado']) }} ganado</div></div>
        <div class="tq-cf-card tq-cf-cifra"><div class="t">Margen de lo ganado{{ $t['margenPrevisto'] && $t['ganadas'] > 0 ? ' (previsto)' : '' }}</div><div class="v">{{ $soles($t['margen']) }}</div></div>
        <div class="tq-cf-card tq-cf-cifra"><div class="t">Costo por contacto</div><div class="v">{{ $t['contactos'] > 0 && (float) $t['gastado'] > 0 ? $soles(bcdiv($t['gastado'], (string) $t['contactos'], 2)) : '—' }}</div><div class="d">{{ $t['porContactoNuevo'] === null ? 'sin contactos nuevos' : $soles($t['porContactoNuevo']).' por contacto nuevo' }}</div></div>
    </section>

    <div class="tq-cf-card" style="display: flex; flex-direction: column; gap: 12px">
        <div class="tq-cf-tabs" role="tablist">
            @foreach (['contactos' => 'Contactos · '.count($contactos), 'oportunidades' => 'Oportunidades · '.count($oportunidades), 'gastos' => 'Gastos · '.count($gastos['gastos']), 'seguimiento' => 'Seguimiento', 'actividad' => 'Actividad'] as $clave => $titulo)
                <button type="button" role="tab" x-bind:aria-selected="pestana === '{{ $clave }}'" x-on:click="pestana = '{{ $clave }}'">{{ $titulo }}</button>
            @endforeach
        </div>
        <div x-show="pestana === 'contactos'">@include('campanias::listas', ['tipo' => 'contactos', 'filas' => $contactos])</div>
        <div x-show="pestana === 'oportunidades'" x-cloak>@include('campanias::listas', ['tipo' => 'oportunidades', 'filas' => $oportunidades])</div>
        <div x-show="pestana === 'gastos'" x-cloak>@include('campanias::listas', ['tipo' => 'gastos', 'filas' => $gastos])</div>
        <div x-show="pestana === 'seguimiento'" x-cloak>
            @if ($f['destinatarios'] === [])
                <p style="font-size: 14px; color: var(--gray-500)">El seguimiento es de las campañas a clientes (las que nacen de «Volver a vender»).</p>
            @else
                @foreach ($f['pasos'] as $p)
                    <div style="margin-bottom: 14px">
                        <div style="font-weight: 600; font-size: 14px">Día {{ $p['dia'] }} · {{ ['correo' => 'Correo', 'whatsapp' => 'WhatsApp', 'llamada' => 'Llamada'][$p['canal']] ?? $p['canal'] }}
                            <span style="font-size: 12px; color: {{ ($p['hecho'] ?? false) ? 'var(--success-600)' : 'var(--gray-500)' }}">· {{ ($p['hecho'] ?? false) ? 'hecho' : 'pendiente en Mien' }}</span></div>
                        @foreach ($p['mensajes'] as $m)<div style="font-size: 13px; padding: 2px 0"><span style="color: var(--gray-500)">{{ $m['cliente'] }}:</span> {{ $m['texto'] }}</div>@endforeach
                    </div>
                @endforeach
            @endif
        </div>
        <div x-show="pestana === 'actividad'" x-cloak style="display: flex; flex-direction: column; gap: 6px">
            @foreach ($actividad as $a)
                <div style="font-size: 13px"><span style="color: var(--gray-500); font-variant-numeric: tabular-nums">{{ $a['cuando'] }}</span> · {{ $a['texto'] }}</div>
            @endforeach
        </div>
    </div>

    @include('campanias::atajo', ['atajo' => $atajo])
</div>
