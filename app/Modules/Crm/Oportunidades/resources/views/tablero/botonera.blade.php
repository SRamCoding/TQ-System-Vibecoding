{{--
    El tablero de botonera de Oportunidades (plan 2.21d, D3–D7; prototipo aprobado). Seis columnas: las cuatro etapas,
    Ganadas y Perdidas, cada una con **su gema solo en el título** y sin montos (D3: ya están en el embudo de arriba).
    En medio, la **botonera** con la cantidad: al tocarla se despliega debajo la tabla de la etapa (D4). En la tabla se
    marcan oportunidades y pasan al tablero **solo con su código**, apiladas, hasta {{ $maximo }} por etapa; se quitan con
    su × (D5). «Avanzar a …» se activa con una sola marcada (D6). Un código se arrastra a la columna siguiente (D7): no se
    saltan etapas ni se retrocede, y a Perdidas pide el motivo. Arrastrar y el botón usan el mismo camino que la ficha
    (`tq-oportunidad-mover` → `MueveOportunidades`): una sola lógica (2.21c, D5). Al pasar (`tq-oportunidad-movida`), la
    gema de origen vuela en arco, estalla a mitad de camino y llega convertida en la de destino, y el título salta (D9);
    con «reducir movimiento» o «reducir animaciones», sin vuelo: el cambio es directo. Recibe `$columnas`
    (`DibujoDeLaBotonera`), `$maximo`, `$fichaAbierta` y `$textos`.
--}}
<div
    @class(['tq-botonera', 'tq-tablero-detras' => $fichaAbierta !== null])
    data-tablero-botonera
    x-data="tqBotonera(@js($textos), @js(array_map(fn ($c) => $c['estado'], $columnas)))"
    x-on:tq-oportunidad-movida.window="movida($event.detail)"
