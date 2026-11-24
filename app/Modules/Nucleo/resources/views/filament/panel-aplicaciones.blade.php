{{--
    El panel de aplicaciones (plan 1.4p), abajo a la derecha de todas las pantallas, sobre el lugar de la burbuja del chat
    (1.14), alineado a su borde derecho y sin taparla (D1). Tres estados (D2): muelle (tus 3 principales, plan 1.4x),
    cuadrícula (todas, con buscador) y cerrado (botón redondo del tamaño del chat). «—» baja un escalón: de la cuadrícula al
    muelle y del muelle al botón. En el celular, sin muelle: el botón abre la
    cuadrícula a pantalla completa (D8). La cuadrícula flota encima del contenido (D4, excepción anotada en
    `docs/convenciones/interfaz.md`). Con «reducir movimiento» o «reducir animaciones», sin animación (D9).

    El estado cambia en el navegador al instante y se guarda en la sesión sin volver a dibujar (`guardarEstado`). Lo
    demás pasa por las acciones registradas, en PanelAplicaciones.
--}}
@php
    use App\Modules\Nucleo\Enums\IconoAplicacion;

    // El ícono original de Microsoft (paso 5) o, el genérico, la inicial del nombre.
    // Plan 1.4z, D9: el que subió administración, si lo hay.
    $icono = fn ($aplicacion): \Illuminate\Support\HtmlString => new \Illuminate\Support\HtmlString(($aplicacion->imagen ?? $aplicacion->icono->imagen()) !== null
        ? '<img class="tq-apps-ico tq-apps-ico-imagen" draggable="false" src="'.e($aplicacion->imagen ?? $aplicacion->icono->imagen()).'" alt="" data-icono="'.$aplicacion->icono->value.'">'
        : '<span class="tq-apps-ico" data-icono="generica" aria-hidden="true">'.e(mb_strtoupper(mb_substr($aplicacion->nombre, 0, 1))).'</span>');
    $iconos = collect(IconoAplicacion::paraPropias())->mapWithKeys(fn (IconoAplicacion $icono): array => [$icono->value => $icono->etiqueta()])->all();
