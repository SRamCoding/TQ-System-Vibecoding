{{--
    La vista «Línea de tiempo» de Oportunidades como el prototipo aprobado (plan 2.21d, D11): una fila por oportunidad
    con una barra por etapa (del color de la etapa, de la entrada a la salida), lo que falta hasta el cierre esperado en
    rayado, el rombo del cierre esperado o ✓ / ✕ en el cierre, los meses arriba, la línea de «hoy» y la leyenda. Al pasar
    el mouse por una barra, su etapa y sus fechas; tocar una fila abre la ficha en el panel (`abrirFicha`). Recibe lo de
    `DibujoDelCronograma::datos()`, `$pagina` (de 10 en 10), `$buscar` y `$fichaAbierta`. Colores de la paleta morada del
    CRM, por nombre. El buscador (pedido del desarrollador, 2026-11-16): mientras se escribe, la que mejor coincide queda
    primera y las coincidencias se marcan; al escribir se vuelve a la página 1.
--}}
<div @class(['tq-crono', 'tq-tablero-detras' => $fichaAbierta !== null]) data-cronograma>
    <style>
        .tq-crono { --tq-e-prospecto: var(--morado-300); --tq-e-calificada: var(--morado-400); --tq-e-propuesta_enviada: var(--morado-500); --tq-e-negociacion: var(--morado-700);
            overflow-x: auto; padding: 0.875rem 1rem; border-radius: 0.75rem; background: white; outline: 1px solid rgb(3 7 18 / 0.06); }
        .dark .tq-crono { background: var(--gray-900); outline-color: rgb(255 255 255 / 0.08); }
        .tq-crono-tabla { position: relative; min-width: 48rem; }
        .tq-crono-fila { display: grid; grid-template-columns: 16rem minmax(0, 1fr); align-items: center; min-height: 2.25rem; border-bottom: 1px solid var(--gray-100); width: 100%; text-align: start; }
        .dark .tq-crono-fila { border-bottom-color: var(--gray-800); }
        button.tq-crono-fila:hover, button.tq-crono-fila:focus-visible { background: var(--gray-50); }
        .dark button.tq-crono-fila:hover, .dark button.tq-crono-fila:focus-visible { background: rgb(255 255 255 / 0.04); }
        .tq-crono-quien { font-size: 0.8125rem; line-height: 1.25; padding-inline-end: 0.6rem; overflow: hidden; }
        .tq-crono-quien small { display: block; font-size: 0.6875rem; color: var(--gray-500); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .tq-crono-pista { position: relative; height: 2.25rem; }
        .tq-crono-tramo { position: absolute; top: 0.7rem; height: 0.75rem; border-radius: 3px; }
        .tq-crono-tramo:first-child { border-radius: 6px 3px 3px 6px; }
        .tq-crono-falta { position: absolute; top: 0.7rem; height: 0.75rem; background: repeating-linear-gradient(90deg, var(--morado-200) 0 4px, transparent 4px 7px); }
        .tq-crono-hito { position: absolute; top: 0.65rem; width: 0.875rem; height: 0.875rem; transform: translateX(-50%); }
        .tq-crono-mes { position: absolute; top: 0; bottom: 0; border-inline-start: 1px dashed var(--gray-200); font-size: 0.6875rem; color: var(--gray-500); padding-inline-start: 0.25rem; }
        .dark .tq-crono-mes { border-inline-start-color: var(--gray-700); }
        .tq-crono-hoy { position: absolute; top: 0; bottom: 0; width: 2px; background: var(--danger-600); opacity: 0.7; pointer-events: none; }
        .tq-crono-ley { display: flex; flex-wrap: wrap; gap: 0.75rem; margin-top: 0.75rem; font-size: 0.75rem; color: var(--gray-500); }
        .tq-crono-ley span { display: inline-flex; align-items: center; gap: 0.3rem; }
        .tq-crono-punto { width: 0.6rem; height: 0.6rem; border-radius: 9999px; display: inline-block; }
        .tq-crono-barra { display: flex; flex-wrap: wrap; align-items: center; gap: 0.75rem; margin-bottom: 0.75rem; }
        .tq-crono-barra .tq-crono-buscar { flex: 1 1 18rem; max-width: 28rem; }
        .tq-crono-fila[data-coincide] .tq-crono-quien b { color: var(--morado-700); }
        .dark .tq-crono-fila[data-coincide] .tq-crono-quien b { color: var(--morado-300); }
        .tq-crono-fila[data-primera] { background: color-mix(in oklab, var(--morado-500) 8%, transparent); }
    </style>

    <div class="tq-crono-barra">
        <div class="tq-crono-buscar">
            <x-filament::input.wrapper prefix-icon="heroicon-o-magnifying-glass">
                <x-filament::input type="search" wire:model.live.debounce.250ms="buscar" placeholder="Buscar por nombre, código o entidad…" aria-label="Buscar en la línea de tiempo" />
            </x-filament::input.wrapper>
        </div>
        @if ($buscar !== '')
            <span class="text-sm text-gray-500" role="status">{{ $coinciden === 0 ? 'Ninguna coincide' : ($coinciden === 1 ? '1 coincide' : $coinciden.' coinciden') }}</span>
        @endif
    </div>

    <div class="tq-crono-tabla">
        <div class="tq-crono-fila" style="min-height: 1.4rem">
            <div></div>
            <div class="tq-crono-pista" style="height: 1.4rem">
                @foreach ($meses as $mes)
                    <span class="tq-crono-mes" style="inset-inline-start: {{ $mes['x'] }}%">{{ $mes['texto'] }}</span>
                @endforeach
            </div>
        </div>

        <div style="position: relative">
            @forelse ($filas as $f)
                <button type="button" class="tq-crono-fila" @if ($f['coincide'] ?? false) data-coincide @if ($loop->first && $pagina->currentPage() === 1) data-primera @endif @endif wire:click="abrirFicha({{ $f['id'] }})" wire:key="crono-{{ $f['id'] }}" data-oportunidad="{{ $f['id'] }}" data-ficha-origen="{{ $f['id'] }}" aria-label="{{ $f['nombre'] }} · {{ $f['detalle'] }} · {{ $f['hito']['titulo'] }}">
                    <span class="tq-crono-quien"><b>{{ $f['nombre'] }}</b><small>{{ $f['detalle'] }} · {{ $f['entidad'] }}</small></span>
                    <span class="tq-crono-pista">
                        @foreach ($f['tramos'] as $t)
                            <span class="tq-crono-tramo" data-etapa="{{ $t['etapa'] }}" title="{{ $t['titulo'] }}" style="inset-inline-start: {{ $t['x'] }}%; width: {{ $t['ancho'] }}%; background: var(--tq-e-{{ $t['etapa'] }})"></span>
                        @endforeach
                        @if ($f['falta'] !== null)
                            <span class="tq-crono-falta" data-falta style="inset-inline-start: {{ $f['falta']['x'] }}%; width: {{ $f['falta']['ancho'] }}%"></span>
                        @endif
                        <svg class="tq-crono-hito" data-hito="{{ $f['hito']['tipo'] }}" style="inset-inline-start: {{ $f['hito']['x'] }}%" viewBox="0 0 14 14" aria-hidden="true"><title>{{ $f['hito']['titulo'] }}</title>
                            @if ($f['hito']['tipo'] === 'esperado')
                                <rect x="3" y="3" width="8" height="8" transform="rotate(45 7 7)" fill="white" stroke="var(--morado-700)" stroke-width="2"/>
                            @else
                                <circle cx="7" cy="7" r="6" fill="{{ $f['hito']['tipo'] === 'ganada' ? 'var(--success-600)' : 'var(--danger-600)' }}"/>
                                <text x="7" y="10.5" text-anchor="middle" font-size="9" fill="white">{{ $f['hito']['tipo'] === 'ganada' ? '✓' : '✕' }}</text>
                            @endif
                        </svg>
                    </span>
                </button>
            @empty
                <p class="text-sm text-gray-500" style="padding: 1rem 0">No hay oportunidades en este rango.</p>
            @endforelse
            <div style="position: absolute; top: 0; bottom: 0; inset-inline-start: 16rem; inset-inline-end: 0; pointer-events: none">
                <div class="tq-crono-hoy" style="inset-inline-start: {{ $hoy }}%" data-hoy></div>
            </div>
        </div>
    </div>

    @if ($pagina->hasPages())
        <div style="margin-top: 0.75rem"><x-filament::pagination :paginator="$pagina" /></div>
    @endif

    <div class="tq-crono-ley">
        @foreach ($etapas as $e)
            <span><span class="tq-crono-punto" style="background: var(--tq-e-{{ $e['clave'] }})"></span>{{ $e['nombre'] }}</span>
        @endforeach
        <span><span class="tq-crono-punto" style="background: repeating-linear-gradient(90deg, var(--morado-200) 0 3px, transparent 3px 5px)"></span>Lo que falta hasta el cierre esperado</span>
        <span><span class="tq-crono-punto" style="background: var(--success-600)"></span>Ganada</span>
        <span><span class="tq-crono-punto" style="background: var(--danger-600)"></span>Perdida</span>
        <span style="color: var(--danger-600)">│ Hoy</span>
    </div>
</div>
