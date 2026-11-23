{{--
    MV-025, MV-026 y MV-087 (observación 8.8, lienzo «A · Mes con actividades», `docs/vibe/pedidos/lienzos/agenda-nueva/`):
    la agenda del muelle como ventana sólida centrada, con la pantalla de atrás desenfocada y un poco aclarada (sin
    oscurecer). Se abre con el evento `tq-agenda-abrir`; se cierra con «×», Esc o un clic fuera. Al abrirse muestra el
    año y enseguida, sola, se acerca al mes actual; «Año» se aleja. El mes: lunes a domingo, celdas grandes con las
    actividades como pastillas del color de su estado y «+N más». A la derecha, angosto, el día elegido (al abrir, hoy).
--}}
@php
    $meses = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
    $estados = ['vencida' => 'Vencida', 'por_vencer' => 'Por vencer', 'programada' => 'Programada', 'hecha' => 'Hecha'];
    $semanas = function (int $anio, int $mes): array {
        $primero = \Carbon\CarbonImmutable::create($anio, $mes, 1);
        $celdas = array_fill(0, $primero->dayOfWeekIso - 1, null);
        for ($d = 1; $d <= $primero->daysInMonth; $d++) {
            $celdas[] = $primero->setDay($d);
        }
        return array_chunk(array_pad($celdas, (int) ceil(count($celdas) / 7) * 7, null), 7);
    };
    $elegido = $dia === null ? null : \Carbon\CarbonImmutable::parse($dia)->locale('es');
    $maxPastillas = 3;
@endphp
<div
    x-data="{
        abierto: false,
        vista: 'anio',
        moviendo: false,
        origen: '50% 50%',
        init() {
            Alpine.store('tqAgenda') ?? Alpine.store('tqAgenda', { lista: false })
            Alpine.store('tqAgenda').lista = true
            // Observación 8.7: como toda ventana al medio, el asistente se esconde mientras está abierta.
            this.$watch('abierto', (v) => document.documentElement.classList.toggle('tq-ventana-abierta', v))
        },
        abrir() {
            this.abierto = true
            this.vista = 'anio'
            $wire.irAHoy()
            // Muestra el año un momento y se acerca sola al mes actual (MV-025).
            setTimeout(() => this.acercar({{ $hoy->month }}), 900)
        },
        acercar(mes) {
            const celda = this.$refs.anio?.querySelector(`[data-mes='${mes}']`)
            const caja = this.$refs.anio
            if (celda && caja) {
                const c = celda.getBoundingClientRect(), r = caja.getBoundingClientRect()
                this.origen = `${c.left - r.left + c.width / 2}px ${c.top - r.top + c.height / 2}px`
            }
            $wire.irAMes(mes)
            this.moviendo = true
            setTimeout(() => { this.vista = 'mes'; this.moviendo = false }, 550)
        },
        alejar() {
            this.vista = 'anio'
            this.moviendo = true
            requestAnimationFrame(() => requestAnimationFrame(() => { this.moviendo = false }))
        },
    }"
    x-on:tq-agenda-abrir.window="abrir()"
    x-on:keydown.escape.window="abierto = false"
    x-show="abierto"
    x-cloak
    class="tq-agenda-fondo"
    x-on:click.self="abierto = false"
