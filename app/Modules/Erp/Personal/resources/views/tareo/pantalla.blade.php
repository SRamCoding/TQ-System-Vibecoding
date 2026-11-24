{{--
    Observaciones 6.9 y 6.10 (MV-069, MV-070): el tareo de un proyecto y semana. Arriba, la tira de semanas del mes con
    flechas y el proyecto como chip; cuatro cifras; el selector Semana / Hoy y, en Semana, Por persona / Por partida. La
    cuadrícula se escribe directo (Tab y Enter avanzan, Esc deshace la celda, se guarda al salir); las líneas propuestas
    se ven suaves con «de la jornada». En Hoy, una tarjeta por persona con Vino / Faltó.
--}}
@php
    $d = $this->datos();
    $soles = fn ($m): string => 'S/ '.number_format((float) $m, 2);
    $DIAS = \App\Modules\Erp\Personal\Services\TareoService::DIAS;
@endphp
<x-filament-panels::page>
    <div class="tq-tr" x-data x-init="if (@js($this->vista === null) && window.innerWidth < 640) $wire.poner('vista', 'hoy')">
        <style>
            .tq-tr { --tq-tr-sup: var(--tq-superficie, #fff); --tq-tr-elev: var(--tq-superficie-elevada, #fff); --tq-tr-borde: var(--tq-borde, var(--gray-200)); --tq-tr-suave: var(--tq-texto-suave, var(--gray-500));
                --tq-tr-acento: var(--tq-franja-misionales, #0790A8); --tq-tr-coral: color-mix(in oklch, #C2532F calc(100% - var(--tq-tinte-leve, 0%)), var(--tq-tinte, #C2532F)); --tq-tr-ambar: color-mix(in oklch, #B7791F calc(100% - var(--tq-tinte-leve, 0%)), var(--tq-tinte, #B7791F)); display: flex; flex-direction: column; gap: 1rem; }
            .dark .tq-tr { --tq-tr-sup: var(--tq-superficie, #33302B); --tq-tr-elev: var(--tq-superficie-elevada, #3A3731); --tq-tr-borde: var(--tq-borde, #45413A); --tq-tr-suave: var(--tq-texto-suave, #A8ADB4);
                --tq-tr-acento: var(--tq-franja-misionales, #7FE9FF); --tq-tr-coral: color-mix(in oklch, #F2A58C calc(100% - var(--tq-tinte-leve, 0%)), var(--tq-tinte, #F2A58C)); --tq-tr-ambar: color-mix(in oklch, #F6C66B calc(100% - var(--tq-tinte-leve, 0%)), var(--tq-tinte, #F6C66B)); }
            .tq-tr-suave { color: var(--tq-tr-suave); }
            .tq-tr-coral { color: var(--tq-tr-coral) !important; }
            .tq-tr-barra-sup { display: flex; flex-wrap: wrap; align-items: center; gap: 0.5rem 0.75rem; }
            .tq-tr-tira { display: flex; flex-wrap: wrap; align-items: center; gap: 0.4rem; }
            .tq-tr-flecha { display: inline-flex; align-items: center; justify-content: center; width: 2rem; height: 2rem; border-radius: 9999px; border: 1px solid var(--tq-tr-borde); background: var(--tq-tr-sup); }
            .tq-tr-semana { display: inline-flex; align-items: center; gap: 0.3rem; padding: 0.3rem 0.75rem; border-radius: 9999px; font-size: 0.8rem; font-weight: 600; border: 1px solid var(--tq-tr-borde); background: var(--tq-tr-sup); color: var(--tq-tr-suave); }
            .tq-tr-semana[data-estado="cerrada"] { color: var(--success-600); }
            .tq-tr-semana[data-estado="atrasada"] { color: var(--tq-tr-coral); border-color: var(--tq-tr-coral); }
            .tq-tr-semana[aria-current="true"] { background: var(--tq-tr-acento); border-color: var(--tq-tr-acento); color: #fff; }
            .dark .tq-tr-semana[aria-current="true"] { color: #06262D; }
            .tq-tr-chip { margin-left: auto; display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.35rem 0.85rem; border-radius: 9999px; font-size: 0.85rem; font-weight: 600; border: 1px solid var(--tq-tr-acento); color: var(--tq-tr-acento); background: color-mix(in oklab, var(--tq-tr-acento) 8%, transparent); max-width: 100%; }
            .tq-tr-chip span { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
            .tq-tr-cifras { display: grid; grid-template-columns: repeat(auto-fit, minmax(12rem, 1fr)); gap: 0.75rem; }
            .tq-tr-cifra { padding: 0.75rem 1rem; border-radius: 0.75rem; background: var(--tq-tr-sup); border: 1px solid var(--tq-tr-borde); display: flex; flex-direction: column; gap: 0.25rem; }
            .tq-tr-cifra-titulo { font-size: 0.8rem; font-weight: 600; color: var(--tq-tr-suave); }
            .tq-tr-cifra-valor { font-size: 1.375rem; font-weight: 600; line-height: 1.875rem; font-variant-numeric: tabular-nums; }
            .tq-tr-barra { position: relative; height: 0.45rem; border-radius: 9999px; background: color-mix(in oklab, var(--tq-tr-suave) 22%, transparent); overflow: hidden; }
            .tq-tr-lleno { position: absolute; inset: 0 auto 0 0; border-radius: 9999px; background: var(--tq-tr-acento); transition: width .3s ease; }
            .tq-tr-lleno[data-pasa] { background: var(--tq-tr-coral); }
            .tq-tr-selectores { display: flex; flex-wrap: wrap; justify-content: space-between; gap: 0.5rem; }
            .tq-tr-seg { display: inline-flex; border: 1px solid var(--tq-tr-borde); border-radius: 9999px; overflow: hidden; background: var(--tq-tr-sup); }
            .tq-tr-seg button { padding: 0.3rem 0.9rem; font-size: 0.8rem; font-weight: 600; color: var(--tq-tr-suave); }
            .tq-tr-seg button[aria-pressed="true"] { background: var(--primary-600); color: #fff; }
            .dark .tq-tr-seg button[aria-pressed="true"] { background: var(--primary-400); color: var(--gray-950); }
            .tq-tr-caja { border-radius: 0.9rem; background: var(--tq-tr-elev); border: 1px solid var(--tq-tr-borde); overflow-x: auto; }
            .tq-tr-tabla { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
            .tq-tr-tabla th { padding: 0.55rem 0.5rem; font-size: 0.75rem; font-weight: 600; color: var(--tq-tr-suave); text-align: left; border-bottom: 1px solid var(--tq-tr-borde); white-space: nowrap; }
            .tq-tr-tabla td { padding: 0.35rem 0.5rem; border-bottom: 1px solid var(--tq-tr-borde); vertical-align: middle; }
            .tq-tr-tabla .num { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
            .tq-tr-tabla th.dia, .tq-tr-tabla td.dia { width: 4.2rem; text-align: center; }
            .tq-tr-tabla .feriado { background: repeating-linear-gradient(135deg, color-mix(in oklab, var(--tq-tr-suave) 12%, transparent) 0 6px, transparent 6px 12px); }
            .tq-tr-fila[data-jornada] td { opacity: .62; }
            .tq-tr-fila[data-jornada] td:hover { opacity: 1; }
            .tq-tr-fila[data-sctr] { background: color-mix(in oklab, var(--tq-tr-coral) 9%, transparent); }
            .tq-tr-persona { display: flex; align-items: center; gap: 0.6rem; min-width: 12rem; }
            .tq-tr-nombre { font-weight: 600; line-height: 1.2; }
            .tq-tr-etiqueta { display: inline-block; font-size: 0.68rem; font-weight: 600; padding: 0 0.45rem; border-radius: 9999px; border: 1px solid var(--tq-tr-borde); color: var(--tq-tr-suave); white-space: nowrap; }
            .tq-tr-etiqueta[data-tono="coral"] { border-color: var(--tq-tr-coral); color: var(--tq-tr-coral); }
            .tq-tr-etiqueta[data-tono="acento"] { border-color: var(--tq-tr-acento); color: var(--tq-tr-acento); }
            .tq-tr-celda { width: 3.6rem; padding: 0.3rem 0.35rem; border-radius: 0.4rem; border: 1px solid transparent; background: transparent; text-align: center; font-variant-numeric: tabular-nums; }
            .tq-tr-celda:hover:not([readonly]) { border-color: var(--tq-tr-borde); }
            .tq-tr-celda:focus { outline: none; border-color: var(--primary-600); background: var(--tq-tr-sup); }
            .tq-tr-celda[data-extra] { border-color: var(--tq-tr-ambar); color: var(--tq-tr-ambar); font-weight: 700; }
            .tq-tr-marca { display: block; font-size: 0.62rem; font-weight: 700; line-height: 1; margin-top: 0.1rem; }
            .tq-tr-marca[data-tipo="falto"] { color: var(--tq-tr-coral); }
            .tq-tr-marca[data-tipo="quiza"] { color: var(--tq-tr-ambar); }
            .tq-tr-marca[data-tipo="extra"] { color: var(--tq-tr-ambar); }
            .tq-tr-partida { min-width: 13rem; max-width: 17rem; font-size: 0.8rem; }
            .tq-tr-icono { display: inline-flex; padding: 0.25rem; border-radius: 0.4rem; color: var(--tq-tr-suave); }
            .tq-tr-icono:hover { background: color-mix(in oklab, var(--tq-tr-suave) 14%, transparent); }
            .tq-tr-tabla tfoot td { font-weight: 700; border-bottom: 0; }
            .tq-tr-hoy { display: grid; grid-template-columns: repeat(auto-fill, minmax(17rem, 1fr)); gap: 0.75rem; }
            .tq-tr-tarjeta { display: flex; flex-direction: column; gap: 0.75rem; padding: 0.9rem 1rem; border-radius: 0.9rem; background: var(--tq-tr-elev); border: 1px solid var(--tq-tr-borde); }
            .tq-tr-tarjeta[data-sctr] { border-color: var(--tq-tr-coral); }
            .tq-tr-botones { display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem; }
            .tq-tr-boton { padding: 0.75rem; border-radius: 0.75rem; font-weight: 700; font-size: 1rem; border: 2px solid var(--tq-tr-borde); }
            .tq-tr-boton[data-que="vino"][aria-pressed="true"] { background: var(--success-600); border-color: var(--success-600); color: #fff; }
            .tq-tr-boton[data-que="falto"][aria-pressed="true"] { background: var(--tq-tr-coral); border-color: var(--tq-tr-coral); color: #fff; }
            .tq-tr-boton:disabled { opacity: .45; cursor: not-allowed; }
            .tq-tr-dias { display: flex; flex-wrap: wrap; gap: 0.35rem; }
            @media (prefers-reduced-motion: reduce) { .tq-tr-lleno { transition: none; } }
        </style>

        @if ($d['vacio'])
            <div class="tq-tr-caja" style="padding: 2rem; text-align: center">
                <x-filament::button wire:click="mountAction('proyecto')" icon="heroicon-o-briefcase">Elegir proyecto</x-filament::button>
            </div>
        @else
            <div class="tq-tr-barra-sup">
                <div class="tq-tr-tira" role="group" aria-label="Semanas del mes">
                    <button type="button" class="tq-tr-flecha" wire:click="moverSemana(-1)" aria-label="Semana anterior"><x-filament::icon icon="heroicon-m-chevron-left" class="h-4 w-4" /></button>
                    @foreach ($d['tira'] as $s)
                        <button type="button" class="tq-tr-semana" data-estado="{{ $s['estado'] }}" aria-current="{{ $s['actual'] ? 'true' : 'false' }}" wire:click="irSemana('{{ $s['lunes'] }}')">
                            @if ($s['estado'] === 'cerrada')<x-filament::icon icon="heroicon-m-check" class="h-3.5 w-3.5" />@endif
                            {{ $s['etiqueta'] }}
                        </button>
                    @endforeach
                    <button type="button" class="tq-tr-flecha" wire:click="moverSemana(1)" aria-label="Semana siguiente"><x-filament::icon icon="heroicon-m-chevron-right" class="h-4 w-4" /></button>
                </div>
                <button type="button" class="tq-tr-chip" wire:click="mountAction('proyecto')" title="{{ $d['proyectoNombre'] }}">
                    <x-filament::icon icon="heroicon-m-briefcase" class="h-4 w-4 shrink-0" />
                    <span>{{ $d['proyectoCodigo'] }} · {{ $d['proyectoNombre'] }}</span>
                    <x-filament::icon icon="heroicon-m-chevron-down" class="h-4 w-4 shrink-0" />
                </button>
            </div>

            <section class="tq-tr-cifras" aria-label="Cifras de la semana">
                <div class="tq-tr-cifra"><span class="tq-tr-cifra-titulo">Horas de la semana</span><span class="tq-tr-cifra-valor">{{ $d['horas'] }} h</span></div>
                <div class="tq-tr-cifra"><span class="tq-tr-cifra-titulo">Costo de la semana</span><span class="tq-tr-cifra-valor">{{ $soles($d['costo']) }}</span></div>
                <div class="tq-tr-cifra">
                    <span class="tq-tr-cifra-titulo">Mano de obra gastada / presupuestada</span>
                    <span @class(['tq-tr-cifra-valor', 'tq-tr-coral' => $d['manoDeObra']['porcentaje'] > 100])>{{ $d['manoDeObra']['porcentaje'] }} %</span>
                    <span class="tq-tr-barra"><span class="tq-tr-lleno" style="width: {{ min(100, $d['manoDeObra']['porcentaje']) }}%" @if ($d['manoDeObra']['porcentaje'] > 100) data-pasa @endif></span></span>
                    <span class="tq-tr-suave" style="font-size: 0.75rem">{{ $soles($d['manoDeObra']['gastado']) }} de {{ $soles($d['manoDeObra']['presupuestado']) }}</span>
                </div>
                <div class="tq-tr-cifra"><span class="tq-tr-cifra-titulo">Personas tareadas</span><span @class(['tq-tr-cifra-valor', 'tq-tr-coral' => $d['tareadas'] < $d['asignadas']])>{{ $d['tareadas'] }} de {{ $d['asignadas'] }}</span></div>
            </section>

            <div class="tq-tr-selectores">
                <div class="tq-tr-seg" role="group" aria-label="Vista">
                    <button type="button" aria-pressed="{{ $this->vista !== 'hoy' ? 'true' : 'false' }}" wire:click="poner('vista', 'semana')">Semana</button>
                    <button type="button" aria-pressed="{{ $this->vista === 'hoy' ? 'true' : 'false' }}" wire:click="poner('vista', 'hoy')">Hoy</button>
                </div>
                @if ($this->vista !== 'hoy')
                    <div class="tq-tr-seg" role="group" aria-label="Agrupar">
                        <button type="button" aria-pressed="{{ $this->agrupar === 'persona' ? 'true' : 'false' }}" wire:click="poner('agrupar', 'persona')">Por persona</button>
                        <button type="button" aria-pressed="{{ $this->agrupar === 'partida' ? 'true' : 'false' }}" wire:click="poner('agrupar', 'partida')">Por partida</button>
                    </div>
                @else
                    <div class="tq-tr-dias" role="group" aria-label="Día">
                        @foreach ($d['dias'] as $i => $dd)
                            <button type="button" class="tq-tr-semana" aria-current="{{ $d['dia'] === $dd ? 'true' : 'false' }}" wire:click="poner('dia', '{{ $dd }}')">{{ $DIAS[$dd] }} {{ $d['lunes']->addDays($i)->format('d') }}</button>
                        @endforeach
                    </div>
                @endif
            </div>

            @if ($this->vista === 'hoy')
                @php
                    $porPersona = collect($d['filas'])->groupBy(fn ($f) => $f['persona']['id']);
                @endphp
                <div class="tq-tr-hoy">
                    @foreach ($porPersona as $id => $fs)
                        @php
                            $p = $fs->first()['persona'];
                            $h = $fs->reduce(fn ($s, $f) => bcadd($s, $f['celdas'][$d['dia']]['horas'] ?? '0', 1), '0');
                            $falto = $fs->contains(fn ($f) => $f['celdas'][$d['dia']]['falto'] ?? false);
                        @endphp
                        <article class="tq-tr-tarjeta" data-tq-fila="hoy-{{ $id }}" wire:key="hoy-{{ $id }}" @if ($p['sctr_mal']) data-sctr @endif>
                            <div class="tq-tr-persona">
                                @include('personal::avatar', ['a' => $p['avatar'], 'tam' => 44])
                                <div><div class="tq-tr-nombre">{{ $p['nombres'] }}</div><div class="tq-tr-suave" style="font-size: 0.8rem">{{ $p['categoria'] }} · {{ \App\Modules\Erp\Personal\Services\TareoService::limpio($h) }} h</div></div>
                            </div>
                            <div class="tq-tr-botones">
                                <button type="button" class="tq-tr-boton" data-que="vino" aria-pressed="{{ bccomp($h, '0', 1) > 0 ? 'true' : 'false' }}" wire:click="asistencia({{ $id }}, true)" @disabled(! $d['puede'] || $p['sctr_mal'] || isset($d['feriados'][$d['dia']]))>Vino</button>
                                <button type="button" class="tq-tr-boton" data-que="falto" aria-pressed="{{ $falto ? 'true' : 'false' }}" wire:click="asistencia({{ $id }}, false)" @disabled(! $d['puede'] || $p['sctr_mal'] || isset($d['feriados'][$d['dia']]))>Faltó</button>
                            </div>
                        </article>
                    @endforeach
                    @foreach ($d['faltantes'] as $p)
                        <article class="tq-tr-tarjeta" data-sctr wire:key="hoy-f-{{ $p['id'] }}">
                            <div class="tq-tr-persona">
                                @include('personal::avatar', ['a' => $p['avatar'], 'tam' => 44])
                                <div><div class="tq-tr-nombre">{{ $p['nombres'] }}</div><span class="tq-tr-etiqueta" data-tono="coral">SCTR vencido</span></div>
                            </div>
                        </article>
                    @endforeach
                </div>
            @elseif ($this->agrupar === 'partida')
                <div class="tq-tr-caja">
                    <table class="tq-tr-tabla">
                        <thead><tr><th>Partida</th><th class="num">Personas</th><th class="num">Horas</th><th class="num">Costo de la semana</th><th style="min-width: 14rem">Mano de obra gastada / presupuestada</th></tr></thead>
                        <tbody>
                            @foreach ($d['porPartida'] as $p)
                                <tr data-tq-fila="partida-{{ $p['id'] }}" wire:key="partida-{{ $p['id'] }}">
                                    <td><span style="font-family: ui-monospace, monospace">{{ $p['codigo'] }}</span> · {{ $p['nombre'] }}</td>
                                    <td class="num">{{ $p['personas'] }}</td>
                                    <td class="num">{{ $p['horas'] }}</td>
                                    <td class="num" style="font-family: ui-monospace, monospace">{{ $soles($p['costo']) }}</td>
                                    <td>
                                        <div style="display: flex; align-items: center; gap: 0.6rem">
                                            <span class="tq-tr-barra" style="flex: 1"><span class="tq-tr-lleno" style="width: {{ min(100, $p['porcentaje']) }}%" @if ($p['porcentaje'] > 100) data-pasa @endif></span></span>
                                            <span @class(['num', 'tq-tr-coral' => $p['porcentaje'] > 100]) style="font-weight: 600">{{ $p['porcentaje'] }} %</span>
                                        </div>
                                        <span class="tq-tr-suave" style="font-size: 0.72rem">{{ $soles($p['gastado']) }} de {{ $soles($p['presupuestado']) }}</span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="tq-tr-caja">
                    <table class="tq-tr-tabla" x-data="{
                            mover(e, filas) {
                                const celdas = [...this.$root.querySelectorAll('input.tq-tr-celda:not([readonly])')];
                                const i = celdas.indexOf(e.target);
                                const col = e.target.dataset.col;
                                const siguiente = celdas.slice(i + 1).find((c) => c.dataset.col === col);
                                e.target.blur();
                                siguiente?.focus(); siguiente?.select();
                            },
                        }">
                        <thead>
                            <tr>
                                <th>Persona</th>
                                <th>Partida</th>
                                @foreach ($d['dias'] as $i => $dd)
                                    <th @class(['dia', 'feriado' => isset($d['feriados'][$dd])]) @if (isset($d['feriados'][$dd])) title="{{ $d['feriados'][$dd] }}" @endif>{{ $DIAS[$dd] }} {{ $d['lunes']->addDays($i)->format('d') }}</th>
                                @endforeach
                                <th class="num">Horas</th>
                                <th class="num">Costo</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($d['filas'] as $f)
                                @php $p = $f['persona']; @endphp
                                <tr class="tq-tr-fila" data-tq-fila="linea-{{ $f['linea'] }}" wire:key="linea-{{ $f['linea'] }}" @if ($f['de_jornada']) data-jornada @endif @if ($p['sctr_mal']) data-sctr @endif>
                                    <td>
                                        @if ($f['primera'])
                                            <div class="tq-tr-persona">
                                                @include('personal::avatar', ['a' => $p['avatar'], 'tam' => 34])
                                                <div style="display: flex; flex-direction: column; gap: 0.1rem">
                                                    <span class="tq-tr-nombre">{{ $p['nombres'] }}</span>
                                                    <span class="tq-tr-suave" style="font-size: 0.75rem">{{ $p['categoria'] }}</span>
                                                    <span style="display: flex; flex-wrap: wrap; gap: 0.25rem">
                                                        <span class="tq-tr-etiqueta" @if ($p['pasa']) data-tono="coral" @endif
                                                            title="{{ collect($p['proyectos'])->map(fn ($x) => $x['codigo'].': '.$x['horas'].' h')->implode(' · ') ?: 'Sin horas esta semana' }}">{{ $p['semana_total'] }} de {{ $p['jornada_total'] }} h</span>
                                                        @if ($p['sctr_mal'])<span class="tq-tr-etiqueta" data-tono="coral">SCTR vencido</span>@endif
                                                    </span>
                                                </div>
                                            </div>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($f['editable'])
                                            <x-filament::input.wrapper @class(['tq-tr-partida']) :valid="$f['partida'] !== null">
                                                <x-filament::input.select wire:change="cambiarPartida({{ $f['linea'] }}, $event.target.value)" aria-label="Partida">
                                                    @if ($f['partida'] === null)<option value="">Elige la partida</option>@endif
                                                    @foreach ($d['partidas'] as $id => $texto)
                                                        <option value="{{ $id }}" @selected(($f['partida']['id'] ?? null) === (int) $id)>{{ $texto }}</option>
                                                    @endforeach
                                                </x-filament::input.select>
                                            </x-filament::input.wrapper>
                                        @else
                                            <span style="font-size: 0.8rem">{{ $f['partida'] ? $f['partida']['codigo'].' · '.$f['partida']['nombre'] : 'Sin partida' }}</span>
                                        @endif
                                        @if ($f['de_jornada'])<span class="tq-tr-etiqueta" data-tono="acento" style="margin-top: 0.2rem">de la jornada</span>@endif
                                    </td>
                                    @foreach ($d['dias'] as $dd)
                                        @php $c = $f['celdas'][$dd]; @endphp
                                        <td @class(['dia', 'feriado' => $c['feriado'] !== null]) @if ($c['feriado']) title="{{ $c['feriado'] }}" @endif>
                                            <input type="text" inputmode="decimal" class="tq-tr-celda" data-col="{{ $dd }}" value="{{ $c['horas'] === '0' ? '' : $c['horas'] }}"
                                                aria-label="{{ $p['nombres'] }}, {{ $DIAS[$dd] }}" @if ($c['extra']) data-extra title="Horas extra" @endif
                                                @if (! $f['editable']) readonly tabindex="-1" @endif
                                                @if ($f['editable'])
                                                    x-on:focus="$el.dataset.antes = $el.value; $el.select()"
                                                    x-on:keydown.escape.prevent="$el.value = $el.dataset.antes; $el.blur()"
                                                    x-on:keydown.enter.prevent="mover($event)"
                                                    x-on:change="$wire.celda({{ $f['linea'] }}, '{{ $dd }}', $el.value)"
                                                @endif>
                                            @if ($c['falto'])<span class="tq-tr-marca" data-tipo="falto">faltó</span>
                                            @elseif ($c['falta_quiza'])<span class="tq-tr-marca" data-tipo="quiza">¿faltó?</span>
                                            @elseif ($c['extra'])<span class="tq-tr-marca" data-tipo="extra">horas extra</span>
                                            @endif
                                        </td>
                                    @endforeach
                                    <td class="num" style="font-weight: 700">{{ $f['horas'] }}</td>
                                    <td class="num" style="font-family: ui-monospace, monospace">{{ number_format((float) $f['costo'], 2) }}</td>
                                    <td style="white-space: nowrap">
                                        @if ($f['editable'])
                                            <button type="button" class="tq-tr-icono" wire:click="llenarFila({{ $f['linea'] }})" title="Llenar la fila" aria-label="Llenar la fila"><x-filament::icon icon="heroicon-m-arrows-right-left" class="h-4 w-4" /></button>
                                            <button type="button" class="tq-tr-icono" wire:click="quitarFila({{ $f['linea'] }})" wire:confirm="¿Quitar esta línea?" title="Quitar la línea" aria-label="Quitar la línea"><x-filament::icon icon="heroicon-m-x-mark" class="h-4 w-4" /></button>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                            @foreach ($d['faltantes'] as $p)
                                <tr class="tq-tr-fila" data-sctr wire:key="falta-{{ $p['id'] }}">
                                    <td>
                                        <div class="tq-tr-persona">
                                            @include('personal::avatar', ['a' => $p['avatar'], 'tam' => 34])
                                            <div style="display: flex; flex-direction: column; gap: 0.1rem"><span class="tq-tr-nombre">{{ $p['nombres'] }}</span><span class="tq-tr-suave" style="font-size: 0.75rem">{{ $p['categoria'] }}</span></div>
                                        </div>
                                    </td>
                                    <td colspan="{{ count($d['dias']) + 4 }}"><span class="tq-tr-etiqueta" data-tono="coral">SCTR vencido</span> <span class="tq-tr-coral" style="font-size: 0.8rem">Asignado {{ $p['dedicacion'] }} %, sin tarear</span></td>
                                </tr>
                            @endforeach
                            @if ($d['puede'])
                                <tr x-data="{ persona: '', partida: '' }" wire:key="fila-nueva">
                                    <td>
                                        <x-filament::input.wrapper>
                                            <x-filament::input.select x-model="persona" aria-label="Persona">
                                                <option value="">Agregar persona…</option>
                                                @foreach ($d['personas'] as $id => $n)<option value="{{ $id }}">{{ $n }}</option>@endforeach
                                            </x-filament::input.select>
                                        </x-filament::input.wrapper>
                                    </td>
                                    <td>
                                        <x-filament::input.wrapper class="tq-tr-partida">
                                            <x-filament::input.select x-model="partida" aria-label="Partida">
                                                <option value="">Partida…</option>
                                                @foreach ($d['partidas'] as $id => $texto)<option value="{{ $id }}">{{ $texto }}</option>@endforeach
                                            </x-filament::input.select>
                                        </x-filament::input.wrapper>
                                    </td>
                                    <td colspan="{{ count($d['dias']) + 3 }}">
                                        <x-filament::button size="sm" color="gray" icon="heroicon-m-plus" x-on:click="$wire.agregarFila(persona, partida); persona = ''; partida = ''">Agregar fila</x-filament::button>
                                    </td>
                                </tr>
                            @endif
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="2">Total</td>
                                @foreach ($d['dias'] as $dd)<td class="num dia">{{ $d['totalesDia'][$dd] === '0' ? '' : $d['totalesDia'][$dd] }}</td>@endforeach
                                <td class="num">{{ $d['horas'] }}</td>
                                <td class="num" style="font-family: ui-monospace, monospace">{{ number_format((float) $d['costo'], 2) }}</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            @endif
        @endif
    </div>
</x-filament-panels::page>
