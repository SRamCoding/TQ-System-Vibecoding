{{--
    MV-035 (reemplaza las pestañas con miga de MV-020): arriba de cada pantalla de un proceso, centrados, la frase del
    proceso en el color de su franja y sus cajas internas juntas como un solo cuerpo: la navegación del proceso. La caja
    de la pantalla actual va marcada; las demás llevan a su pantalla y la barra se queda en su lugar (transición de vista
    entre páginas). Las cajas se reordenan arrastrando (como en el muelle: una copia sigue al puntero y las demás se corren en vivo) o
    con Alt + ← / →, y el orden se guarda por persona; «Volver al
    orden original» lo deshace.
--}}
@php
    /** @var array{proceso: \App\Modules\Nucleo\Navegacion\Proceso, caja: \App\Modules\Nucleo\Navegacion\CajaModulo, internas: list<\App\Modules\Nucleo\Navegacion\CajaModulo>, actual: \App\Modules\Nucleo\Navegacion\CajaModulo} $datos */
    $proceso = $datos['proceso'];
    $frase = rescue(fn (): string => app(\App\Modules\Nucleo\Services\ParametrosService::class)->texto($proceso->parametroFrase()), $proceso->frase(), false);
    $personalizado = auth()->user() !== null && app(\App\Modules\Nucleo\Services\NavegacionService::class)->ordenGuardado(auth()->user(), $proceso) !== null;
    $C = \App\Modules\Nucleo\Navegacion\ColoresMarca::class;