@endphp
<div>
    <style>
        /* El lugar de la burbuja del chat (1.14): todo el panel se alinea a él. La burbuja usará estas mismas medidas. */
        :root { --tq-chat-derecha: 1.5rem; --tq-chat-abajo: 1.5rem; --tq-chat-tamano: 3rem; --tq-apps-sobre-chat: calc(var(--tq-chat-abajo) + var(--tq-chat-tamano) + 0.75rem); }
        /* MV-062: el muelle, por defecto, un poco más arriba que antes: nunca encima del asistente (que ocupa hasta 110 px
           desde abajo). */
        .tq-apps-muelle { bottom: 7.75rem !important; }
        /* MV-062: el muelle se arrastra desde cualquier parte que no sea un ícono (o su agarre) y se pega al borde más
           cercano; en los bordes de arriba y abajo se pone horizontal. Su lugar va en variables de <html> (Livewire no las
           pisa al redibujar el panel). */
        .tq-muelle-agarre { width: 1.6rem; height: 0.6rem; display: grid; place-items: center; cursor: grab; touch-action: none; color: var(--tq-apps-suave); }
        .tq-muelle-agarre::before { content: ''; width: 1.1rem; height: 3px; border-radius: 9999px; background: currentColor; opacity: .55; box-shadow: 0 -4px 0 color-mix(in oklab, currentColor 55%, transparent); }
        html.tq-muelle-movido .tq-apps-muelle { left: var(--tq-muelle-left) !important; top: var(--tq-muelle-top) !important; right: auto !important; bottom: auto !important; }
        html.tq-muelle-suave .tq-apps-muelle { transition: left 240ms cubic-bezier(.2, .7, .2, 1), top 240ms cubic-bezier(.2, .7, .2, 1); }
        html.tq-muelle-arrastrando, html.tq-muelle-arrastrando * { cursor: grabbing !important; user-select: none !important; }
        html.tq-muelle-horizontal .tq-apps-muelle { width: auto; height: 3.6rem; grid-auto-flow: column; align-items: center; padding: 0 0.6rem; }
        html.tq-muelle-horizontal .tq-apps-muelle hr { width: 0; height: 60%; border-top: 0; border-left: 1px solid var(--tq-apps-linea); }
        html.tq-muelle-horizontal .tq-muelle-agarre { width: 0.6rem; height: 1.6rem; }
        html.tq-muelle-horizontal .tq-muelle-agarre::before { width: 3px; height: 1.1rem; box-shadow: -4px 0 0 color-mix(in oklab, currentColor 55%, transparent); }
        /* Los nombres de las aplicaciones salen hacia el lado con más espacio. */
        html.tq-muelle-izquierda .tq-apps-muelle a::after { right: auto; left: calc(100% + 0.6rem); }
        html.tq-muelle-arriba .tq-apps-muelle a::after { right: auto; left: 50%; top: calc(100% + 0.6rem); transform: translateX(-50%); }
        html.tq-muelle-abajo .tq-apps-muelle a::after { right: auto; left: 50%; top: auto; bottom: calc(100% + 0.6rem); transform: translateX(-50%); }
        /* Lo que se abre desde el muelle (la cuadrícula), del lado con más espacio. */
        html.tq-muelle-izquierda .tq-apps-cuadricula { right: auto !important; left: calc(var(--tq-muelle-left) + 4.6rem); }
        .tq-apps { --tq-apps-fondo: #FFFFFF; --tq-apps-linea: var(--gray-200); --tq-apps-suave: var(--gray-500); --tq-apps-hover: var(--gray-100); --tq-apps-sombra: 0 12px 36px rgb(1 0 111 / 0.16), 0 2px 6px rgb(1 0 111 / 0.08); color: var(--gray-950); }
        .dark .tq-apps { --tq-apps-fondo: var(--gray-900); --tq-apps-linea: var(--gray-700); --tq-apps-suave: var(--gray-400); --tq-apps-hover: var(--gray-800); --tq-apps-sombra: 0 12px 36px rgb(0 0 0 / 0.5); color: var(--gray-50); }
        .tq-apps-flota { position: fixed; right: var(--tq-chat-derecha); bottom: var(--tq-apps-sobre-chat); z-index: 30; background: var(--tq-apps-fondo); border: 1px solid var(--tq-apps-linea); box-shadow: var(--tq-apps-sombra); }
        .tq-apps-boton { width: var(--tq-chat-tamano); height: var(--tq-chat-tamano); border-radius: 9999px; display: grid; place-items: center; color: var(--primary-600); cursor: pointer; }
        .tq-apps-puntos { display: grid; grid-template-columns: repeat(3, 5px); gap: 3px; }
        .tq-apps-puntos i { width: 5px; height: 5px; border-radius: 1.5px; background: currentColor; }
        .tq-apps-muelle { width: 3.6rem; border-radius: 0.9rem; display: grid; justify-items: center; gap: 0.55rem; padding: 0.6rem 0; }
        .tq-apps-muelle hr { width: 60%; border: 0; border-top: 1px solid var(--tq-apps-linea); margin: 0; }
        .tq-apps-control { width: 1.6rem; height: 1.6rem; border-radius: 0.4rem; display: grid; place-items: center; color: var(--tq-apps-suave); cursor: pointer; }
        .tq-apps-control:hover { background: var(--tq-apps-hover); color: inherit; }
        /* MV-055: la agenda, un ícono de aplicación de 32 px; en modo claro, la hoja con un borde fino gris cálido. */
        .tq-apps-agenda { display: grid; place-items: center; width: 2.25rem; height: 2.25rem; border-radius: 0.55rem; cursor: pointer; }
        .tq-apps-agenda:hover { background: var(--tq-apps-hover); }
        .tq-apps-agenda:focus-visible { outline: 2px solid var(--primary-600); outline-offset: 2px; }
        html:not(.dark) .tq-icono-agenda-hoja { stroke: #D9D4C9; stroke-width: 1; x: 3.5px; y: 3.5px; width: 41px; height: 41px; rx: 9.5px; }
        .tq-apps-control:focus-visible, .tq-apps a:focus-visible, .tq-apps-boton:focus-visible { outline: 2px solid var(--primary-600); outline-offset: 2px; }
        /* D1: de 3 a 3,5 de cada 10 partes del ancho; de alto, de bajo la barra superior hasta encima del chat. */
        .tq-apps-cuadricula { top: 5rem; width: 30vw; min-width: 17rem; max-width: 35vw; border-radius: 0.9rem; display: flex; flex-direction: column; overflow: hidden; }
        .tq-apps-cabecera { display: flex; align-items: center; justify-content: space-between; padding: 0.75rem 0.9rem 0.5rem; }
        .tq-apps-cabecera b { font-size: 0.95rem; }
        .tq-apps-cabecera span { display: flex; gap: 0.25rem; }
        .tq-apps-buscar { margin: 0 0.9rem 0.5rem; height: 2rem; border: 1px solid var(--tq-apps-linea); border-radius: 0.45rem; padding: 0 0.6rem; font-size: 0.85rem; background: transparent; color: inherit; }
        .tq-apps-cuerpo { padding: 0 0.9rem 0.9rem; overflow-y: auto; display: grid; gap: 0.5rem; align-content: start; }
        .tq-apps-etiqueta { font-size: 0.65rem; letter-spacing: 0.1em; text-transform: uppercase; color: var(--tq-apps-suave); margin-top: 0.25rem; }
        .tq-apps-rejilla { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 0.5rem; }
        .tq-apps-app { position: relative; display: grid; justify-items: center; gap: 0.3rem; padding: 0.5rem 0.15rem; border-radius: 0.55rem; font-size: 0.72rem; text-align: center; overflow-wrap: anywhere; }
        .tq-apps-app:hover { background: var(--tq-apps-hover); }
        .tq-apps-app button { position: absolute; top: 0.1rem; width: 1.35rem; height: 1.35rem; border-radius: 9999px; display: grid; place-items: center; background: var(--tq-apps-fondo); border: 1px solid var(--tq-apps-linea); color: var(--tq-apps-suave); cursor: pointer; }
        .tq-apps-app button:hover { color: inherit; }
        .tq-apps-ico { width: 2.2rem; height: 2.2rem; border-radius: 0.5rem; display: grid; place-items: center; color: #FFFFFF; font-weight: 700; font-size: 0.9rem; flex: none; background: var(--gray-500); }
        /* Los originales de Microsoft traen su propio dibujo: sin fondo ni recorte. */
        .tq-apps-ico.tq-apps-ico-imagen { background: none; border-radius: 0; object-fit: contain; }
        .tq-apps-ico.tq-apps-mas { background: transparent; color: var(--tq-apps-suave); border: 1.5px dashed var(--tq-apps-linea); }
        /* El nombre al pasar el mouse por el muelle (D2). */
        .tq-apps-muelle a { position: relative; }
        /* Plan 1.4v: al pasar el mouse, el ícono crece como el de las cajas del inicio; quieto con el movimiento reducido. */
        .tq-apps-muelle a .tq-apps-ico { transition: transform 180ms ease-out; }
        .tq-apps-muelle a:is(:hover, :focus-visible) .tq-apps-ico { transform: scale(1.2); }
        @media (prefers-reduced-motion: reduce) { .tq-apps-muelle a .tq-apps-ico { transition: none; transform: none !important; } }
        html.tq-sin-animaciones .tq-apps-muelle a .tq-apps-ico { transition: none; transform: none !important; }
        .tq-apps-muelle a::after { content: attr(data-nombre); position: absolute; right: calc(100% + 0.6rem); top: 50%; transform: translateY(-50%); background: var(--gray-950); color: #FFFFFF; font-size: 0.72rem; padding: 0.2rem 0.45rem; border-radius: 0.3rem; white-space: nowrap; opacity: 0; pointer-events: none; transition: opacity 0.15s; }
        .dark .tq-apps-muelle a::after { background: var(--gray-50); color: var(--gray-950); }
        .tq-apps-muelle a:hover::after, .tq-apps-muelle a:focus-visible::after { opacity: 1; }
        .tq-apps-formulario { display: grid; gap: 0.5rem; padding: 0.75rem; border: 1px solid var(--tq-apps-linea); border-radius: 0.6rem; }
        .tq-apps-formulario label { display: grid; gap: 0.2rem; font-size: 0.78rem; }
        .tq-apps-formulario input, .tq-apps-formulario select { height: 2rem; border: 1px solid var(--tq-apps-linea); border-radius: 0.4rem; padding: 0 0.5rem; background: transparent; color: inherit; font-size: 0.85rem; }
        .tq-apps-formulario .tq-apps-error { color: var(--danger-600); font-size: 0.78rem; }
        .tq-apps-formulario div { display: flex; gap: 0.5rem; justify-content: flex-end; }
        .tq-apps-formulario button { padding: 0.3rem 0.7rem; border-radius: 0.4rem; font-size: 0.8rem; cursor: pointer; border: 1px solid var(--tq-apps-linea); }
        .tq-apps-formulario button[type="submit"] { background: var(--primary-600); border-color: var(--primary-600); color: #FFFFFF; }
        .tq-apps-vacio { font-size: 0.8rem; color: var(--tq-apps-suave); }
        /* Plan 1.4x, D2: se arrastran sobre uno de los 3 lugares de arriba. Plan 1.4aa, D1: con Pointer Events, igual con
           mouse y con el dedo (sin `touch-action` el navegador se queda el gesto para desplazar). */
        [data-tq-arrastra] { touch-action: none; user-select: none; -webkit-user-select: none; -webkit-touch-callout: none; }
        .tq-apps-app[data-tq-arrastra] { cursor: grab; }
        .tq-apps-moviendo, .tq-apps-moviendo * { cursor: grabbing !important; }
        /* La copia que sigue al puntero. */
        .tq-apps-clon { position: fixed; margin: 0; z-index: 60; pointer-events: none; display: grid; justify-items: center; align-content: center; gap: 0.3rem; background: var(--tq-apps-fondo); border-radius: 0.55rem; box-shadow: var(--tq-apps-sombra); opacity: 0.95; }
        .tq-apps-muelle a.tq-apps-sobre .tq-apps-ico { outline: 2px dashed var(--primary-600); outline-offset: 3px; border-radius: 0.4rem; }
        .tq-apps-lugar { border-radius: 0.55rem; border: 1.5px dashed transparent; min-height: 4.2rem; }
        .tq-apps-arrastrando .tq-apps-lugar { border-color: var(--tq-apps-linea); }
        .tq-apps-lugar.tq-apps-sobre { border-color: var(--primary-600); background: var(--tq-apps-hover); }
        .tq-apps-app.tq-apps-sobre { outline: 1.5px dashed var(--primary-600); background: var(--tq-apps-hover); }
        .tq-apps-pista { font-size: 0.72rem; color: var(--tq-apps-suave); margin: -0.2rem 0 0; }
        /* Plan 1.4z, D1: el enlace es una columna centrada; ícono y nombre comparten el centro (con `display: contents` el
           ícono se corría a la izquierda, más cuanto más largo el nombre). */
        .tq-apps-enlace { display: flex; flex-direction: column; align-items: center; gap: 0.3rem; width: 100%; }
        /* D8: en el celular, la cuadrícula ocupa la pantalla. */
        @media (max-width: 767px) {
            .tq-apps-cuadricula { inset: 0; width: auto; max-width: none; min-width: 0; border-radius: 0; z-index: 40; }
        }
        @media (prefers-reduced-motion: no-preference) {
            html:not(.tq-sin-animaciones) .tq-apps-entra { transition: opacity 0.18s ease-out, transform 0.18s ease-out; }
        }
        .tq-apps-desde { opacity: 0; transform: translateY(0.5rem); }
    </style>

    {{-- Plan 1.4z: Livewire no toca los atributos de este contenedor al redibujar (solo su contenido). Si no, un cambio en
         el `x-data` (el estado que viene de la sesión) reiniciaba el panel y se perdía el modo edición tras cada acción. --}}
    <div
        wire:ignore.self
        class="tq-apps"
        x-data="{
            estado: @js($estado),
            abierto: @js($abierto),
            celular: window.matchMedia('(max-width: 767px)').matches,
            busqueda: '',
            editando: false,
            formulario: null,
            error: null,
            ocupado: false,
            arrastrando: null,
            sobre: null,
            init() {
                const medida = window.matchMedia('(max-width: 767px)')
                medida.addEventListener('change', (evento) => this.celular = evento.matches)
                // Plan 1.4z, D3: si se abre algo encima (una ventana o panel de Filament, un menú, los resultados de la
                // búsqueda), la cuadrícula vuelve al muelle y lo otro sigue abierto. Filament avisa sus ventanas con
                // `x-modal-opened`; los menús y la búsqueda no avisan: se ven por el clic y al escribir.
                document.addEventListener('x-modal-opened', () => this.hacerseAUnLado())
                document.addEventListener('click', (evento) => { if (evento.target.closest?.('.fi-dropdown-trigger')) this.hacerseAUnLado() }, true)
                document.addEventListener('input', (evento) => { if (evento.target.closest?.('.fi-global-search')) this.hacerseAUnLado() }, true)
            },
            hacerseAUnLado() { if (this.estado === 'cuadricula') this.aminorar() },
            get verMuelle() { return this.estado === 'muelle' && ! this.celular },
            get verCuadricula() { return this.estado === 'cuadricula' },
            get verBoton() { return this.estado === 'cerrado' || (this.estado === 'muelle' && this.celular) },
            poner(estado) {
                if (estado !== 'cerrado') this.abierto = estado
                this.estado = estado
                if (estado !== 'cuadricula') { this.editando = false; this.formulario = null; this.busqueda = '' }
                $wire.guardarEstado(this.estado, this.abierto)
            },
            abrirPanel() { this.poner(this.celular ? 'cuadricula' : this.abierto) },
            cerrar() { this.poner('cerrado') },
            aminorar() { this.poner(this.celular ? 'cerrado' : 'muelle') },
            sinAcentos(texto) { return texto.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase() },
            coincide(nombre) { return this.busqueda.trim() === '' || this.sinAcentos(nombre).includes(this.sinAcentos(this.busqueda.trim())) },
            nueva() { this.error = null; this.formulario = { id: null, nombre: '', enlace: 'https://', icono: 'generica', escritorio: '', version: null } },
            // Plan 1.4z, D8: el recorte de pantalla de Windows solo tiene sentido en Windows.
            windows: /Windows/i.test(navigator.userAgentData?.platform ?? navigator.userAgent),
            editar(datos) { this.error = null; this.formulario = { ...datos } },
            async responder(promesa) {
                this.ocupado = true
                this.error = await promesa
                this.ocupado = false
                if (this.error === null) this.formulario = null
            },
            guardar() {
                const f = this.formulario
                this.responder(f.id === null ? $wire.agregar(f.nombre, f.enlace, f.icono, f.escritorio) : $wire.cambiar(f.id, f.nombre, f.enlace, f.icono, f.version, f.escritorio))
            },
            // Plan 1.4aa, D1: se arrastra con Pointer Events, igual con mouse y con el dedo. Sin espera: el arrastre empieza al
            // moverse UMBRAL_PX desde que se presiona; si no hubo movimiento, es un clic y abre la app como siempre. Mientras
            // se arrastra, una copia sigue al puntero y la que cedería su lugar se corre en vivo (FLIP, con `animate`: Livewire
            // no borra una animación al redibujar, sí un `style`). Al soltar, la copia se acomoda y recién ahí va la acción.
            UMBRAL_PX: 5,
            DURACION_MS: 150,
            MENSAJE_SECCION: 'Cada una se mueve dentro de su sección; para subirla, suéltala en «Tus 3 principales».',
            arrastre: null,
            sinClicHasta: 0,
            sinMovimiento() { return window.matchMedia('(prefers-reduced-motion: reduce)').matches || document.documentElement.classList.contains('tq-sin-animaciones') },
            duracion() { return this.sinMovimiento() ? 0 : this.DURACION_MS },
            presionar(evento, origen, id, zona) {
                if (this.arrastre !== null || (evento.pointerType === 'mouse' && evento.button !== 0) || evento.target.closest('button')) return
                const mover = (e) => this.moverPuntero(e)
                const terminar = (e) => this.soltarPuntero(e, e.type === 'pointercancel')
                window.addEventListener('pointermove', mover)
                window.addEventListener('pointerup', terminar)
                window.addEventListener('pointercancel', terminar)
                this.arrastre = {
                    origen, id, zona, el: evento.currentTarget, puntero: evento.pointerId, x: evento.clientX, y: evento.clientY,
                    activo: false, destino: null, corrido: null,
                    quitar: () => { window.removeEventListener('pointermove', mover); window.removeEventListener('pointerup', terminar); window.removeEventListener('pointercancel', terminar) },
                }
            },
            moverPuntero(evento) {
                const a = this.arrastre
                if (a === null || evento.pointerId !== a.puntero) return
                const dx = evento.clientX - a.x
                const dy = evento.clientY - a.y
                if (! a.activo) {
                    if (Math.hypot(dx, dy) < this.UMBRAL_PX) return
                    this.empezarArrastre(a)
                }
                evento.preventDefault()
                a.clon.style.transform = `translate(${dx}px, ${dy}px)`
                this.verDestino(a, this.destinoEn(a, evento.clientX, evento.clientY))
            },
            empezarArrastre(a) {
                a.activo = true
                try { a.el.setPointerCapture(a.puntero) } catch (e) {}
                a.caja = a.el.getBoundingClientRect()
                const contenedor = a.el.closest('[data-tq-apps=muelle], [data-tq-apps=cuadricula]')
                a.destinos = Array.from(contenedor.querySelectorAll('[data-tq-destino]')).map((d) => {
                    const item = d.matches('[data-tq-arrastra]') ? d : d.querySelector('[data-tq-arrastra]')
                    return { zona: d.dataset.tqDestino, valor: Number(d.dataset.tqValor), caja: d.getBoundingClientRect(), item, cajaItem: item?.getBoundingClientRect() ?? null }
                }).filter((d) => d.caja.width > 0)
                const clon = a.el.cloneNode(true)
                clon.querySelectorAll('button').forEach((boton) => boton.remove())
                for (const nodo of [clon, ...clon.querySelectorAll('*')]) {
                    for (const atributo of Array.from(nodo.attributes)) {
                        if (/^(x-|wire:|@|:|data-tq-|aria-|href|target|id$)/.test(atributo.name)) nodo.removeAttribute(atributo.name)
                    }
                }
                clon.classList.add('tq-apps-clon')
                clon.setAttribute('x-ignore', '')
                clon.setAttribute('aria-hidden', 'true')
                Object.assign(clon.style, { left: a.caja.left + 'px', top: a.caja.top + 'px', width: a.caja.width + 'px', height: a.caja.height + 'px' })
                this.$root.querySelector('[data-tq-apps=capa]').appendChild(clon)
                a.clon = clon
                a.oculto = a.el.animate([{ opacity: 0 }, { opacity: 0 }], { duration: 1, fill: 'forwards' })
                this.arrastrando = { origen: a.origen, id: a.id, principal: a.zona === 'muelle' || a.zona === 'principales' }
                document.documentElement.classList.add('tq-apps-moviendo')
            },
            // El lugar bajo el puntero, con las medidas del inicio (las que se corren no cambian el blanco). Unos px de
            // margen para que el hueco entre dos íconos no deshaga lo que se corrió.
            destinoEn(a, x, y) {
                return a.destinos.find((d) => x >= d.caja.left - 6 && x <= d.caja.right + 6 && y >= d.caja.top - 6 && y <= d.caja.bottom + 6) ?? null
            },
            // Qué haría soltarla ahí: subirla a un lugar (muelle o «Tus 3 principales»), cambiarla con otra de su sección
            // o el aviso de que cada una se mueve dentro de su sección.
            regla(a, d) {
                if (d.zona === 'muelle' || d.zona === 'principales') return { lugar: d.valor, intercambio: a.zona === d.zona }
                if (a.zona === 'muelle' || a.zona === 'principales' || a.zona !== d.zona) return { error: this.MENSAJE_SECCION }
                return { sobreId: d.valor, intercambio: true }
            },
            deslizar(el, dx, dy) {
                const desde = el.tqAnimacion ? getComputedStyle(el).transform : 'none'
                el.tqAnimacion?.cancel()
                el.tqAnimacion = el.animate([{ transform: desde }, { transform: dx === 0 && dy === 0 ? 'none' : `translate(${dx}px, ${dy}px)` }], { duration: this.duracion(), easing: 'ease-out', fill: 'forwards' })
                return el.tqAnimacion.finished.catch(() => null)
            },
            verDestino(a, d) {
                if (d === a.destino) return
                if (a.corrido !== null) this.deslizar(a.corrido, 0, 0)
                a.corrido = null
                a.destino = d
                this.sobre = null
                if (d === null || d.item === a.el) return
                const regla = this.regla(a, d)
                if (regla.error) return
                if (regla.intercambio && d.item !== null) {
                    this.deslizar(d.item, a.caja.left - d.cajaItem.left, a.caja.top - d.cajaItem.top)
                    a.corrido = d.item
                } else {
                    this.sobre = d.zona + '-' + d.valor
                }
            },
            moverClon(a, caja) {
                const desde = a.clon.style.transform || 'none'
                const hasta = `translate(${caja.left - a.caja.left}px, ${caja.top - a.caja.top}px)`
                a.clon.style.transform = hasta
                return a.clon.animate([{ transform: desde }, { transform: hasta }], { duration: this.duracion(), easing: 'ease-out' }).finished.catch(() => null)
            },
            async soltarPuntero(evento, cancelado) {
                const a = this.arrastre
                if (a === null || evento.pointerId !== a.puntero) return
                a.quitar()
                if (! a.activo) {
                    this.arrastre = null
                    return
                }
                // El clic que sigue a un arrastre no abre la app.
                this.sinClicHasta = Date.now() + 400
                this.sobre = null
                const d = cancelado ? null : a.destino
                const regla = d !== null && d.item !== a.el ? this.regla(a, d) : null
                if (regla === null || regla.error) {
                    if (regla?.error) this.error = regla.error
                    return this.devolver(a)
                }
                await this.moverClon(a, d.cajaItem ?? d.caja)
                const error = regla.lugar ? await $wire.ponerPrincipal(regla.lugar, a.origen, a.id) : await $wire.mover(a.origen, a.id, regla.sobreId)
                if (error) {
                    this.error = error
                    return this.devolver(a)
                }
                this.error = null
                this.terminarArrastre(a)
            },
            // Vuelve todo a como estaba, deslizándose (o de golpe, con «reducir animaciones»).
            async devolver(a) {
                await Promise.all([this.moverClon(a, a.caja), a.corrido !== null ? this.deslizar(a.corrido, 0, 0) : null])
                this.terminarArrastre(a)
            },
            terminarArrastre(a) {
                for (const d of a.destinos ?? []) { d.item?.tqAnimacion?.cancel(); if (d.item) d.item.tqAnimacion = null }
                a.oculto?.cancel()
                a.clon?.remove()
                try { a.el.releasePointerCapture(a.puntero) } catch (e) {}
                document.documentElement.classList.remove('tq-apps-moviendo')
                this.arrastrando = null
                this.sobre = null
                this.arrastre = null
            },
            async ponerArriba(origen, id, lugar) { this.error = await $wire.ponerPrincipal(lugar, origen, id) },
            async quitar(id, version, nombre) {
                if (! confirm(`¿Quitar «${nombre}» de tus aplicaciones?`)) return
                this.error = await $wire.quitar(id, version)
            },
        }"
        x-on:keydown.escape.window="if (verCuadricula) formulario ? formulario = null : aminorar()"
        x-on:click.capture="if (Date.now() < sinClicHasta) { $event.preventDefault(); $event.stopPropagation() }"
    >
        {{-- Plan 1.4aa, D1: aquí va la copia que sigue al puntero; Livewire no la toca al redibujar. --}}
        <div wire:ignore x-ignore data-tq-apps="capa"></div>

        {{-- Cerrado (o el celular): el botón redondo. Al presionarlo vuelve al último estado abierto (D2, D8). --}}
        <button
            type="button"
            class="tq-apps-flota tq-apps-boton"
            x-show="verBoton"
            x-cloak
            x-on:click="abrirPanel()"
            aria-label="Abrir aplicaciones"
            title="Aplicaciones"
            data-tq-apps="boton"
        >
            <span class="tq-apps-puntos" aria-hidden="true">@for ($i = 0; $i < 9; $i++)<i></i>@endfor</span>
        </button>

        {{-- Muelle: tus 3 principales (plan 1.4x, D1), con el nombre al pasar el mouse (D2). --}}
        <nav
            class="tq-apps-flota tq-apps-muelle tq-apps-entra"
            x-show="verMuelle"
            x-cloak
            x-transition:enter-start="tq-apps-desde"
            x-transition:leave-end="tq-apps-desde"
            aria-label="Tus 3 aplicaciones principales"
            data-tq-apps="muelle"
        >
            {{-- MV-062: el agarre para moverlo; doble clic lo devuelve a su lugar de siempre. --}}
            <span class="tq-muelle-agarre" data-tq-muelle-agarre title="Arrastra para moverlo · doble clic: a su lugar" aria-hidden="true"></span>
            {{-- Expandir arriba y aminorar abajo (pedido del desarrollador, 2026-10-28). --}}
            <button type="button" class="tq-apps-control" x-on:click="poner('cuadricula')" aria-label="Ver todas" title="Ver todas">
                <x-filament::icon icon="heroicon-m-arrows-pointing-out" class="h-4 w-4" />
            </button>
            <hr>
            {{-- Plan 1.4aa, D1: cada una se arrastra (sin espera) sobre otra y cambian de lugar; las demás se corren en vivo. --}}
            @foreach ($muelle as $aplicacion)
                <a
                    href="{{ $aplicacion->destino() }}"
                    @unless ($aplicacion->enEscritorio) target="_blank" @endunless
                    rel="noopener noreferrer"
                    draggable="false"
                    data-nombre="{{ $aplicacion->nombre }}"
                    data-tq-lugar-muelle="{{ $loop->iteration }}"
                    data-tq-arrastra
                    data-tq-destino="muelle"
                    data-tq-valor="{{ $loop->iteration }}"
                    aria-label="{{ $aplicacion->nombre }} {{ $aplicacion->enEscritorio ? '(se abre en el programa de escritorio)' : '(se abre en otra pestaña)' }}"
                    x-on:click="$wire.abrir(@js($aplicacion->origen->value), {{ $aplicacion->id }})"
                    x-on:pointerdown="presionar($event, @js($aplicacion->origen->value), {{ $aplicacion->id }}, 'muelle')"
                    x-on:dragstart.prevent
                    x-bind:class="sobre === 'muelle-{{ $loop->iteration }}' && 'tq-apps-sobre'"
                >
                    {{ $icono($aplicacion) }}
                </a>
            @endforeach
            <hr>
            {{-- MV-025: la agenda, arriba de Recortes. La pone el módulo de actividades (si la persona la ve, el ícono aparece). --}}
            {{-- MV-055: estilo aplicación, la hoja del día, a 32 px como las demás aplicaciones. --}}
            <button type="button" class="tq-apps-agenda" x-data x-init="Alpine.store('tqAgenda') ?? Alpine.store('tqAgenda', { lista: false })" x-show="$store.tqAgenda?.lista" x-cloak
                x-on:click="window.dispatchEvent(new CustomEvent('tq-agenda-abrir'))" data-nombre="Agenda" aria-label="Agenda" title="Agenda" data-tq-apps="agenda">
                @include('nucleo::filament.icono-agenda', ['tamano' => 32])
            </button>
            {{-- Plan 1.4z, D8: recortar pantalla, fijo; no es una aplicación (no se arrastra ni se oculta). Solo en Windows. --}}
            <a href="ms-screenclip:" class="tq-apps-control" x-show="windows" x-cloak data-nombre="Recortar pantalla (Windows + Mayús + S)" aria-label="Recortar pantalla (Windows + Mayús + S)" data-tq-apps="recortar">
                <x-filament::icon icon="heroicon-m-scissors" class="h-4 w-4" />
            </a>
            <button type="button" class="tq-apps-control" x-on:click="cerrar()" aria-label="Aminorar" title="Aminorar">
                <x-filament::icon icon="heroicon-m-minus" class="h-4 w-4" />
            </button>
        </nav>

        {{-- Cuadrícula: todas, con buscador. Arriba tus 3 principales (se arrastra una sobre un lugar); luego las de
             Techquk y abajo las propias con «+ Agregar» (D2, D7; plan 1.4x). --}}
        <section
            class="tq-apps-flota tq-apps-cuadricula tq-apps-entra"
            x-show="verCuadricula"
            x-cloak
            x-transition:enter-start="tq-apps-desde"
            x-transition:leave-end="tq-apps-desde"
            role="dialog"
            aria-label="Aplicaciones"
            data-tq-apps="cuadricula"
            x-bind:class="arrastrando !== null && 'tq-apps-arrastrando'"
        >
            <header class="tq-apps-cabecera">
                <b>Aplicaciones</b>
                <span>
                    <button type="button" class="tq-apps-control" x-on:click="editando = ! editando; formulario = null" x-bind:aria-pressed="editando" aria-label="Elegir cuáles ver" title="Elegir cuáles ver">
                        <x-filament::icon icon="heroicon-m-cog-6-tooth" class="h-4 w-4" />
                    </button>
                    {{-- «—» pasa al muelle con tus 3 principales; en el celular, que no tiene muelle, al botón (pedido del
                         desarrollador, 2026-10-28). --}}
                    <button type="button" class="tq-apps-control" x-on:click="aminorar()" aria-label="Aminorar" title="Aminorar">
                        <x-filament::icon icon="heroicon-m-minus" class="h-4 w-4" />
                    </button>
                </span>
            </header>
            <input type="search" class="tq-apps-buscar" x-model="busqueda" placeholder="Buscar aplicación" aria-label="Buscar aplicación">

            <div class="tq-apps-cuerpo">
                <template x-if="formulario !== null">
                <form class="tq-apps-formulario" x-on:submit.prevent="guardar()">
                    <b x-text="formulario.id === null ? 'Agregar una aplicación' : 'Cambiar la aplicación'"></b>
                    <label>Nombre <input type="text" maxlength="40" required x-model="formulario.nombre" x-init="$nextTick(() => $el.focus())"></label>
                    <label>Enlace <input type="url" maxlength="300" required x-model="formulario.enlace" placeholder="https://…"></label>
                    <label>Ícono
                        <select x-model="formulario.icono">
                            @foreach ($iconos as $valor => $etiqueta)
                                <option value="{{ $valor }}">{{ $etiqueta }}</option>
                            @endforeach
                        </select>
                    </label>
                    {{-- Plan 1.4z, D6: de la lista cerrada, nunca un enlace escrito a mano. --}}
                    <label>Programa de escritorio
                        <select x-model="formulario.escritorio">
                            <option value="">Ninguno (solo web)</option>
                            @foreach (\App\Modules\Nucleo\Enums\ProgramaEscritorio::cases() as $programa)
                                <option value="{{ $programa->value }}">{{ $programa->etiqueta() }}</option>
                            @endforeach
                        </select>
                    </label>
                    <p class="tq-apps-error" x-show="error" x-text="error" role="alert"></p>
                    <div>
                        <button type="button" x-on:click="formulario = null">Cancelar</button>
                        <button type="submit" x-bind:disabled="ocupado">Guardar</button>
                    </div>
                </form>
                </template>
                <p class="tq-apps-error" x-show="error && formulario === null" x-text="error" role="alert" style="color: var(--danger-600); font-size: 0.78rem;"></p>

                <div class="tq-apps-etiqueta">Tus 3 principales</div>
                <p class="tq-apps-pista">Las de tu muelle. Arrastra aquí la que quieras tener a la mano.</p>
                <div class="tq-apps-rejilla" data-tq-apps="principales">
                    @foreach (range(1, \App\Modules\Nucleo\Services\AplicacionesService::DEL_MUELLE) as $lugar)
                        <div
                            class="tq-apps-lugar"
                            x-bind:class="sobre === 'principales-{{ $lugar }}' && 'tq-apps-sobre'"
                            data-tq-lugar="{{ $lugar }}"
                            data-tq-destino="principales"
                            data-tq-valor="{{ $lugar }}"
                        >
                            @isset ($muelle[$lugar - 1])
                                @include('nucleo::filament.panel-aplicaciones-app', ['aplicacion' => $muelle[$lugar - 1], 'principal' => true, 'icono' => $icono])
                            @endisset
                        </div>
                    @endforeach
                </div>

                <div class="tq-apps-etiqueta">Más aplicaciones</div>
                <div class="tq-apps-rejilla">
                    @foreach ($cuadricula['techquk'] as $aplicacion)
                        @include('nucleo::filament.panel-aplicaciones-app', ['aplicacion' => $aplicacion, 'principal' => false, 'icono' => $icono])
                    @endforeach
                    @if ($cuadricula['techquk'] === [] && $muelle === [])
                        <p class="tq-apps-vacio">Ninguna a la vista.</p>
                    @endif
                </div>

                @if ($ocultas !== [])
                    <div class="tq-apps-etiqueta" x-show="editando" x-cloak>Ocultas</div>
                    <div class="tq-apps-rejilla" x-show="editando" x-cloak>
                        @foreach ($ocultas as $aplicacion)
                            <div class="tq-apps-app" style="opacity: 0.6" wire:key="oculta-{{ $aplicacion->id }}">
                                {{ $icono($aplicacion) }}
                                <span>{{ $aplicacion->nombre }}</span>
                                <button type="button" style="right: 0.1rem" x-on:click="error = await $wire.mostrar({{ $aplicacion->id }})" aria-label="Mostrar {{ $aplicacion->nombre }}" title="Mostrar">
                                    <x-filament::icon icon="heroicon-m-eye" class="h-3 w-3" />
                                </button>
                            </div>
                        @endforeach
                    </div>
                @endif

                <div class="tq-apps-etiqueta">Mías</div>
                <div class="tq-apps-rejilla">
                    @foreach ($cuadricula['propias'] as $aplicacion)
                        @include('nucleo::filament.panel-aplicaciones-app', ['aplicacion' => $aplicacion, 'principal' => false, 'icono' => $icono])
                    @endforeach
                    <button type="button" class="tq-apps-app" x-show="busqueda.trim() === ''" x-on:click="nueva()" style="cursor: pointer">
                        <span class="tq-apps-ico tq-apps-mas" aria-hidden="true">+</span>
                        <span>Agregar</span>
                    </button>
                </div>
            </div>
        </section>
    </div>
    <script>
        // MV-062: mover el muelle y pegarlo al borde más cercano; se recuerda en este navegador.
        (() => {
            if (window.tqMuelleMovible) return
            window.tqMuelleMovible = true
            const CLAVE = 'tq-muelle-lugar', raiz = document.documentElement
            const muelle = () => document.querySelector('[data-tq-apps="muelle"]')
            const leer = () => { try { return JSON.parse(localStorage.getItem(CLAVE)) } catch (e) { return null } }
            const limites = () => {
                const barra = document.querySelector('.fi-topbar')?.getBoundingClientRect().bottom ?? 64
                const lateral = document.querySelector('.fi-main-sidebar')
                const izq = lateral && lateral.getBoundingClientRect().width > 0 && innerWidth >= 1024 ? lateral.getBoundingClientRect().right : 0
                return { arriba: barra + 8, izq: izq + 12, der: innerWidth - 12, abajo: innerHeight - 12 }
            }
            const aplicar = (suave = false) => {
                const lugar = leer(), m = muelle()
                ;['izquierda', 'derecha', 'arriba', 'abajo'].forEach((b) => raiz.classList.remove('tq-muelle-' + b))
                raiz.classList.remove('tq-muelle-horizontal')
                if (! lugar || ! m) { raiz.classList.remove('tq-muelle-movido'); return }
                raiz.classList.add('tq-muelle-' + lugar.borde)
                raiz.classList.toggle('tq-muelle-horizontal', lugar.borde === 'arriba' || lugar.borde === 'abajo')
                raiz.classList.toggle('tq-muelle-suave', suave)
                const l = limites(), w = m.offsetWidth, h = m.offsetHeight, cl = (v, a, b) => Math.max(a, Math.min(b, v))
                let x, y
                if (lugar.borde === 'derecha' || lugar.borde === 'izquierda') {
                    x = lugar.borde === 'derecha' ? l.der - w : l.izq
                    y = cl(lugar.t * innerHeight - h / 2, l.arriba, l.abajo - h)
                } else {
                    y = lugar.borde === 'arriba' ? l.arriba : l.abajo - h
                    x = cl(lugar.t * innerWidth - w / 2, l.izq, l.der - w)
                }
                raiz.style.setProperty('--tq-muelle-left', Math.round(x) + 'px')
                raiz.style.setProperty('--tq-muelle-top', Math.round(y) + 'px')
                raiz.classList.add('tq-muelle-movido')
                if (suave) setTimeout(() => raiz.classList.remove('tq-muelle-suave'), 300)
            }
            let arrastre = null
            document.addEventListener('pointerdown', (e) => {
                const m = e.target.closest?.('[data-tq-apps="muelle"]')
                if (! m || e.button !== 0 || (e.target.closest('a, button') && ! e.target.closest('[data-tq-muelle-agarre]'))) return
                const r = m.getBoundingClientRect()
                arrastre = { dx: e.clientX - r.left, dy: e.clientY - r.top, x0: e.clientX, y0: e.clientY, activo: false }
            })
            addEventListener('pointermove', (e) => {
                if (! arrastre) return
                if (! arrastre.activo && Math.hypot(e.clientX - arrastre.x0, e.clientY - arrastre.y0) < 5) return
                arrastre.activo = true
                raiz.classList.add('tq-muelle-movido', 'tq-muelle-arrastrando')
                raiz.style.setProperty('--tq-muelle-left', (e.clientX - arrastre.dx) + 'px')
                raiz.style.setProperty('--tq-muelle-top', (e.clientY - arrastre.dy) + 'px')
            })
            addEventListener('pointerup', (e) => {
                if (! arrastre) return
                const activo = arrastre.activo
                arrastre = null
                raiz.classList.remove('tq-muelle-arrastrando')
                if (! activo) return
                const m = muelle(), r = m.getBoundingClientRect(), cx = r.left + r.width / 2, cy = r.top + r.height / 2, l = limites()
                // Al borde más cercano: derecho, izquierdo, arriba o abajo.
                const d = { derecha: l.der - cx, izquierda: cx - l.izq, arriba: cy - l.arriba, abajo: l.abajo - cy }
                const borde = Object.entries(d).sort((a, b) => a[1] - b[1])[0][0]
                const t = borde === 'derecha' || borde === 'izquierda' ? cy / innerHeight : cx / innerWidth
                try { localStorage.setItem(CLAVE, JSON.stringify({ borde, t })) } catch (err) {}
                aplicar(! matchMedia('(prefers-reduced-motion: reduce)').matches)
            })
            document.addEventListener('dblclick', (e) => {
                if (! e.target.closest?.('[data-tq-apps="muelle"]') || (e.target.closest('a, button') && ! e.target.closest('[data-tq-muelle-agarre]'))) return
                try { localStorage.removeItem(CLAVE) } catch (err) {}
                aplicar()
            })
            addEventListener('resize', () => aplicar())
            // Observación 8.13: si la persona lo movió en otra pestaña, aquí también.
            addEventListener('storage', (e) => { if (e.key === CLAVE) aplicar(true) })
            document.addEventListener('livewire:navigated', () => requestAnimationFrame(() => aplicar()))
            const empezar = () => requestAnimationFrame(() => aplicar())
            document.readyState === 'loading' ? document.addEventListener('DOMContentLoaded', empezar) : empezar()
            // El muelle aparece después (Alpine): se vuelve a ubicar cuando ya se ve.
            setTimeout(() => aplicar(), 600)
        })()
    </script>
</div>