>
    <style>
        .tq-agenda-fondo { position: fixed; inset: 0; z-index: 60; display: grid; place-items: center; padding: 3.5rem 6.5rem; background: rgb(255 255 255 / 0.16); -webkit-backdrop-filter: blur(8px); backdrop-filter: blur(8px); }
        .dark .tq-agenda-fondo { background: rgb(255 255 255 / 0.05); }
        @media (max-width: 1100px) { .tq-agenda-fondo { padding: 1rem; } }
        .tq-agenda { --ag-borde: var(--tq-borde, #E5E0D6); --ag-linea: var(--ag-borde); --ag-suave: var(--tq-texto-suave, #5F5B53); --ag-fuera: color-mix(in oklab, var(--ag-borde) 25%, transparent);
            --ag-turquesa: #0E8FAE; --ag-azul: var(--primary-600, #24389C);
            width: 100%; height: 100%; max-width: 76rem; max-height: 46rem; display: flex; flex-direction: column; border-radius: 20px; overflow: hidden;
            background: var(--tq-superficie-elevada, #fff); color: var(--tq-texto, inherit); box-shadow: 0 30px 80px rgb(30 25 15 / .25), 0 0 0 1px rgb(0 0 0 / .05); }
        .dark .tq-agenda { --ag-turquesa: #4DCFEA; --ag-azul: var(--primary-400, #9DB0FF); }
        [data-estado="vencida"] { --ag-c: #D9534A; --ag-f: #FBE9E7; }
        [data-estado="por_vencer"] { --ag-c: #B07A00; --ag-f: #FFF4D6; }
        [data-estado="programada"] { --ag-c: #0E8FAE; --ag-f: #E3F5F9; }
        [data-estado="hecha"] { --ag-c: #2F7D4F; --ag-f: #E5F3EA; }
        .dark [data-estado="vencida"] { --ag-c: #FF8A75; --ag-f: #4A2A26; }
        .dark [data-estado="por_vencer"] { --ag-c: #F2C14E; --ag-f: #43381E; }
        .dark [data-estado="programada"] { --ag-c: #4DCFEA; --ag-f: #1F3A42; }
        .dark [data-estado="hecha"] { --ag-c: #8FD3A5; --ag-f: #243A2D; }
        .tq-ag-cab { display: flex; align-items: center; gap: 10px; padding: 16px 20px; border-bottom: 1px solid var(--ag-linea); flex-wrap: wrap; }
        .tq-ag-titulo { font-size: 20px; font-weight: 700; }
        .tq-ag-btn { height: 32px; padding: 0 12px; border-radius: 9px; border: 1px solid var(--ag-borde); background: var(--tq-superficie, #fff); font-size: 13px; font-weight: 600; display: inline-flex; align-items: center; justify-content: center; gap: 6px; }
        .tq-ag-btn.p { background: var(--ag-turquesa); border-color: var(--ag-turquesa); color: #fff; }
        .tq-ag-btn.cuad { width: 32px; padding: 0; }
        .tq-ag-seg { display: inline-flex; border: 1px solid var(--ag-borde); border-radius: 9px; overflow: hidden; }
        .tq-ag-seg button { padding: 6px 12px; font-size: 13px; color: var(--ag-suave); }
        .tq-ag-seg button[aria-pressed="true"] { background: color-mix(in oklab, var(--ag-azul) 14%, transparent); color: var(--ag-azul); font-weight: 600; }
        .tq-ag-cuerpo { display: flex; flex: 1; min-height: 0; }
        .tq-ag-grande { flex: 1; display: flex; flex-direction: column; padding: 4px 16px 14px; min-width: 0; overflow: auto; position: relative; }
        .tq-ag-semana { display: grid; grid-template-columns: repeat(7, 1fr); }
        .tq-ag-semana div { font-size: 12px; font-weight: 600; color: var(--ag-suave); padding: 8px 10px; }
        .tq-ag-mes { flex: 1; display: grid; grid-template-columns: repeat(7, minmax(0, 1fr)); grid-auto-rows: minmax(4.4rem, 1fr); border-right: 1px solid var(--ag-linea); border-bottom: 1px solid var(--ag-linea); }
        .tq-ag-dia { border-top: 1px solid var(--ag-linea); border-left: 1px solid var(--ag-linea); padding: 6px 8px; display: flex; flex-direction: column; align-items: stretch; gap: 3px; min-width: 0; overflow: hidden; text-align: start; position: relative; }
        .tq-ag-dia:hover { background: color-mix(in oklab, var(--ag-azul) 5%, transparent); }
        .tq-ag-dia.tq-fuera { background: var(--ag-fuera); }
        .tq-ag-dia.tq-elegido { box-shadow: inset 0 0 0 2px var(--ag-azul); }
        .tq-ag-num { align-self: flex-start; display: inline-flex; width: 26px; height: 26px; border-radius: 50%; align-items: center; justify-content: center; font-size: 13px; }
        .tq-ag-dia.tq-hoy .tq-ag-num { background: var(--ag-turquesa); color: #fff; font-weight: 700; }
        .tq-ag-flecha { position: absolute; top: -2px; left: 20px; color: var(--ag-turquesa); font-size: 10px; line-height: 1; animation: tq-ag-salta 1.2s ease-in-out infinite; pointer-events: none; }
        @keyframes tq-ag-salta { 50% { transform: translateY(-3px); } }
        .tq-ag-pastilla { display: block; max-width: 100%; text-align: start; font-size: 11px; border-radius: 5px; padding: 2px 6px; background: var(--ag-f); color: var(--ag-c); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; font-weight: 600; }
        .tq-ag-mas { font-size: 11px; color: var(--ag-suave); font-weight: 600; }
        .tq-ag-leyenda { display: flex; gap: 14px; margin-top: 10px; font-size: 12px; color: var(--ag-suave); flex-wrap: wrap; }
        .tq-ag-leyenda span::before { content: ''; display: inline-block; width: 9px; height: 9px; border-radius: 3px; margin-right: 6px; background: var(--ag-c); vertical-align: middle; }
        .tq-ag-detalle { width: 360px; border-left: 1px solid var(--ag-linea); padding: 18px 20px; display: flex; flex-direction: column; gap: 4px; background: var(--tq-superficie, #FCFBF8); overflow: auto; }
        @media (max-width: 900px) { .tq-ag-cuerpo { flex-direction: column; } .tq-ag-detalle { width: 100%; border-left: 0; border-top: 1px solid var(--ag-linea); } }
        .tq-ag-act { display: flex; gap: 12px; padding: 12px 0; border-top: 1px solid var(--ag-linea); }
        .tq-ag-tag { font-size: 11px; font-weight: 600; border-radius: 6px; padding: 2px 7px; background: var(--ag-f); color: var(--ag-c); }
        .tq-ag-campo { width: 100%; margin-top: 0.35rem; padding: 0.35rem 0.5rem; border-radius: 0.45rem; border: 1px solid var(--ag-borde); background: var(--tq-superficie, #fff); color: inherit; font-size: 0.85rem; }
        /* El año (MV-025): doce meses chicos, cada día con el color de sus actividades. */
        .tq-agenda-escena { transition: transform 550ms cubic-bezier(.3, .7, .2, 1), opacity 550ms ease; }
        .tq-agenda-escena.tq-lejos { transform: scale(3.2); opacity: 0; }
        .tq-agenda-anio { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 1rem; padding-top: 0.75rem; }
        .tq-agenda-mini { padding: 0.4rem; border-radius: 0.5rem; text-align: left; }
        .tq-agenda-mini:hover { background: color-mix(in oklab, var(--ag-azul) 6%, transparent); }
        .tq-agenda-mini-nombre { font-size: 0.8rem; font-weight: 700; margin-bottom: 0.25rem; }
        .tq-agenda-mini-dias { display: grid; grid-template-columns: repeat(7, 1fr); gap: 2px; }
        .tq-agenda-mini-dias i { display: block; aspect-ratio: 1; border-radius: 2px; font-style: normal; background: var(--ag-fuera); }
        .tq-agenda-mini-dias i.tq-vacio { background: transparent; }
        .tq-agenda-mini-dias i[data-estado] { background: var(--ag-c); }
        @media (prefers-reduced-motion: reduce) { .tq-agenda-escena { transition: none; } .tq-ag-flecha { animation: none; } }
        html.tq-sin-animaciones .tq-agenda-escena { transition: none; }
    </style>

    <div class="tq-agenda" role="dialog" aria-label="Agenda">
        <div class="tq-ag-cab">
            <span class="tq-ag-titulo" x-text="vista === 'anio' ? '{{ $anio }}' : '{{ $meses[$mes - 1] }} {{ $anio }}'"></span>
            <button type="button" class="tq-ag-btn cuad" aria-label="Anterior" x-on:click="vista === 'anio' ? $wire.moverAnio(-1) : $wire.moverMes(-1)">‹</button>
            <button type="button" class="tq-ag-btn cuad" aria-label="Siguiente" x-on:click="vista === 'anio' ? $wire.moverAnio(1) : $wire.moverMes(1)">›</button>
            <button type="button" class="tq-ag-btn" x-on:click="$wire.irAHoy(); if (vista === 'anio') acercar({{ $hoy->month }})">Hoy</button>
            <span style="flex: 1"></span>
            {{-- MV-026: «Año» se aleja; «Mes» se acerca al mes elegido. Semana y Lista, más adelante. --}}
            <div class="tq-ag-seg" role="group" aria-label="Vista">
                <button type="button" x-bind:aria-pressed="vista === 'anio'" x-on:click="alejar()">Año</button>
                <button type="button" x-bind:aria-pressed="vista === 'mes'" x-on:click="acercar({{ $mes }})">Mes</button>
            </div>
            @if ($puedeRegistrar)<button type="button" class="tq-ag-btn p" wire:click="registrarEnDia">+ Registrar actividad</button>@endif
            <button type="button" class="tq-ag-btn cuad" aria-label="Cerrar" x-on:click="abierto = false">×</button>
        </div>

        <div class="tq-ag-cuerpo">
            <section class="tq-ag-grande">
                <div x-show="vista === 'anio'" x-ref="anio" class="tq-agenda-escena" x-bind:class="moviendo && 'tq-lejos'" x-bind:style="`transform-origin: ${origen}`">
                    <div class="tq-agenda-anio">
                        @foreach ($meses as $i => $nombre)
                            <button type="button" class="tq-agenda-mini" data-mes="{{ $i + 1 }}" x-on:click="acercar({{ $i + 1 }})">
                                <div class="tq-agenda-mini-nombre">{{ $nombre }}</div>
                                <div class="tq-agenda-mini-dias">
                                    @foreach (array_merge(...$semanas($anio, $i + 1)) as $d)
                                        @if ($d === null)
                                            <i class="tq-vacio"></i>
                                        @else
                                            <i @if (isset($dias[$d->toDateString()])) data-estado="{{ $dias[$d->toDateString()] }}" @endif title="{{ $d->format('d/m') }}"></i>
                                        @endif
                                    @endforeach
                                </div>
                            </button>
                        @endforeach
                    </div>
                </div>

                <div x-show="vista === 'mes'" x-cloak class="tq-agenda-escena" style="display: flex; flex-direction: column; flex: 1">
                    <div class="tq-ag-semana">@foreach (['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'] as $s)<div>{{ $s }}</div>@endforeach</div>
                    <div class="tq-ag-mes">
                        @foreach (array_merge(...$semanas($anio, $mes)) as $d)
                            @if ($d === null)
                                <div class="tq-ag-dia tq-fuera"></div>
                            @else
                                @php $f = $d->toDateString(); $lista = $delMes[$f] ?? []; @endphp
                                <button type="button" wire:click="elegirDia('{{ $f }}')" wire:key="dia-{{ $f }}"
                                    @class(['tq-ag-dia', 'tq-hoy' => $d->isSameDay($hoy), 'tq-elegido' => $f === $dia])>
                                    @if ($d->isSameDay($hoy))<span class="tq-ag-flecha" aria-label="Hoy">▼</span>@endif
                                    <span class="tq-ag-num">{{ $d->day }}</span>
                                    @foreach (array_slice($lista, 0, $maxPastillas) as $p)
                                        <span class="tq-ag-pastilla" data-estado="{{ $p['estado'] }}" title="{{ $p['hora'] }} {{ $p['asunto'] }}">{{ $p['hora'] }} {{ $p['asunto'] }}</span>
                                    @endforeach
                                    @if (count($lista) > $maxPastillas)<span class="tq-ag-mas">+{{ count($lista) - $maxPastillas }} más</span>@endif
                                </button>
                            @endif
                        @endforeach
                    </div>
                </div>

                <div class="tq-ag-leyenda">@foreach ($estados as $clave => $nombre)<span data-estado="{{ $clave }}">{{ $nombre }}</span>@endforeach</div>
            </section>

            {{-- El detalle del día elegido (al abrir, hoy). --}}
            <aside class="tq-ag-detalle">
                @if ($elegido)
                    <div style="font-size: 13px; color: var(--ag-suave)">{{ ucfirst($elegido->isoFormat('dddd')) }}</div>
                    <div style="font-size: 22px; font-weight: 700">{{ $elegido->isoFormat('D [de] MMMM') }}</div>
                    <div style="font-size: 12px; color: var(--ag-suave); margin-bottom: 6px">
                        {{ count($actividades) }} {{ count($actividades) === 1 ? 'actividad' : 'actividades' }}@if ($vencidasAntes > 0) · {{ $vencidasAntes }} {{ $vencidasAntes === 1 ? 'vencida' : 'vencidas' }} de días anteriores @endif
                    </div>
                @endif

                @foreach ($actividades as $a)
                    <div class="tq-ag-act" wire:key="act-{{ $a['id'] }}" data-estado="{{ $a['estado'] }}">
                        <div style="width: 44px; font-size: 13px; font-weight: 600; color: var(--ag-c)">{{ $a['hora'] }}</div>
                        <div style="flex: 1; min-width: 0">
                            <div style="font-size: 14px; font-weight: 600">{{ $a['asunto'] }}</div>
                            <div style="font-size: 12px; color: var(--ag-suave); margin-top: 2px">{{ $a['tipo'] }} · {{ $a['cuenta'] ?? '—' }}@if ($a['oportunidad']) · {{ $a['oportunidad'] }}@endif</div>
                            @if ($a['resultado'])<div style="font-size: 12px; color: var(--ag-suave); font-style: italic; margin-top: 2px">{{ $a['resultado'] }}</div>@endif
                            <div style="display: flex; gap: 6px; margin-top: 8px; align-items: center">
                                <span class="tq-ag-tag">{{ $estados[$a['estado']] }}</span>
                                <span style="flex: 1"></span>
                                @if ($a['estado'] !== 'hecha' && $puedeRegistrar && $cumpliendo !== $a['id'])
                                    <button type="button" class="tq-ag-btn" style="height: 26px; font-size: 12px" wire:click="$set('cumpliendo', {{ $a['id'] }})">Hecha</button>
                                @endif
                            </div>
                            @if ($cumpliendo === $a['id'])
                                <form wire:submit="marcarHecha({{ $a['id'] }})" style="margin-top: 6px">
                                    <textarea class="tq-ag-campo" rows="2" wire:model="quePaso" placeholder="Qué pasó"></textarea>
                                    <div style="display: flex; gap: 6px; margin-top: 6px">
                                        <x-filament::button type="submit" size="xs">Marcar hecha</x-filament::button>
                                        <x-filament::button type="button" size="xs" color="gray" wire:click="$set('cumpliendo', null)">Cancelar</x-filament::button>
                                    </div>
                                </form>
                            @endif
                        </div>
                    </div>
                @endforeach
                @if ($elegido && $actividades === [])<p style="font-size: 13px; color: var(--ag-suave); padding: 12px 0; border-top: 1px solid var(--ag-linea)">Nada este día.</p>@endif

                @if ($dia !== null && $puedeRegistrar)
                    @if (! $registrando)
                        <button type="button" class="tq-ag-btn" style="margin-top: auto" wire:click="registrarEnDia">+ Actividad para este día</button>
                    @else
                        <form wire:submit="registrar" style="margin-top: 10px; font-size: 13px">
                            <select class="tq-ag-campo" wire:model="nueva.tipo">@foreach ($tipos as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select>
                            <select class="tq-ag-campo" wire:model.live="nueva.cuenta_id" required><option value="">Cliente…</option>@foreach ($cuentas as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select>
                            <select class="tq-ag-campo" wire:model="nueva.oportunidad_id"><option value="">Sin oportunidad</option>@foreach ($oportunidades as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select>
                            <input class="tq-ag-campo" wire:model="nueva.asunto" placeholder="Asunto" required>
                            <input class="tq-ag-campo" type="time" wire:model="nueva.hora" required>
                            <div style="display: flex; gap: 6px; margin-top: 8px">
                                <x-filament::button type="submit" size="sm">Registrar</x-filament::button>
                                <x-filament::button type="button" size="sm" color="gray" wire:click="$set('registrando', false)">Cancelar</x-filament::button>
                            </div>
                        </form>
                    @endif
                @endif
            </aside>
        </div>
    </div>
</div>
