{{--
    La ficha de una oportunidad en el panel deslizante (plan 2.21c, paso 7; D6 y D8) y el controlador de los movimientos
    (paso 6; D4 y D5). Lo pone en la página `MuestraLaFicha::fichaDeOportunidad()`; recibe `$ficha` (de
    `FichaDeOportunidad::datos`, o nulo), `$version` (la leída al abrirla) y `$textos`.

    El panel: se desliza desde la derecha encima de la vista, que sigue visible y usable (patrón de `interfaz.md`); la
    gema en grande con su talla, los datos, lo que exige la transición siguiente (Cumple o Falta) y los botones que la
    persona puede usar. Una cerrada: lo de su cierre, sin botones. Se cierra con la ✕ o Esc, deslizándose afuera, y el
    foco vuelve a la tarjeta. No atrapa el foco (paso 10): como en el patrón de la casa, lo de atrás y la barra de la
    izquierda siguen usables; al abrir, el foco va al título. La próxima acción no sale hasta CRM-05 (D8).

    El controlador (`tqMovimientoOportunidad`): el único camino de los botones de la ficha y del arrastre del tablero.
    Escucha `tq-oportunidad-mover` ({ id, desde, hacia, version, exigir }) y llama a `abrirMovimiento` (con `exigir`, la
    rueda de tallado revisa los requisitos: si falta algo, la rueda se pone roja y el aviso dice qué). El diálogo
    devuelve lo llenado con `tq-oportunidad-dialogo-listo`; ahí `moverOportunidad` ejecuta la acción real con la versión
    tomada; si pasa, la rueda gira y `despuesDeMover` redibuja (la gema llega tallada). Al terminar avisa
    `tq-oportunidad-movida` ({ id, ok }). Las ruedas son `[data-rueda="<etapa de salida>"] .tq-tablero-rueda` (si la
    vista no tiene, nada gira). `tq-oportunidad-aviso` ({ texto, tono }) muestra una línea en el aviso. Con «reducir
    movimiento» o «reducir animaciones», sin giro ni deslizamiento: los cambios son instantáneos.
--}}
<div
    class="tq-ficha-raiz"
    x-data="tqMovimientoOportunidad(@js($textos))"
    x-on:tq-oportunidad-mover.window="mover($event.detail)"
    x-on:tq-oportunidad-dialogo-listo.window="dialogoListo($event.detail)"
    x-on:tq-oportunidad-aviso.window="decir($event.detail.texto, $event.detail.tono)"
    x-on:modal-closed.window="dialogoCerrado($event.detail)"