@endphp
@if ($proceso !== \App\Modules\Nucleo\Navegacion\Proceso::Mien)
<nav class="tq-proceso" data-area="{{ $datos['caja']->area->value }}" aria-label="{{ $proceso->nombre() }}"
    x-data="{
        claves: @js(array_map(fn ($c) => $c->clave, $datos['internas'])),
        arrastre: null,
        sinClicHasta: 0,
        sinMovimiento() { return window.matchMedia('(prefers-reduced-motion: reduce)').matches || document.documentElement.classList.contains('tq-sin-animaciones') },
        guardar() {
            fetch(@js('/procesos/'.$proceso->value.'/orden'), { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': @js(csrf_token()), 'Accept': 'application/json' }, body: JSON.stringify({ claves: this.claves }) })
                .then(() => { this.$refs.original && (this.$refs.original.hidden = false) })
        },
        cajas() { return this.claves.map((c) => this.$refs.cuerpo.querySelector(`[data-caja='${c}']`)) },
        // Con el teclado: Alt + flechas la corren un lugar, con el mismo deslizamiento.
        mover(clave, hacia) {
            const i = this.claves.indexOf(clave), j = i + hacia
            if (i < 0 || j < 0 || j >= this.claves.length) return
            const antes = new Map(this.cajas().map((el) => [el, el.getBoundingClientRect()]))
            this.claves.splice(j, 0, this.claves.splice(i, 1)[0])
            this.ordenarDom()
            this.animarDesde(antes)
            this.guardar()
            this.$nextTick(() => this.$root.querySelector(`[data-caja='${clave}']`)?.focus())
        },
        animarDesde(antes) {
            if (this.sinMovimiento()) return
            for (const [el, r] of antes) {
                const n = el.getBoundingClientRect()
                el.animate([{ transform: `translate(${r.left - n.left}px, ${r.top - n.top}px)` }, { transform: 'none' }], { duration: 220, easing: 'ease-out' })
            }
        },
        // Con el puntero (mouse o dedo), como en el muelle: una copia sigue al puntero y las demás se corren en vivo.
        presionar(e, clave) {
            if (this.arrastre !== null || (e.pointerType === 'mouse' && e.button !== 0)) return
            const mover = (ev) => this.moverPuntero(ev), soltar = (ev) => this.soltarPuntero(ev, ev.type === 'pointercancel')
            window.addEventListener('pointermove', mover); window.addEventListener('pointerup', soltar); window.addEventListener('pointercancel', soltar)
            this.arrastre = { clave, el: e.currentTarget, puntero: e.pointerId, x: e.clientX, y: e.clientY, activo: false, destino: null,
                quitar: () => { window.removeEventListener('pointermove', mover); window.removeEventListener('pointerup', soltar); window.removeEventListener('pointercancel', soltar) } }
        },
        moverPuntero(e) {
            const a = this.arrastre
            if (a === null || e.pointerId !== a.puntero) return
            const dx = e.clientX - a.x, dy = e.clientY - a.y
            if (! a.activo) {
                if (Math.hypot(dx, dy) < 6) return
                this.empezar(a)
            }
            e.preventDefault()
            a.clon.style.transform = `translate(${dx}px, ${dy}px) scale(1.04)`
            // El lugar más cercano al puntero.
            let mejor = a.destino ?? a.origen, distancia = Infinity
            a.lugares.forEach((r, i) => { const d = Math.hypot(e.clientX - (r.left + r.width / 2), e.clientY - (r.top + r.height / 2)); if (d < distancia) { distancia = d; mejor = i } })
            if (mejor !== a.destino) { a.destino = mejor; this.correr(a) }
        },
        empezar(a) {
            a.activo = true
            try { a.el.setPointerCapture(a.puntero) } catch (err) {}
            a.els = this.cajas()
            a.lugares = a.els.map((el) => el.getBoundingClientRect())
            a.origen = this.claves.indexOf(a.clave)
            a.destino = a.origen
            const r = a.lugares[a.origen], clon = a.el.cloneNode(true)
            for (const nodo of [clon, ...clon.querySelectorAll('*')]) for (const at of Array.from(nodo.attributes)) if (/^(x-|wire:|@|:|aria-|href|data-caja|id$)/.test(at.name)) nodo.removeAttribute(at.name)
            Object.assign(clon.style, { position: 'fixed', left: r.left + 'px', top: r.top + 'px', width: r.width + 'px', height: r.height + 'px', margin: 0, zIndex: 60, pointerEvents: 'none', borderRadius: '0.75rem', background: 'var(--tq-proceso-fondo)', boxShadow: '0 12px 28px rgb(0 0 0 / 0.18)' })
            clon.setAttribute('aria-hidden', 'true')
            document.body.appendChild(clon)
            a.clon = clon
            a.el.style.opacity = '0'
            document.documentElement.classList.add('tq-proceso-moviendo')
        },
        // Cada caja se desliza al lugar que le toca si la arrastrada se soltara en el destino.
        correr(a) {
            const orden = [...a.els]
            orden.splice(a.destino, 0, orden.splice(a.origen, 1)[0])
            orden.forEach((el, i) => {
                const desde = a.els.indexOf(el), r0 = a.lugares[desde], r1 = a.lugares[i]
                const hasta = `translate(${r1.left - r0.left}px, ${r1.top - r0.top}px)`
                if (el === a.el) { el.style.transform = hasta; return }
                const actual = el.tqAnim ? getComputedStyle(el).transform : 'none'
                el.tqAnim?.cancel()
                el.tqAnim = el.animate([{ transform: actual }, { transform: hasta }], { duration: this.sinMovimiento() ? 0 : 200, easing: 'ease-out', fill: 'forwards' })
            })
        },
        async soltarPuntero(e, cancelado) {
            const a = this.arrastre
            if (a === null || e.pointerId !== a.puntero) return
            a.quitar()
            if (! a.activo) { this.arrastre = null; return }
            this.sinClicHasta = Date.now() + 400
            const destino = cancelado ? a.origen : a.destino
            if (cancelado) { a.destino = a.origen; this.correr(a) }
            const r0 = a.lugares[a.origen], r1 = a.lugares[destino]
            const fin = `translate(${r1.left - r0.left}px, ${r1.top - r0.top}px)`
            if (! this.sinMovimiento()) await a.clon.animate([{ transform: a.clon.style.transform }, { transform: fin }], { duration: 180, easing: 'ease-out' }).finished.catch(() => null)
            for (const el of a.els) { el.tqAnim?.cancel(); el.tqAnim = null; el.style.transform = '' }
            a.el.style.opacity = ''
            a.clon.remove()
            try { a.el.releasePointerCapture(a.puntero) } catch (err) {}
            document.documentElement.classList.remove('tq-proceso-moviendo')
            this.arrastre = null
            if (destino !== a.origen) {
                this.claves.splice(destino, 0, this.claves.splice(a.origen, 1)[0])
                this.ordenarDom()
                this.guardar()
            }
        },
        ordenarDom() {
            const cuerpo = this.$refs.cuerpo
            this.claves.forEach((c) => cuerpo.appendChild(cuerpo.querySelector(`[data-caja='${c}']`)))
        },
        original() {
            fetch(@js('/procesos/'.$proceso->value.'/orden'), { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': @js(csrf_token()), 'Accept': 'application/json' }, body: JSON.stringify({ claves: [] }) })
                .then(() => Livewire.navigate(window.location.href))
        },
    }">
    <style>
        .tq-proceso { display: flex; flex-direction: column; align-items: center; gap: 1rem; margin-bottom: 1.25rem; view-transition-name: tq-proceso; }
        .tq-proceso[data-area="personal"] { --tq-f: var(--dorado-600); --tq-f-texto: var(--dorado-600); }
        .tq-proceso[data-area="nucleo"] { --tq-f: {{ $C::AZUL_NOCHE }}; --tq-f-texto: {{ $C::AZUL_NOCHE }}; }
        .tq-proceso[data-area="crm"] { --tq-f: var(--morado-600); --tq-f-texto: var(--morado-700); }
        .tq-proceso[data-area="erp"] { --tq-f: {{ $C::TURQUESA_LOGO_CLARO }}; --tq-f-texto: {{ $C::TURQUESA_TEXTO }}; }
        .tq-proceso[data-area="plataforma"] { --tq-f: {{ $C::GRIS_PLATAFORMA }}; --tq-f-texto: {{ $C::GRIS_PLATAFORMA }}; }
        .dark .tq-proceso[data-area="personal"] { --tq-f: var(--dorado-400); --tq-f-texto: var(--dorado-400); }
        .dark .tq-proceso[data-area="nucleo"] { --tq-f: {{ $C::AZUL_NOCHE_OSCURO }}; --tq-f-texto: {{ $C::AZUL_NOCHE_OSCURO }}; }
        .dark .tq-proceso[data-area="crm"] { --tq-f: {{ $C::LILA_OSCURO }}; --tq-f-texto: {{ $C::LILA_OSCURO }}; }
        .dark .tq-proceso[data-area="erp"] { --tq-f: {{ $C::TURQUESA }}; --tq-f-texto: {{ $C::TURQUESA }}; }
        .dark .tq-proceso[data-area="plataforma"] { --tq-f: {{ $C::GRIS_PLATAFORMA_OSCURO }}; --tq-f-texto: {{ $C::GRIS_PLATAFORMA_OSCURO }}; }
        .tq-proceso-frase { margin: -0.25rem 0 0; font-size: 0.95rem; line-height: 1.3; font-weight: 500; font-style: italic; text-align: center; color: color-mix(in oklab, var(--tq-f-texto) 55%, var(--gray-500)); }
        .dark .tq-proceso-frase { color: color-mix(in oklab, var(--tq-f-texto) 55%, var(--gray-400)); }
        /* Las cajas juntas, como un solo cuerpo: un borde, un fondo y separadores finos. */
        .tq-proceso { --tq-proceso-fondo: white; }
        .dark .tq-proceso { --tq-proceso-fondo: var(--gray-900); }
        .tq-proceso-caja { touch-action: none; user-select: none; -webkit-user-select: none; -webkit-touch-callout: none; }
        .tq-proceso-moviendo, .tq-proceso-moviendo * { cursor: grabbing !important; }
        .tq-proceso-cuerpo { display: flex; flex-wrap: wrap; justify-content: center; max-width: 100%; border-radius: 1rem; overflow: hidden; background: var(--tq-proceso-fondo); box-shadow: 0 1px 3px rgb(0 0 0 / 0.08), 0 0 0 1px var(--gray-200); }
        .dark .tq-proceso-cuerpo { background: var(--gray-900); box-shadow: 0 0 0 1px var(--gray-700); }
        .tq-proceso-caja { position: relative; width: 9rem; height: 7.5rem; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 0.5rem; padding: 0.75rem; text-align: center; color: var(--gray-700); box-shadow: 1px 0 0 var(--gray-200), 0 1px 0 var(--gray-200); transition: background 150ms ease; cursor: pointer; }
        .dark .tq-proceso-caja { color: var(--gray-200); box-shadow: 1px 0 0 var(--gray-700), 0 1px 0 var(--gray-700); }
        .tq-proceso-caja:hover { background: color-mix(in oklab, var(--tq-f) 8%, transparent); }
        .tq-proceso-caja:focus-visible { outline: 2px solid var(--tq-f); outline-offset: -2px; }
        .tq-proceso-caja .tq-proceso-icono { width: 2rem; height: 2rem; color: var(--tq-f); }
        .tq-proceso-nombre { display: -webkit-box; -webkit-box-orient: vertical; -webkit-line-clamp: 2; line-clamp: 2; overflow: hidden; font-size: 0.875rem; font-weight: 600; line-height: 1.25rem; }
        /* La caja elegida, marcada con el color de su franja. */
        .tq-proceso-caja[aria-current="page"] { background: color-mix(in oklab, var(--tq-f) 14%, transparent); color: var(--tq-f-texto); }
        .tq-proceso-caja[aria-current="page"]::after { content: ''; position: absolute; left: 0; right: 0; bottom: 0; height: 4px; background: var(--tq-f); }
        .tq-proceso-caja.tq-arrastrando { opacity: 0.4; }
        .tq-proceso-original { font-size: 0.8rem; color: var(--gray-500); text-decoration: underline; }
        @media (prefers-reduced-motion: reduce) { .tq-proceso-caja { transition: none; } }
    </style>
    <div class="tq-proceso-cuerpo" x-ref="cuerpo" role="list">
        @foreach ($datos['internas'] as $interna)
            <a href="{{ $interna->url }}" wire:navigate class="tq-proceso-caja" role="listitem" data-caja="{{ $interna->clave }}"
                @if ($interna->clave === $datos['actual']->clave) aria-current="page" @endif
                draggable="false"
                x-on:dragstart.prevent
                x-on:pointerdown="presionar($event, @js($interna->clave))"
                x-on:click="if (Date.now() < sinClicHasta) $event.preventDefault()"
                x-on:keydown.alt.arrow-left.prevent="mover(@js($interna->clave), -1)"
                x-on:keydown.alt.arrow-right.prevent="mover(@js($interna->clave), 1)"
                title="{{ $interna->descripcion }} · Arrastra o usa Alt + ← / → para cambiarla de lugar">
                <x-filament::icon :icon="$interna->icono" class="tq-proceso-icono" />
                <span class="tq-proceso-nombre">{{ $interna->nombre }}</span>
            </a>
        @endforeach
    </div>
    {{-- La frase, debajo de las cajas y sutil (pedido de Sebastián, 2026-11-20). --}}
    <p class="tq-proceso-frase">{{ $frase }}</p>
    <button type="button" class="tq-proceso-original" x-ref="original" @unless ($personalizado) hidden @endunless x-on:click="original()">Volver al orden original</button>
</nav>
@endif
