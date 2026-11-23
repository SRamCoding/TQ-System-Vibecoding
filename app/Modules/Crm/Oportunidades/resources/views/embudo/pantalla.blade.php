{{--
    MV-058: el Embudo de oportunidades, como el tablero «A refinada · el embudo con sus gemas» del lienzo
    (`docs/vibe/pedidos/lienzos/embudo/Refinada.dc.html`). MV-061: sin fila propia de cifras (las cinco de la cabecera de las vistas bastan). A la izquierda, el embudo clásico de
    cuatro franjas que se angostan, con su gema, nombre, talla y probabilidad, monto y cantidad, y entre franjas «N %
    avanza»; debajo, Ganadas y Perdidas; a la derecha, el panel de la etapa elegida con su lista. Las franjas son botones;
    cada oportunidad abre su ficha deslizante. Recibe lo que arma `TableroOportunidades::datosEmbudo()`.
--}}
<div class="tq-embudo">
    <style>
        .tq-embudo { --tq-e-sup: var(--tq-superficie, #fff); --tq-e-borde: var(--tq-borde, var(--gray-200)); --tq-e-suave: var(--tq-texto-suave, var(--gray-500)); --tq-e-acento: #0790A8; --tq-e-coral: #C2532F;
            display: flex; flex-direction: column; gap: 22px; margin-top: 0.5rem; }
        .dark .tq-embudo { --tq-e-sup: var(--tq-superficie, #33302B); --tq-e-borde: var(--tq-borde, #45413A); --tq-e-suave: var(--tq-texto-suave, #A8ADB4); --tq-e-acento: #7FE9FF; --tq-e-coral: #F2A58C; }
        .tq-embudo-cuerpo { display: grid; grid-template-columns: minmax(0, 11fr) minmax(0, 9fr); gap: 36px; }
        @media (max-width: 1100px) { .tq-embudo-cuerpo { grid-template-columns: minmax(0, 1fr); } }
        .tq-embudo-izq { display: flex; flex-direction: column; align-items: center; }
        .tq-embudo-etapa { display: flex; flex-direction: column; align-items: center; width: 100%; }
        .tq-embudo-boton { padding: 0; border: 0; background: transparent; cursor: pointer; transition: filter .2s ease, transform .2s ease; filter: saturate(.9); }
        .tq-embudo-boton[aria-pressed="true"] { filter: drop-shadow(0 6px 18px rgba(0, 218, 255, .35)); transform: scale(1.015); }
        .tq-embudo-boton:focus-visible { outline: 2px solid var(--primary-600); outline-offset: 4px; border-radius: 8px; }
        .tq-embudo-franja { display: flex; align-items: center; gap: 14px; height: 82px; padding: 0 9% 0 8%; box-sizing: border-box; clip-path: polygon(0 0, 100% 0, 95% 100%, 5% 100%); border-radius: 6px; box-shadow: inset 0 1px 0 rgba(255, 255, 255, .45); text-align: left; }
        .tq-embudo-boton[aria-pressed="true"] .tq-embudo-franja { box-shadow: inset 0 0 0 3px rgba(255, 255, 255, .75); }
        .tq-embudo-nombre { font-size: 16px; font-weight: 700; color: #06262D; }
        .tq-embudo-sub { font-size: 12px; font-weight: 600; color: #0E4651; }
        .tq-embudo-monto { font-size: 18px; font-weight: 800; color: #06262D; font-variant-numeric: tabular-nums; }
        .tq-embudo-paso { margin: 7px 0; display: inline-flex; align-items: center; gap: 6px; padding: 3px 10px; border-radius: 999px; background: var(--tq-e-sup); border: 1px solid var(--tq-e-borde); font-size: 12px; font-weight: 600; color: var(--tq-e-suave); }
        .tq-embudo-salidas { margin-top: 14px; display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; width: 62%; }
        @media (max-width: 700px) { .tq-embudo-salidas { width: 100%; } }
        .tq-embudo-salida { display: flex; align-items: center; gap: 12px; padding: 12px 16px; border-radius: 14px; cursor: pointer; text-align: left; border: 1px solid; }
        .tq-embudo-salida[aria-pressed="true"] { border-width: 2px; }
        .tq-embudo-salida[data-salida="ganadas"] { background: rgba(125, 181, 130, .12); border-color: #7DB582; }
        .tq-embudo-salida[data-salida="perdidas"] { background: rgba(255, 138, 101, .10); border-color: #C9715A; }
        .tq-embudo-panel { display: flex; flex-direction: column; border-radius: 16px; background: var(--tq-e-sup); border: 1px solid var(--tq-e-borde); overflow: hidden; animation: tq-embudo-panel .28s ease both; }
        @keyframes tq-embudo-panel { from { opacity: 0; transform: translateX(8px); } to { opacity: 1; transform: none; } }
        @media (prefers-reduced-motion: reduce) { .tq-embudo-panel { animation: none; } .tq-embudo-boton { transition: none; } }
        html.tq-sin-animaciones .tq-embudo-panel { animation: none; }
        .tq-embudo-panel-cabeza { display: flex; align-items: center; gap: 14px; padding: 18px 20px; border-bottom: 1px solid var(--tq-e-borde); }
        .tq-embudo-fila { display: grid; grid-template-columns: 22px minmax(0, 1fr) auto; gap: 12px; align-items: center; width: 100%; padding: 13px 20px; border-bottom: 1px solid var(--tq-e-borde); text-align: left; }
        .tq-embudo-fila:hover, .tq-embudo-fila:focus-visible { background: color-mix(in oklab, var(--primary-600) 6%, transparent); }
        .tq-embudo-corta { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .tq-embudo-ver { margin-top: auto; padding: 14px 20px; font-size: 13px; font-weight: 600; color: var(--primary-600); }
        .dark .tq-embudo-ver { color: var(--primary-400); }
    </style>

    <div class="tq-embudo-cuerpo">
        <div class="tq-embudo-izq">
            {{-- Observación 7.1: la boca del embudo, «Por calificar», con la gema en la roca; lleva a esas tarjetas. --}}
            <div class="tq-embudo-etapa">
                <button type="button" wire:click="elegirEtapa('por_calificar')" class="tq-embudo-boton" style="width: 100%" data-etapa="por_calificar" aria-pressed="{{ $elegida['lugar'] === 'por_calificar' ? 'true' : 'false' }}"
                    aria-label="Por calificar, {{ $porCalificar['cantidad'] }}: {{ $porCalificar['leads'] }} leads y {{ $porCalificar['licitaciones'] }} licitaciones por decidir">
                    <span class="tq-embudo-franja" style="height: 66px; background: linear-gradient(180deg, #DCD7D1, #BEB7AF)">
                        <x-oportunidades::gema talla="en_roca" :tamano="38" decorativa />
                        <span style="display: flex; flex-direction: column; align-items: flex-start; gap: 1px; flex: 1; min-width: 0">
                            <span class="tq-embudo-nombre" style="color: #2E2A26">Por calificar</span>
                            <span class="tq-embudo-sub" style="color: #4F4943">Gema en la roca · {{ $porCalificar['leads'] }} leads · {{ $porCalificar['licitaciones'] }} licitaciones</span>
                        </span>
                        <span style="display: flex; flex-direction: column; align-items: flex-end; gap: 1px">
                            <span class="tq-embudo-monto" style="color: #2E2A26">{{ $porCalificar['cantidad'] }}</span>
                            <span class="tq-embudo-sub" style="color: #4F4943">ref. {{ $porCalificar['referencial'] }}</span>
                        </span>
                    </span>
                </button>
                <span class="tq-embudo-paso">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="var(--tq-e-acento)" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 5v14M6 13l6 6 6-6"/></svg>
                    Calificar · Participar
                </span>
            </div>
            @foreach ($etapas as $e)
                <div class="tq-embudo-etapa">
                    <button type="button" class="tq-embudo-boton" style="width: {{ $e['ancho'] }}%" wire:click="elegirEtapa('{{ $e['lugar'] }}')"
                        aria-pressed="{{ $elegida['lugar'] === $e['lugar'] ? 'true' : 'false' }}"
                        aria-label="{{ $e['nombre'] }}, {{ $e['cantidad'] }} oportunidades, {{ $e['monto'] }}">
                        <span class="tq-embudo-franja" style="background: linear-gradient(180deg, {{ $e['color1'] }}, {{ $e['color2'] }})">
                            <x-oportunidades::gema :talla="$e['talla']" :tamano="40" decorativa />
                            <span style="display: flex; flex-direction: column; align-items: flex-start; gap: 1px; flex: 1; min-width: 0">
                                <span class="tq-embudo-nombre">{{ $e['nombre'] }}</span>
                                <span class="tq-embudo-sub">Gema {{ \Illuminate\Support\Str::lower($e['talla']->etiqueta()) }} · {{ $e['prob'] }}</span>
                            </span>
                            <span style="display: flex; flex-direction: column; align-items: flex-end; gap: 1px">
                                <span class="tq-embudo-monto">{{ $e['monto'] }}</span>
                                <span class="tq-embudo-sub">{{ $e['cantidad'] }} oportunidades</span>
                            </span>
                        </span>
                    </button>
                    @if ($e['avanza'] !== null)
                        <span class="tq-embudo-paso">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="var(--tq-e-acento)" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 5v14M6 13l6 6 6-6"/></svg>
                            {{ $e['avanza'] }} avanza
                        </span>
                    @endif
                </div>
            @endforeach

            <div class="tq-embudo-salidas">
                <button type="button" class="tq-embudo-salida" data-salida="ganadas" wire:click="elegirEtapa('ganadas')" aria-pressed="{{ $elegida['lugar'] === 'ganadas' ? 'true' : 'false' }}">
                    <x-oportunidades::gema talla="brillante" :tamano="36" decorativa />
                    <span style="display: flex; flex-direction: column"><span style="font-size: 14px; font-weight: 700; color: #4E8F55">Ganadas</span><span style="font-size: 12px; color: var(--tq-e-suave)">{{ $ganadas['cantidad'] }} · {{ $ganadas['monto'] }}</span></span>
                </button>
                <button type="button" class="tq-embudo-salida" data-salida="perdidas" wire:click="elegirEtapa('perdidas')" aria-pressed="{{ $elegida['lugar'] === 'perdidas' ? 'true' : 'false' }}">
                    <x-oportunidades::gema talla="fisurada" :tamano="36" decorativa />
                    <span style="display: flex; flex-direction: column"><span style="font-size: 14px; font-weight: 700; color: var(--tq-e-coral)">Perdidas</span><span style="font-size: 12px; color: var(--tq-e-suave)">{{ $perdidas['cantidad'] }} · {{ $perdidas['monto'] }}</span></span>
                </button>
            </div>
        </div>

        <div class="tq-embudo-panel" wire:key="panel-{{ $elegida['lugar'] }}">
            <div class="tq-embudo-panel-cabeza">
                <x-oportunidades::gema :talla="$elegida['talla']" :tamano="48" decorativa />
                <div style="display: flex; flex-direction: column; gap: 2px; flex: 1">
                    <span style="font-size: 18px; font-weight: 700">{{ $elegida['nombre'] }}</span>
                    <span style="font-size: 13px; color: var(--tq-e-suave)">{{ $elegida['resumen'] }}</span>
                </div>
            </div>
            {{-- Observación 8.2: el panel de «Por calificar», con sus botones; al resolver uno, sale con la animación suave. --}}
            @if ($elegida['porCalificar'] !== null)
                <div style="display: flex; flex-direction: column" data-tq-escalonar>
                    @forelse ($elegida['porCalificar'] as $p)
                        <div class="tq-embudo-fila" style="grid-template-columns: 22px minmax(0, 1fr) auto; cursor: default" wire:key="pc-{{ $p['clave'] }}" data-tq-fila="pc-{{ $p['clave'] }}">
                            <x-oportunidades::gema talla="en_roca" :tamano="22" decorativa />
                            <span style="display: flex; flex-direction: column; gap: 2px; min-width: 0">
                                <span class="tq-embudo-corta" style="font-size: 14px; font-weight: 600">{{ $p['nombre'] }}</span>
                                <span class="tq-embudo-corta" style="font-size: 12px; color: var(--tq-e-suave)">{{ $p['entidad'] }} · {{ $p['origenEtiqueta'] }}</span>
                                <span style="display: flex; gap: 6px; margin-top: 6px; flex-wrap: wrap">
                                    @if ($p['clase'] === 'lead')
                                        <x-filament::button size="xs" color="success" wire:click="decidir('calificarLead', {{ $p['id'] }})">Calificar</x-filament::button>
                                        <x-filament::button size="xs" color="gray" wire:click="decidir('descartarLead', {{ $p['id'] }})">Descartar</x-filament::button>
                                    @else
                                        <x-filament::button size="xs" color="success" wire:click="decidir('participar', {{ $p['id'] }})">Participar</x-filament::button>
                                        <x-filament::button size="xs" color="gray" wire:click="decidir('noParticipar', {{ $p['id'] }})">No participar</x-filament::button>
                                    @endif
                                </span>
                            </span>
                            <span style="display: flex; flex-direction: column; align-items: flex-end; gap: 2px">
                                <span style="font-size: 14px; font-weight: 700; font-variant-numeric: tabular-nums">{{ $p['montoTexto'] ?? '' }}</span>
                                @if ($p['plazo'])<span style="font-size: 12px; font-weight: 600; color: {{ $p['plazo']['urgente'] ? 'var(--tq-e-coral)' : 'var(--tq-e-suave)' }}">{{ $p['plazo']['texto'] }}</span>@endif
                                <span style="font-size: 12px; color: {{ $p['esperaUrgente'] ? 'var(--tq-e-coral)' : 'var(--tq-e-suave)' }}">{{ $p['espera'] }}</span>
                            </span>
                        </div>
                    @empty
                        <p style="padding: 18px 20px; font-size: 14px; color: var(--tq-e-suave)">Nada por calificar.</p>
                    @endforelse
                </div>
            @else
            <div style="display: flex; flex-direction: column">
                @forelse ($elegida['filas'] as $f)
                    <button type="button" class="tq-embudo-fila" wire:click="abrirFicha({{ $f['id'] }})" data-tq-fila="embudo-{{ $f['id'] }}">
                        <x-oportunidades::gema :talla="$elegida['talla']" :tamano="22" decorativa />
                        <span style="display: flex; flex-direction: column; gap: 2px; min-width: 0">
                            <span class="tq-embudo-corta" style="font-size: 14px; font-weight: 600">{{ $f['nombre'] }}</span>
                            <span class="tq-embudo-corta" style="font-size: 12px; color: var(--tq-e-suave)">{{ $f['cliente'] }}</span>
                            @if ($f['accion'] !== '')
                                <span style="font-size: 12px; font-weight: 600; margin-top: 2px; color: {{ $f['vencida'] ? 'var(--tq-e-coral)' : 'var(--tq-e-acento)' }}">{{ $f['accion'] }}</span>
                            @endif
                        </span>
                        <span style="display: flex; flex-direction: column; align-items: flex-end; gap: 2px">
                            <span style="font-size: 14px; font-weight: 700; font-variant-numeric: tabular-nums">{{ $f['monto'] }}</span>
                            <span style="font-size: 12px; color: var(--tq-e-suave)">{{ $f['dias'] }}</span>
                        </span>
                    </button>
                @empty
                    <p style="padding: 18px 20px; font-size: 14px; color: var(--tq-e-suave)">Ninguna oportunidad en esta etapa.</p>
                @endforelse
            </div>
            @endif
            <a href="{{ $urlMias }}" wire:navigate class="tq-embudo-ver">Ver todas en Mis oportunidades →</a>
        </div>
    </div>
</div>