>
    <script>
        window.tqMovimientoOportunidad = (textos) => ({
            // { id, desde, hacia, version, exigir, listo }: el movimiento en curso (uno a la vez).
            movimiento: null,
            aviso: null,
            // Lo que oye el lector de pantalla (paso 10): una región siempre presente, porque una que aparece con
            // `x-show` a la vez que su texto no siempre se anuncia; se vacía antes para que un aviso repetido se repita.
            anuncio: '',
            relojAviso: null,

            quieto() {
                return window.matchMedia('(prefers-reduced-motion: reduce)').matches || document.documentElement.classList.contains('tq-sin-animaciones');
            },

            decir(texto, tono = 'no') {
                if (! texto) return;
                this.aviso = { texto, tono };
                this.anuncio = '';
                this.$nextTick(() => this.anuncio = texto);
                clearTimeout(this.relojAviso);
                this.relojAviso = setTimeout(() => this.aviso = null, 5000);
            },

            // La rueda de tallado entre la etapa de salida y la siguiente (o la bifurcación); perder no pasa por ninguna.
            marcarRueda(m, estado) {
                const rueda = m.hacia === textos.perdida ? null : document.querySelector(`[data-rueda="${CSS.escape(m.desde)}"] .tq-tablero-rueda`);
                if (! rueda) return;
                rueda.classList.remove('tq-rueda-revisa', 'tq-rueda-no', 'tq-rueda-si');
                if (estado === null) return;
                void rueda.offsetWidth;
                rueda.classList.add(`tq-rueda-${estado}`);
            },

            async mover(detalle) {
                const id = Number(detalle?.id);
                if (! id) return;
                const m = { id, desde: String(detalle.desde), hacia: String(detalle.hacia), version: Number(detalle.version), exigir: Boolean(detalle.exigir), listo: false };
                if (this.movimiento !== null) return this.terminar(m, false);
                this.movimiento = m;
                this.marcarRueda(m, 'revisa');
                let r;
                try {
                    r = await this.$wire.abrirMovimiento(m.id, m.desde, m.hacia, m.version, m.exigir);
                } catch (error) {
                    r = { ok: false, conflicto: false, mensaje: 'No se pudo revisar. Vuelve a intentarlo.' };
                }
                if (r.ok) return this.marcarRueda(m, null);
                await this.fallar(m, r);
            },

            // Lo llenado en el diálogo (`MueveOportunidades::dialogoDelMovimiento`): se ejecuta la acción real.
            dialogoListo(detalle) {
                const m = this.movimiento;
                if (m === null || m.listo || Number(detalle?.oportunidad) !== m.id) return;
                m.listo = true;
                // Plan 2.21d: con la certificación, el diálogo ya ejecutó el avance en el servidor (un archivo no viaja).
                if (detalle.resultado) return this.concluir(m, detalle.resultado);
                this.ejecutar(m, detalle.datos ?? {});
            },

            // El diálogo se cerró sin confirmar (Cancelar, Esc o la ✕): la tarjeta vuelve a su lugar.
            dialogoCerrado(detalle) {
                const m = this.movimiento;
                if (m === null || m.listo || ! String(detalle?.id ?? '').startsWith(`fi-${this.$wire.$id}-action-`)) return;
                setTimeout(() => {
                    if (this.movimiento === m && ! m.listo) this.terminar(m, false);
                }, 250);
            },

            async ejecutar(m, datos) {
                this.marcarRueda(m, 'revisa');
                let r;
                try {
                    r = await this.$wire.moverOportunidad(m.id, m.desde, m.hacia, m.version, datos);
                } catch (error) {
                    r = { ok: false, conflicto: false, mensaje: 'No se pudo guardar. Vuelve a intentarlo.' };
                }
                await this.concluir(m, r);
            },

            async concluir(m, r) {
                if (! r.ok) return this.fallar(m, r);
                this.marcarRueda(m, 'si');
                if (! this.quieto()) await new Promise((listo) => setTimeout(listo, 650));
                this.marcarRueda(m, null);
                await this.$wire.despuesDeMover(m.id);
                this.decir(r.mensaje, 'si');
                this.terminar(m, true);
            },

            async fallar(m, r) {
                this.marcarRueda(m, 'no');
                this.decir(r.mensaje);
                setTimeout(() => this.marcarRueda(m, null), 900);
                // Otro la movió o la anuló: la vista se redibuja con lo de ahora.
                if (r.conflicto) await this.$wire.despuesDeMover(m.id);
                this.terminar(m, false);
            },

            terminar(m, ok) {
                if (this.movimiento === m) this.movimiento = null;
                window.dispatchEvent(new CustomEvent('tq-oportunidad-movida', { detail: { id: m.id, ok } }));
            },
        });
    </script>
    <style>
        .tq-ficha-aviso { position: fixed; z-index: 45; left: 50%; bottom: 1.25rem; transform: translateX(-50%); max-width: min(36rem, calc(100% - 2rem)); margin: 0; padding: 0.625rem 1rem; border-radius: 12px; font-size: 0.875rem; font-weight: 500; line-height: 1.35; background: var(--color-white, #fff); box-shadow: 0 0 0 1px rgb(9 9 11 / 0.08), 0 10px 30px rgb(9 9 11 / 0.18); border-left: 4px solid var(--danger-600); }
        .tq-ficha-aviso[data-tono="si"] { border-left-color: var(--success-600); }
        .dark .tq-ficha-aviso { background: var(--gray-800); box-shadow: 0 0 0 1px rgb(255 255 255 / 0.1), 0 10px 30px rgb(0 0 0 / 0.5); }

        .tq-ficha { position: fixed; z-index: 30; top: 4rem; right: 0; bottom: 0; width: min(100%, 26rem); overflow-y: auto; display: flex; flex-direction: column; gap: 1rem; padding: 1.25rem 1.25rem calc(1.25rem + env(safe-area-inset-bottom, 0px)); background: var(--color-white, #fff); box-shadow: -8px 0 28px rgb(0 0 0 / 0.18), -1px 0 0 rgb(9 9 11 / 0.08); animation: tq-ficha-entra 200ms ease-out both; }
        .dark .tq-ficha { background: var(--gray-900); box-shadow: -8px 0 28px rgb(0 0 0 / 0.5), -1px 0 0 rgb(255 255 255 / 0.1); }
        .tq-ficha.tq-saliendo { animation: tq-ficha-sale 200ms ease-in both; }
        @keyframes tq-ficha-entra { from { opacity: 0; transform: translateX(3rem); } to { opacity: 1; transform: none; } }
        @keyframes tq-ficha-sale { from { opacity: 1; transform: none; } to { opacity: 0; transform: translateX(3rem); } }
        .tq-ficha-cabeza { display: flex; align-items: flex-start; justify-content: space-between; gap: 0.75rem; }
        .tq-ficha-cuenta { font-size: 0.75rem; color: var(--gray-500); }
        .tq-ficha-titulo { margin: 0.125rem 0 0; font-size: 1.125rem; font-weight: 700; line-height: 1.25; outline: none; overflow-wrap: anywhere; }
        .tq-ficha-gema { display: grid; place-items: center; gap: 0.375rem; padding: 0.875rem 0 0.75rem; border-radius: 12px; background: radial-gradient(ellipse at 50% 70%, var(--gray-100), transparent 70%); }
        .dark .tq-ficha-gema { background: radial-gradient(ellipse at 50% 70%, var(--gray-800), transparent 70%); }
        .tq-ficha-estado { margin: 0; font-size: 0.8125rem; font-weight: 500; color: var(--gray-500); }
        .tq-ficha-datos { margin: 0; display: flex; flex-direction: column; }
        .tq-ficha-datos div { display: flex; justify-content: space-between; gap: 0.75rem; padding: 0.4375rem 0; border-bottom: 1px solid rgb(9 9 11 / 0.06); font-size: 0.8125rem; }
        .dark .tq-ficha-datos div { border-bottom-color: rgb(255 255 255 / 0.08); }
        .tq-ficha-datos dt { color: var(--gray-500); }
        .tq-ficha-datos dd { margin: 0; text-align: right; font-weight: 500; font-variant-numeric: tabular-nums; overflow-wrap: anywhere; }
        .tq-ficha-atascada dd { color: var(--warning-700); font-weight: 600; }
        .dark .tq-ficha-atascada dd { color: var(--warning-400); }
        .tq-ficha-seccion h3 { margin: 0 0 0.375rem; font-size: 0.8125rem; font-weight: 600; }
        .tq-ficha-requisitos { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 0.375rem; }
        .tq-ficha-requisitos li { display: flex; align-items: center; gap: 0.5rem; padding: 0.4375rem 0.5625rem; border-radius: 8px; box-shadow: 0 0 0 1px rgb(9 9 11 / 0.08); font-size: 0.8125rem; }
        .dark .tq-ficha-requisitos li { box-shadow: 0 0 0 1px rgb(255 255 255 / 0.1); }
        .tq-ficha-requisitos .fi-icon { flex: none; }
        .tq-ficha-requisitos span:nth-child(2) { flex: 1; }
        .tq-ficha-cumple { color: var(--success-600); font-weight: 600; }
        .tq-ficha-falta { color: var(--warning-700); font-weight: 600; }
        .dark .tq-ficha-cumple { color: var(--success-400); }
        .dark .tq-ficha-falta { color: var(--warning-400); }
        .tq-ficha-nota { margin: 0; font-size: 0.75rem; color: var(--gray-500); }
        .tq-ficha-botones { display: flex; flex-direction: column; gap: 0.5rem; }
        .tq-ficha-botones .fi-btn { width: 100%; }
        .tq-ficha-anuncio { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0, 0, 0, 0); white-space: nowrap; border: 0; }

        /* Reducir movimiento (D11), una sola regla para la ficha y el aviso: aparecen y se van sin deslizarse. */
        @media (prefers-reduced-motion: reduce) {
            .tq-ficha-raiz *, .tq-ficha-raiz *::before, .tq-ficha-raiz *::after { animation: none !important; transition: none !important; }
        }
        html.tq-sin-animaciones .tq-ficha-raiz *, html.tq-sin-animaciones .tq-ficha-raiz *::before, html.tq-sin-animaciones .tq-ficha-raiz *::after { animation: none !important; transition: none !important; }
    </style>

    <div class="tq-ficha-anuncio" role="status" aria-live="polite" aria-atomic="true" x-text="anuncio" data-anuncio-movimiento></div>
    <p class="tq-ficha-aviso" aria-hidden="true" x-cloak x-show="aviso !== null" x-bind:data-tono="aviso?.tono" x-text="aviso?.texto" data-aviso-movimiento></p>

    @if ($ficha !== null)
        <aside
            class="tq-ficha"
            wire:key="ficha-{{ $ficha['id'] }}"
            data-ficha="{{ $ficha['id'] }}"
            data-etapa-ficha="{{ $ficha['etapa'] }}"
            aria-labelledby="tq-ficha-titulo"
            x-data="{
                saliendo: false,
                cerrar() {
                    if (this.saliendo) return
                    this.saliendo = true
                    setTimeout(() => $wire.cerrarFicha(), this.quieto() ? 0 : 190)
                },
            }"
            x-bind:class="{ 'tq-saliendo': saliendo }"
            x-init="$nextTick(() => $el.querySelector('#tq-ficha-titulo')?.focus())"
            x-on:keydown.escape.window="if (! $event.defaultPrevented && ! document.querySelector('.tq-tablero-fantasma') && ! $event.target.closest('input, textarea, select, [contenteditable]') && ! [...document.querySelectorAll('.fi-modal-open, [aria-modal=true], .fi-dropdown-panel')].some((capa) => capa.getClientRects().length)) cerrar()"
        >
            <header class="tq-ficha-cabeza">
                <div>
                    <div class="tq-ficha-cuenta">{{ $ficha['numero'] }} · {{ $ficha['cuenta'] }}</div>
                    <h2 id="tq-ficha-titulo" class="tq-ficha-titulo" tabindex="-1">{{ $ficha['nombre'] }}</h2>
                </div>
                <x-filament::icon-button icon="heroicon-o-x-mark" color="gray" label="Cerrar la ficha" x-on:click="cerrar()" />
            </header>

            <div class="tq-ficha-gema" data-tq-gema-anfitrion>
                <x-oportunidades::gema :talla="$ficha['talla']" :tamano="104" :semilla="$ficha['id']" />
                <p class="tq-ficha-estado" data-talla="{{ $ficha['talla']->value }}">{{ $ficha['estado'] }}</p>
            </div>

            <dl class="tq-ficha-datos">
                @foreach ($ficha['filas'] as $dato)
                    @php([$etiqueta, $valor] = $dato)
                    <div @class(['tq-ficha-atascada' => $etiqueta === 'Días en la etapa' && $ficha['atascada']])>
                        <dt>{{ $etiqueta }}</dt>
                        <dd>@isset($dato[2])<x-oportunidades::monto :corto="$valor" :completo="$dato[2]" />@else{{ $valor }}@endisset</dd>
                    </div>
                @endforeach
                @foreach ($ficha['cierre'] as [$etiqueta, $valor])
                    <div><dt>{{ $etiqueta }}</dt><dd>{{ $valor }}</dd></div>
                @endforeach
            </dl>

            @if ($ficha['siguiente'] !== null)
                <section class="tq-ficha-seccion" data-requisitos>
                    <h3>{{ $ficha['siguiente']['titulo'] }}</h3>
                    @if ($ficha['siguiente']['requisitos'] === [])
                        <p class="tq-ficha-nota">Por ahora no exige nada en particular.</p>
                    @else
                        <ul class="tq-ficha-requisitos">
                            @foreach ($ficha['siguiente']['requisitos'] as $requisito)
                                <li data-requisito="{{ $requisito['cumple'] ? 'cumple' : 'falta' }}">
                                    <x-filament::icon :icon="$requisito['cumple'] ? 'heroicon-m-check-circle' : 'heroicon-m-exclamation-circle'" @class(['h-5 w-5', 'tq-ficha-cumple' => $requisito['cumple'], 'tq-ficha-falta' => ! $requisito['cumple']]) />
                                    <span>{{ $requisito['texto'] }}</span>
                                    <span @class(['tq-ficha-cumple' => $requisito['cumple'], 'tq-ficha-falta' => ! $requisito['cumple']])>{{ $requisito['cumple'] ? 'Cumple' : 'Falta' }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </section>

                <div class="tq-ficha-botones">
                    @forelse ($ficha['siguiente']['botones'] as $boton)
                        <x-filament::button
                            :color="$boton['color']"
                            :outlined="$boton['color'] === 'danger'"
                            data-hacia="{{ $boton['hacia'] }}"
                            x-on:click="mover({ id: {{ $ficha['id'] }}, desde: {{ \Illuminate\Support\Js::from($ficha['etapa']) }}, hacia: {{ \Illuminate\Support\Js::from($boton['hacia']) }}, version: {{ (int) $version }} })"
                            x-bind:disabled="movimiento !== null"
                        >{{ $boton['etiqueta'] }}</x-filament::button>
                    @empty
                        <p class="tq-ficha-nota">No tienes permiso para mover esta oportunidad.</p>
                    @endforelse
                </div>
            @endif

            <x-filament::link :href="$ficha['url']" icon="heroicon-m-arrow-top-right-on-square" icon-position="after" data-ficha-completa>Abrir la ficha completa</x-filament::link>
        </aside>
    @endif
</div>
