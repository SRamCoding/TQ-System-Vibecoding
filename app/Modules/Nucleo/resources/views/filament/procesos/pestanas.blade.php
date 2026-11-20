{{--
    MV-035 (reemplaza las pestañas con miga de MV-020): arriba de cada pantalla de un proceso, centrados, la frase del
    proceso en el color de su franja y sus cajas internas juntas como un solo cuerpo: la navegación del proceso. La caja
    de la pantalla actual va marcada; las demás llevan a su pantalla y la barra se queda en su lugar (transición de vista
    entre páginas). Las cajas se reordenan arrastrando o con Alt + ← / → y el orden se guarda por persona; «Volver al
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
        arrastrada: null,
        guardar() {
            fetch(@js('/procesos/'.$proceso->value.'/orden'), { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': @js(csrf_token()), 'Accept': 'application/json' }, body: JSON.stringify({ claves: this.claves }) })
                .then(() => { this.$refs.original && (this.$refs.original.hidden = false) })
        },
        mover(clave, hacia) {
            const i = this.claves.indexOf(clave), j = i + hacia
            if (i < 0 || j < 0 || j >= this.claves.length) return
            this.claves.splice(j, 0, this.claves.splice(i, 1)[0])
            this.ordenarDom()
            this.guardar()
            this.$nextTick(() => this.$root.querySelector(`[data-caja='${clave}']`)?.focus())
        },
        soltar(sobre) {
            if (! this.arrastrada || this.arrastrada === sobre) return
            this.mover(this.arrastrada, this.claves.indexOf(sobre) - this.claves.indexOf(this.arrastrada))
            this.arrastrada = null
        },
        ordenarDom() {
            const cuerpo = this.$refs.cuerpo
            this.claves.forEach((c) => cuerpo.appendChild(cuerpo.querySelector(`[data-caja='${c}']`)))
        },
        original() {
            fetch(@js('/procesos/'.$proceso->value.'/orden'), { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': @js(csrf_token()), 'Accept': 'application/json' }, body: JSON.stringify({ claves: [] }) })
                .then(() => window.location.reload())
        },
    }">
    <style>
        @view-transition { navigation: auto; }
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
        .tq-proceso-frase { margin: 0; font-size: 1.75rem; line-height: 1.2; font-weight: 800; text-align: center; color: var(--tq-f-texto); }
        @media (max-width: 640px) { .tq-proceso-frase { font-size: 1.35rem; } }
        /* Las cajas juntas, como un solo cuerpo: un borde, un fondo y separadores finos. */
        .tq-proceso-cuerpo { display: flex; flex-wrap: wrap; justify-content: center; max-width: 100%; border-radius: 1rem; overflow: hidden; background: white; box-shadow: 0 1px 3px rgb(0 0 0 / 0.08), 0 0 0 1px var(--gray-200); }
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
    <p class="tq-proceso-frase">{{ $frase }}</p>
    <div class="tq-proceso-cuerpo" x-ref="cuerpo" role="list">
        @foreach ($datos['internas'] as $interna)
            <a href="{{ $interna->url }}" class="tq-proceso-caja" role="listitem" data-caja="{{ $interna->clave }}"
                @if ($interna->clave === $datos['actual']->clave) aria-current="page" @endif
                draggable="true"
                x-on:dragstart="arrastrada = @js($interna->clave); $el.classList.add('tq-arrastrando')"
                x-on:dragend="$el.classList.remove('tq-arrastrando')"
                x-on:dragover.prevent
                x-on:drop.prevent="soltar(@js($interna->clave))"
                x-on:keydown.alt.arrow-left.prevent="mover(@js($interna->clave), -1)"
                x-on:keydown.alt.arrow-right.prevent="mover(@js($interna->clave), 1)"
                title="{{ $interna->descripcion }} · Arrastra o usa Alt + ← / → para cambiarla de lugar">
                <x-filament::icon :icon="$interna->icono" class="tq-proceso-icono" />
                <span class="tq-proceso-nombre">{{ $interna->nombre }}</span>
            </a>
        @endforeach
    </div>
    <button type="button" class="tq-proceso-original" x-ref="original" @unless ($personalizado) hidden @endunless x-on:click="original()">Volver al orden original</button>
</nav>
@endif