>
    <script>
        window.tqBotonera = (textos, orden) => ({
            arrastrada: null,
            sobre: null,
            agarrar(evento, codigo) {
                this.arrastrada = codigo;
                evento.dataTransfer.effectAllowed = 'move';
                evento.dataTransfer.setData('text/plain', String(codigo.id));
            },
            soltar(columna) {
                const c = this.arrastrada;
                this.sobre = null;
                this.arrastrada = null;
                if (! c || columna === c.estado) return;
                const decir = (texto) => window.dispatchEvent(new CustomEvent('tq-oportunidad-aviso', { detail: { texto, tono: 'no' } }));
                const desde = orden.indexOf(c.estado), hacia = orden.indexOf(columna);
                if (desde < 0 || c.estado === textos.ganada || c.estado === textos.perdida) return decir('Esa oportunidad ya está cerrada.');
                if (columna !== textos.ganada && columna !== textos.perdida) {
                    if (hacia < desde) return decir(textos.atras);
                    if (hacia > desde + 1) return decir(textos.salto);
                }
                this.mover(c, columna);
            },
            // El último movimiento pedido desde este tablero, para su animación (D9).
            ultimo: null,
            mover(c, hacia) {
                if (! c) return;
                this.ultimo = { id: c.id, desde: c.estado, hacia };
                window.dispatchEvent(new CustomEvent('tq-oportunidad-mover', { detail: { id: c.id, desde: c.estado, hacia, version: c.version, exigir: false } }));
            },
            quieto() {
                return window.matchMedia('(prefers-reduced-motion: reduce)').matches || document.documentElement.classList.contains('tq-sin-animaciones');
            },
            async movida(detalle) {
                const m = this.ultimo;
                this.ultimo = null;
                if (! detalle?.ok) return;
                if (m && Number(detalle.id) === m.id && ! this.quieto()) await this.volar(m.desde, m.hacia);
                this.$wire.$refresh();
            },
            // D9: la gema de la etapa de origen se levanta, vuela en arco a la de destino, a mitad de camino estalla en
            // luz y chispas y sale convertida en la gema de la etapa siguiente; se encoge en el título y este da un
            // salto. Solo transformaciones y opacidad (sin redibujar la página); nada corre en reposo.
            async volar(desde, hacia) {
                const origen = this.$root.querySelector(`[data-gema-titulo="${CSS.escape(desde)}"]`);
                const destino = this.$root.querySelector(`[data-gema-titulo="${CSS.escape(hacia)}"]`);
                if (! origen || ! destino) return;
                const a = origen.getBoundingClientRect(), b = destino.getBoundingClientRect();
                const gema = document.createElement('div');
                gema.className = 'tq-gema-vuela';
                gema.setAttribute('aria-hidden', 'true');
                gema.innerHTML = origen.innerHTML;
                gema.style.left = `${a.left}px`;
                gema.style.top = `${a.top}px`;
                document.body.appendChild(gema);
                const dx = b.left - a.left, dy = b.top - a.top, alto = Math.max(70, Math.abs(dx) * 0.35);
                const ida = gema.animate([
                    { transform: 'translate(0, 0) scale(1)' },
                    { transform: `translate(0, -14px) scale(1.5)`, offset: 0.15 },
                    { transform: `translate(${dx / 2}px, ${dy / 2 - alto}px) scale(2.1)`, offset: 0.5 },
                ], { duration: 520, easing: 'cubic-bezier(.3,.1,.3,1)', fill: 'forwards' });
                await ida.finished;
                this.destello(gema.getBoundingClientRect());
                gema.innerHTML = destino.innerHTML;
                const llegada = gema.animate([
                    { transform: `translate(${dx / 2}px, ${dy / 2 - alto}px) scale(2.1)` },
                    { transform: `translate(${dx}px, ${dy}px) scale(1)` },
                ], { duration: 520, easing: 'cubic-bezier(.4,0,.2,1)', fill: 'forwards' });
                await llegada.finished;
                gema.remove();
                destino.classList.remove('tq-salta');
                void destino.offsetWidth;
                destino.classList.add('tq-salta');
            },
            destello(r) {
                const x = r.left + r.width / 2, y = r.top + r.height / 2;
                const luz = document.createElement('div');
                luz.className = 'tq-gema-luz';
                luz.style.left = `${x}px`;
                luz.style.top = `${y}px`;
                document.body.appendChild(luz);
                luz.animate([{ transform: 'translate(-50%, -50%) scale(.2)', opacity: 1 }, { transform: 'translate(-50%, -50%) scale(2.4)', opacity: 0 }], { duration: 420, easing: 'ease-out' }).finished.then(() => luz.remove());
                for (let i = 0; i < 10; i++) {
                    const chispa = document.createElement('div');
                    const angulo = (Math.PI * 2 * i) / 10, lejos = 28 + (i % 3) * 10;
                    chispa.className = 'tq-gema-chispa';
                    chispa.style.left = `${x}px`;
                    chispa.style.top = `${y}px`;
                    document.body.appendChild(chispa);
                    chispa.animate([
                        { transform: 'translate(-50%, -50%) scale(1)', opacity: 1 },
                        { transform: `translate(calc(-50% + ${Math.cos(angulo) * lejos}px), calc(-50% + ${Math.sin(angulo) * lejos}px)) scale(.3)`, opacity: 0 },
                    ], { duration: 480, easing: 'ease-out' }).finished.then(() => chispa.remove());
                }
            },
        });
    </script>
    <style>
        .tq-botonera-columnas { display: grid; grid-template-columns: repeat(6, minmax(9rem, 1fr)); gap: 0.75rem; }
        @media (max-width: 1023px) { .tq-botonera-columnas { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
        @media (max-width: 639px) { .tq-botonera-columnas { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
        .tq-botonera-col { display: flex; flex-direction: column; gap: 0.6rem; min-height: 15rem; padding: 0.75rem; border-radius: 0.75rem; background: var(--gray-50); outline: 1px solid rgb(3 7 18 / 0.05); transition: outline-color 120ms ease, background-color 120ms ease; }
        .dark .tq-botonera-col { background: rgb(255 255 255 / 0.03); outline-color: rgb(255 255 255 / 0.06); }
        .tq-botonera-col[data-lugar="ganadas"] { background: color-mix(in oklab, var(--success-500) 7%, var(--gray-50)); }
        .tq-botonera-col[data-lugar="perdidas"] { background: color-mix(in oklab, var(--danger-500) 6%, var(--gray-50)); }
        .tq-botonera-col.tq-sobre { outline: 2px dashed var(--primary-600); }
        .tq-botonera-titulo { display: flex; align-items: center; gap: 0.5rem; font-weight: 600; font-size: 0.875rem; }
        .tq-botonera-titulo .tq-gema-titulo { display: inline-flex; transition: transform 300ms ease; }
        .tq-botonera-titulo .tq-gema-titulo.tq-salta { animation: tq-titulo-salta 500ms ease; }
        @keyframes tq-titulo-salta { 30% { transform: translateY(-6px) scale(1.15); } 60% { transform: translateY(1px); } }
        .tq-botonera-pila { display: flex; flex-direction: column; gap: 0.35rem; min-height: 1.5rem; }
        .tq-botonera-codigo { display: flex; align-items: center; justify-content: space-between; gap: 0.35rem; padding: 0.3rem 0.4rem 0.3rem 0.6rem; border-radius: 0.5rem; background: white; box-shadow: 0 1px 2px rgb(0 0 0 / 0.08); font-family: var(--mono-font-family, ui-monospace, monospace); font-size: 0.8125rem; font-weight: 600; cursor: grab; }
        .dark .tq-botonera-codigo { background: var(--gray-800); }
        .tq-botonera-codigo[draggable="false"] { cursor: default; }
        .tq-botonera-codigo button { display: inline-flex; padding: 0.1rem; border-radius: 0.25rem; color: var(--gray-400); }
        .tq-botonera-codigo button:hover, .tq-botonera-codigo button:focus-visible { color: var(--gray-700); background: var(--gray-100); }
        .tq-botonera-boton { margin-top: auto; display: flex; flex-direction: column; align-items: center; gap: 0.1rem; padding: 0.6rem; border-radius: 0.6rem; background: white; outline: 1px solid rgb(3 7 18 / 0.08); }
        .dark .tq-botonera-boton { background: var(--gray-900); outline-color: rgb(255 255 255 / 0.1); }
        .tq-botonera-boton b { font-size: 1.5rem; line-height: 1; font-variant-numeric: tabular-nums; }
        .tq-botonera-boton small { font-size: 0.75rem; color: var(--gray-500); }
        .tq-botonera-boton[aria-expanded="true"] { outline: 2px solid var(--morado-600); }
        .tq-botonera-tabla { margin-top: 1rem; }
        /* D9: la gema que vuela al avanzar, su destello y sus chispas (fuera del tablero, encima de todo). */
        .tq-gema-vuela { position: fixed; z-index: 60; pointer-events: none; will-change: transform; filter: drop-shadow(0 4px 8px rgb(0 0 0 / .25)); }
        .tq-gema-luz { position: fixed; z-index: 61; width: 3.5rem; height: 3.5rem; border-radius: 9999px; pointer-events: none; background: radial-gradient(circle, rgb(255 255 255 / .95), color-mix(in oklab, var(--morado-400) 60%, transparent) 45%, transparent 70%); }
        .tq-gema-chispa { position: fixed; z-index: 61; width: 0.35rem; height: 0.35rem; border-radius: 9999px; pointer-events: none; background: white; box-shadow: 0 0 6px var(--morado-400); }
        @media (prefers-reduced-motion: reduce) { .tq-botonera-titulo .tq-gema-titulo.tq-salta { animation: none; } }
        html.tq-sin-animaciones .tq-botonera-titulo .tq-gema-titulo.tq-salta { animation: none; }
        .tq-botonera-tabla table { width: 100%; border-collapse: collapse; font-size: 0.875rem; }
        .tq-botonera-tabla th { text-align: start; font-size: 0.75rem; font-weight: 500; color: var(--gray-500); padding: 0.4rem 0.5rem; border-bottom: 1px solid var(--gray-200); }
        .tq-botonera-tabla td { padding: 0.45rem 0.5rem; border-bottom: 1px solid var(--gray-100); }
        .dark .tq-botonera-tabla th { border-bottom-color: var(--gray-700); }
        .dark .tq-botonera-tabla td { border-bottom-color: var(--gray-800); }
        .tq-botonera-tabla .tq-num { text-align: end; font-variant-numeric: tabular-nums; }
        .tq-botonera-tabla tr.tq-puesta { background: color-mix(in oklab, var(--primary-600) 6%, transparent); }
        .tq-botonera-cab { display: flex; flex-wrap: wrap; align-items: center; gap: 0.75rem; margin-bottom: 0.5rem; }
        .tq-botonera-ayuda { margin-top: 0.75rem; font-size: 0.8125rem; color: var(--gray-500); }
    </style>

    <div class="tq-botonera-columnas">
        @foreach ($columnas as $c)
            <div
                class="tq-botonera-col"
                data-lugar="{{ $c['lugar'] }}"
                data-estado="{{ $c['estado'] }}"
                x-bind:class="{ 'tq-sobre': sobre === @js($c['estado']) }"
                x-on:dragover.prevent="sobre = @js($c['estado'])"
                x-on:dragleave="sobre = null"
                x-on:drop.prevent="soltar(@js($c['estado']))"
            >
                <div class="tq-botonera-titulo">
                    <span class="tq-gema-titulo" data-gema-titulo="{{ $c['estado'] }}"><x-oportunidades::gema :talla="$c['talla']" :tamano="26" :semilla="3 + $loop->index" decorativa /></span>
                    <span>{{ $c['nombre'] }}</span>
                </div>
                <div class="tq-botonera-pila" data-pila="{{ $c['estado'] }}">
                    @foreach ($c['puestas'] as $codigo)
                        <div
                            class="tq-botonera-codigo"
                            data-codigo="{{ $codigo['id'] }}"
                            title="{{ $codigo['titulo'] }}"
                            draggable="{{ in_array($c['lugar'], ['ganadas', 'perdidas'], true) ? 'false' : 'true' }}"
                            x-on:dragstart="agarrar($event, @js($codigo))"
                            x-on:dblclick="$wire.abrirFicha({{ $codigo['id'] }})"
                            wire:key="codigo-{{ $codigo['id'] }}"
                        >
                            <span>{{ $codigo['numero'] }}</span>
                            <button type="button" wire:click="alternarEnTablero({{ $codigo['id'] }}, false)" aria-label="Quitar {{ $codigo['numero'] }} del tablero" title="Quitar del tablero">
                                <x-filament::icon icon="heroicon-m-x-mark" class="h-4 w-4" />
                            </button>
                        </div>
                    @endforeach
                </div>
                <button type="button" class="tq-botonera-boton" wire:click="abrirEtapa('{{ $c['lugar'] }}')" aria-expanded="{{ $c['abierta'] ? 'true' : 'false' }}" data-botonera="{{ $c['lugar'] }}">
                    <b>{{ $c['cantidad'] }}</b>
                    <small>{{ $c['cantidad'] === 1 ? 'oportunidad' : 'oportunidades' }}</small>
                    <small>{{ $c['abierta'] ? 'Ocultar tabla ▴' : 'Ver tabla ▾' }}</small>
                </button>
            </div>
        @endforeach
    </div>

    @php $abierta = collect($columnas)->firstWhere('abierta', true); @endphp
    @if ($abierta !== null)
        @php
            $marcadas = array_values(array_filter($abierta['filas'], fn ($f) => $f['puesta']));
            $una = count($abierta['puestas']) === 1 ? $abierta['puestas'][0] : null;
            $sala = in_array($abierta['lugar'], ['ganadas', 'perdidas'], true);
        @endphp
        <x-filament::section class="tq-botonera-tabla" compact data-tabla-de="{{ $abierta['lugar'] }}">
            <div class="tq-botonera-cab">
                <x-oportunidades::gema :talla="$abierta['talla']" :tamano="30" decorativa />
                <div>
                    <b>{{ $abierta['nombre'] }}</b><br>
                    <small class="text-gray-500">{{ $abierta['cantidad'] }} {{ $abierta['cantidad'] === 1 ? 'oportunidad' : 'oportunidades' }} · {{ count($abierta['puestas']) }} de {{ $maximo }} en el tablero</small>
                </div>
                @if ($abierta['avanzaA'] !== null)
                    <x-filament::button
                        size="sm"
                        icon="heroicon-m-chevron-double-right"
                        :disabled="$una === null"
                        x-on:click="mover(@js($una), @js($abierta['avanzaA']['estado']))"
                        data-avanzar="{{ $una === null ? 'no' : 'si' }}"
                        :tooltip="$una === null ? 'Se avanza de una en una: deja solo una en el tablero de esta etapa' : null"
                    >Avanzar a {{ $abierta['avanzaA']['nombre'] }}</x-filament::button>
                @endif
                <x-filament::link tag="button" wire:click="abrirEtapa('{{ $abierta['lugar'] }}')" icon="heroicon-m-x-mark" style="margin-inline-start: auto">Cerrar</x-filament::link>
            </div>
            <table>
                <thead>
                    <tr>
                        <th style="width: 2rem"><span class="sr-only">En el tablero</span></th><th>Código</th><th>Oportunidad</th><th>Entidad</th><th class="tq-num">Monto</th>
                        @if ($sala)<th>Cierre</th>@else<th class="tq-num">Días</th>@endif
                    </tr>
                </thead>
                <tbody>
                    @forelse ($abierta['filas'] as $f)
                        <tr @class(['tq-puesta' => $f['puesta']]) wire:key="fila-{{ $f['id'] }}">
                            <td>
                                <x-filament::input.checkbox
                                    :checked="$f['puesta']"
                                    :disabled="! $f['puesta'] && $abierta['lleno']"
                                    wire:click="alternarEnTablero({{ $f['id'] }}, {{ $f['puesta'] ? 'false' : 'true' }})"
                                    aria-label="Poner {{ $f['numero'] }} en el tablero"
                                    :title="! $f['puesta'] && $abierta['lleno'] ? 'Máximo '.$maximo.' en el tablero' : null"
                                    data-marcar="{{ $f['id'] }}"
                                />
                            </td>
                            <td><b class="font-mono">{{ $f['numero'] }}</b></td>
                            <td><button type="button" class="text-primary-600 dark:text-primary-400" wire:click="abrirFicha({{ $f['id'] }})">{{ $f['nombre'] }}</button></td>
                            <td>{{ $f['cuenta'] }}</td>
                            <td class="tq-num">{{ $f['monto'] }}</td>
                            @if ($sala)
                                <td>{{ $f['cierre'] ?? '—' }}{{ $f['motivo'] !== null ? ' · '.$f['motivo'] : '' }}</td>
                            @else
                                <td class="tq-num" @if ($f['atascada']) style="color: var(--warning-600); font-weight: 600" @endif>{{ $f['dias'] }}</td>
                            @endif
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-gray-500">No hay oportunidades en esta etapa.</td></tr>
                    @endforelse
                </tbody>
            </table>
            @if ($abierta['avanzaA'] !== null)
                <p class="tq-botonera-ayuda">Avanzar se activa con una sola en el tablero de esta etapa: cada paso pide lo suyo.</p>
            @endif
        </x-filament::section>
    @else
        <p class="tq-botonera-ayuda">Toca la botonera de una etapa para ver todas sus oportunidades y elegir hasta {{ $maximo }} para tenerlas a mano en el tablero. Arrastra un código a la etapa siguiente para avanzarla.</p>
    @endif
</div>
