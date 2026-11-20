{{--
    MV-025 y MV-026: la agenda del muelle. Se abre con el evento `tq-agenda-abrir` (el ícono del calendario con reloj).
    Al abrirse muestra el año completo y enseguida, sola, se acerca al mes actual; «Año» se aleja. Cada día lleva el
    color de sus actividades (vencidas, por vencer, programadas, hechas); hoy tiene una flecha encima. Al presionar un
    día, el recuadro angosto de al lado muestra sus actividades, para marcarlas hechas o registrar otra.
--}}
@php
    $meses = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
    $estados = ['vencida' => 'Vencidas', 'por_vencer' => 'Por vencer', 'programada' => 'Programadas', 'hecha' => 'Hechas'];
    // Las semanas de un mes, de lunes a domingo, con null en los huecos.
    $semanas = function (int $anio, int $mes): array {
        $primero = \Carbon\CarbonImmutable::create($anio, $mes, 1);
        $celdas = array_fill(0, $primero->dayOfWeekIso - 1, null);
        for ($d = 1; $d <= $primero->daysInMonth; $d++) {
            $celdas[] = $primero->setDay($d);
        }
        return array_chunk(array_pad($celdas, (int) ceil(count($celdas) / 7) * 7, null), 7);
    };
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
        },
        abrir() {
            this.abierto = true
            this.vista = 'anio'
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
        .tq-agenda-fondo { position: fixed; inset: 0; z-index: 60; display: grid; place-items: center; padding: 1rem; background: rgb(0 0 0 / 0.35); backdrop-filter: blur(2px); }
        .tq-agenda { display: flex; align-items: stretch; max-width: 100%; max-height: calc(100vh - 2rem); border-radius: 1rem; overflow: hidden; background: white; box-shadow: 0 25px 60px rgb(0 0 0 / 0.3); }
        .dark .tq-agenda { background: var(--gray-900); }
        .tq-agenda-grande { width: min(44rem, 68vw); padding: 1.25rem; overflow: auto; position: relative; }
        .tq-agenda-angosto { width: 19rem; padding: 1.25rem 1rem; border-left: 1px solid var(--gray-200); overflow: auto; background: var(--gray-50); }
        .dark .tq-agenda-angosto { border-left-color: var(--gray-700); background: rgb(255 255 255 / 0.03); }
        @media (max-width: 760px) { .tq-agenda { flex-direction: column; } .tq-agenda-grande { width: 100%; } .tq-agenda-angosto { width: 100%; border-left: 0; border-top: 1px solid var(--gray-200); } }
        .tq-agenda-cabeza { display: flex; align-items: center; justify-content: space-between; gap: 0.5rem; margin-bottom: 1rem; }
        .tq-agenda-titulo { font-size: 1.15rem; font-weight: 700; }
        .tq-agenda-boton { padding: 0.25rem 0.6rem; border-radius: 0.5rem; font-size: 0.85rem; font-weight: 600; color: var(--primary-600); }
        .tq-agenda-boton:hover { background: var(--gray-100); }
        .dark .tq-agenda-boton { color: var(--primary-400); } .dark .tq-agenda-boton:hover { background: rgb(255 255 255 / 0.06); }
        .tq-agenda-escena { transition: transform 550ms cubic-bezier(.3, .7, .2, 1), opacity 550ms ease; }
        .tq-agenda-escena.tq-lejos { transform: scale(3.2); opacity: 0; }
        .tq-agenda-escena.tq-chico { transform: scale(0.5); opacity: 0; }
        .tq-agenda-anio { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 0.9rem; }
        @media (max-width: 760px) { .tq-agenda-anio { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
        .tq-agenda-mini { padding: 0.35rem; border-radius: 0.5rem; cursor: pointer; text-align: left; }
        .tq-agenda-mini:hover { background: var(--gray-100); } .dark .tq-agenda-mini:hover { background: rgb(255 255 255 / 0.05); }
        .tq-agenda-mini-nombre { font-size: 0.75rem; font-weight: 700; margin-bottom: 0.2rem; }
        .tq-agenda-mini-dias { display: grid; grid-template-columns: repeat(7, 1fr); gap: 1px; }
        .tq-agenda-mini-dias i { display: block; aspect-ratio: 1; border-radius: 2px; font-style: normal; background: var(--gray-100); }
        .dark .tq-agenda-mini-dias i { background: rgb(255 255 255 / 0.05); }
        .tq-agenda-mini-dias i.tq-vacio { background: transparent; }
        .tq-agenda-mes { display: grid; grid-template-columns: repeat(7, minmax(0, 1fr)); gap: 0.35rem; }
        .tq-agenda-semana { font-size: 0.7rem; font-weight: 600; text-align: center; color: var(--gray-500); text-transform: uppercase; }
        .tq-agenda-dia { position: relative; height: 3.4rem; border-radius: 0.6rem; padding: 0.3rem 0.4rem; text-align: left; font-size: 0.85rem; font-weight: 600; background: var(--gray-50); border: 2px solid transparent; }
        .dark .tq-agenda-dia { background: rgb(255 255 255 / 0.04); }
        .tq-agenda-dia:hover { border-color: var(--gray-300); }
        .tq-agenda-dia.tq-elegido { border-color: var(--primary-600); }
        .tq-agenda-dia.tq-hoy { box-shadow: inset 0 0 0 1px var(--primary-600); }
        .tq-agenda-dia[data-estado]::after { content: ''; position: absolute; left: 0.4rem; right: 0.4rem; bottom: 0.35rem; height: 0.35rem; border-radius: 9999px; background: var(--tq-color); }
        [data-estado="vencida"] { --tq-color: var(--danger-500); }
        [data-estado="por_vencer"] { --tq-color: var(--warning-500); }
        [data-estado="programada"] { --tq-color: var(--info-500); }
        [data-estado="hecha"] { --tq-color: var(--success-500); }
        .tq-agenda-mini-dias i[data-estado] { background: var(--tq-color); }
        .tq-agenda-dia[data-estado] { background: color-mix(in oklab, var(--tq-color) 12%, transparent); }
        /* La flecha que señala hoy, arriba del día. */
        .tq-agenda-flecha { position: absolute; top: -0.95rem; left: 50%; translate: -50% 0; color: var(--primary-600); font-size: 0.9rem; line-height: 1; animation: tq-agenda-salta 1.2s ease-in-out infinite; pointer-events: none; }
        @keyframes tq-agenda-salta { 50% { transform: translateY(-3px); } }
        @media (prefers-reduced-motion: reduce) { .tq-agenda-escena { transition: none; } .tq-agenda-flecha { animation: none; } }
        .tq-agenda-leyenda { display: flex; flex-wrap: wrap; gap: 0.75rem; margin-top: 1rem; font-size: 0.75rem; color: var(--gray-500); }
        .tq-agenda-leyenda span::before { content: ''; display: inline-block; width: 0.6rem; height: 0.6rem; margin-right: 0.3rem; border-radius: 9999px; background: var(--tq-color); }
        .tq-agenda-act { padding: 0.6rem 0; border-bottom: 1px solid var(--gray-200); font-size: 0.85rem; }
        .dark .tq-agenda-act { border-bottom-color: var(--gray-700); }
        .tq-agenda-etiqueta { display: inline-block; padding: 0 0.4rem; border-radius: 9999px; font-size: 0.7rem; font-weight: 600; color: white; background: var(--tq-color); }
        .tq-agenda-campo { width: 100%; margin-top: 0.35rem; padding: 0.35rem 0.5rem; border-radius: 0.45rem; border: 1px solid var(--gray-300); background: white; font-size: 0.85rem; }
        .dark .tq-agenda-campo { background: var(--gray-800); border-color: var(--gray-600); }
    </style>

    <div class="tq-agenda" role="dialog" aria-label="Agenda">
        {{-- El recuadro grande: el calendario. --}}
        <section class="tq-agenda-grande">
            <div x-show="vista === 'anio'" x-ref="anio" class="tq-agenda-escena" x-bind:class="moviendo && 'tq-lejos'" x-bind:style="`transform-origin: ${origen}`">
                <div class="tq-agenda-cabeza">
                    <button type="button" class="tq-agenda-boton" wire:click="moverAnio(-1)" aria-label="Año anterior">‹</button>
                    <span class="tq-agenda-titulo">{{ $anio }}</span>
                    <button type="button" class="tq-agenda-boton" wire:click="moverAnio(1)" aria-label="Año siguiente">›</button>
                </div>
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

            <div x-show="vista === 'mes'" x-cloak class="tq-agenda-escena" x-transition:enter-start="tq-chico">
                <div class="tq-agenda-cabeza">
                    <button type="button" class="tq-agenda-boton" wire:click="moverMes(-1)" aria-label="Mes anterior">‹</button>
                    <span class="tq-agenda-titulo">{{ $meses[$mes - 1] }} {{ $anio }}</span>
                    <span class="flex items-center gap-1">
                        <button type="button" class="tq-agenda-boton" wire:click="moverMes(1)" aria-label="Mes siguiente">›</button>
                        {{-- MV-026: «Año» vuelve a mostrar el año completo. --}}
                        <button type="button" class="tq-agenda-boton" x-on:click="alejar()">Año</button>
                    </span>
                </div>
                <div class="tq-agenda-mes" style="padding-top: 0.9rem">
                    @foreach (['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'] as $s)
                        <div class="tq-agenda-semana">{{ $s }}</div>
                    @endforeach
                    @foreach (array_merge(...$semanas($anio, $mes)) as $d)
                        @if ($d === null)
                            <div></div>
                        @else
                            @php $f = $d->toDateString(); @endphp
                            <button type="button" wire:click="elegirDia('{{ $f }}')"
                                @class(['tq-agenda-dia', 'tq-hoy' => $d->isSameDay($hoy), 'tq-elegido' => $f === $dia])
                                @if (isset($dias[$f])) data-estado="{{ $dias[$f] }}" @endif>
                                @if ($d->isSameDay($hoy))<span class="tq-agenda-flecha" aria-label="Hoy">▼</span>@endif
                                {{ $d->day }}
                            </button>
                        @endif
                    @endforeach
                </div>
            </div>

            <div class="tq-agenda-leyenda">
                @foreach ($estados as $clave => $nombre)
                    <span data-estado="{{ $clave }}">{{ $nombre }}</span>
                @endforeach
            </div>
        </section>

        {{-- El recuadro angosto, pegado: las actividades del día elegido. --}}
        <aside class="tq-agenda-angosto">
            <div class="flex items-center justify-between">
                <h3 class="font-bold">{{ $dia === null ? 'Elige un día' : \Carbon\CarbonImmutable::parse($dia)->locale('es')->isoFormat('dddd D [de] MMMM') }}</h3>
                <button type="button" class="tq-agenda-boton" x-on:click="abierto = false" aria-label="Cerrar">✕</button>
            </div>

            @forelse ($actividades as $a)
                <div class="tq-agenda-act" wire:key="act-{{ $a['id'] }}">
                    <div class="flex items-center justify-between gap-2">
                        <strong>{{ $a['hora'] }} · {{ $a['tipo'] }}</strong>
                        <span class="tq-agenda-etiqueta" data-estado="{{ $a['estado'] }}">{{ ['vencida' => 'vencida', 'por_vencer' => 'por vencer', 'programada' => 'programada', 'hecha' => 'hecha'][$a['estado']] }}</span>
                    </div>
                    <div>{{ $a['asunto'] }}</div>
                    <div class="text-gray-500">Con {{ $a['cuenta'] ?? '—' }}@if ($a['oportunidad']) · {{ $a['oportunidad'] }}@endif</div>
                    <div class="text-gray-500">Responsable: {{ $a['quien'] }}</div>
                    @if ($a['resultado'])<div class="text-gray-500 italic">{{ $a['resultado'] }}</div>@endif
                    @if ($a['estado'] !== 'hecha' && $puedeRegistrar)
                        <button type="button" class="tq-agenda-boton" style="padding-left: 0" wire:click="marcarHecha({{ $a['id'] }})">✓ Marcar hecha</button>
                    @endif
                </div>
            @empty
                <p class="mt-3 text-sm text-gray-500">{{ $dia === null ? '' : 'Nada este día.' }}</p>
            @endforelse

            @if ($dia !== null && $puedeRegistrar)
                @if (! $registrando)
                    <button type="button" class="tq-agenda-boton mt-3" style="padding-left: 0" wire:click="$set('registrando', true)">+ Registrar actividad</button>
                @else
                    <form wire:submit="registrar" class="mt-3 text-sm">
                        <select class="tq-agenda-campo" wire:model="nueva.tipo">
                            @foreach ($tipos as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach
                        </select>
                        <select class="tq-agenda-campo" wire:model.live="nueva.cuenta_id" required>
                            <option value="">Cliente…</option>
                            @foreach ($cuentas as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach
                        </select>
                        <select class="tq-agenda-campo" wire:model="nueva.oportunidad_id">
                            <option value="">Sin oportunidad</option>
                            @foreach ($oportunidades as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach
                        </select>
                        <input class="tq-agenda-campo" wire:model="nueva.asunto" placeholder="Asunto" required>
                        <input class="tq-agenda-campo" type="time" wire:model="nueva.hora" required>
                        <div class="mt-2 flex gap-2">
                            <x-filament::button type="submit" size="sm">Registrar</x-filament::button>
                            <x-filament::button type="button" size="sm" color="gray" wire:click="$set('registrando', false)">Cancelar</x-filament::button>
                        </div>
                    </form>
                @endif
            @endif
        </aside>
    </div>
</div>
