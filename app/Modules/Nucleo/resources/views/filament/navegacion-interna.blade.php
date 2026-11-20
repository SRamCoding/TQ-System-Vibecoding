{{--
    MV-044 y MV-045: al cambiar de pantalla sin recargar, lo que navega se queda quieto y solo se desliza la marca de lo
    elegido; el contenido nuevo aparece desvaneciéndose y subiendo unos px.
    - Barra de cajas de un proceso (se conserva con `x-persist`): la marca es un solo elemento que se desliza en
      horizontal (250 ms) hasta la caja de la pantalla actual, también con «atrás».
    - Barras laterales internas (Configuración, Listas…): conservan su desplazamiento y la marca se desliza en vertical
      desde la opción anterior, en el azul de resaltado.
    Con «reducir movimiento» o «reducir animaciones», la marca salta y el contenido aparece de golpe. Se instala una sola
    vez (en el encabezado).
--}}
<style>
    /* MV-051: las barras laterales (principal e internas) siempre entran. Cada medida tiene su máximo (el de antes) y su
       mínimo, y entre los dos se ajusta al alto de la ventana: primero se quita el aire, después se achica el ícono
       (nunca un elemento de menos de 28 px de alto) y, expandida, un poco la letra. En el caso extremo se desplaza sin
       dibujar la barra de desplazamiento, con un degradado arriba o abajo que avisa que hay más. */
    .fi-main-sidebar .fi-sidebar-nav { padding-block: clamp(0.25rem, 1.6vh, 2rem); }
    .fi-main-sidebar .fi-sidebar-nav-groups { gap: clamp(0rem, 1.4vh, 1.75rem); }
    .fi-main-sidebar .fi-sidebar-group-btn { padding-block: clamp(0rem, 0.6vh, 0.5rem); }
    .fi-main-sidebar .fi-sidebar-group-label { font-size: clamp(0.65rem, 1.6vh, 0.75rem); }
    .fi-main-sidebar .fi-sidebar-group-items { gap: clamp(0rem, 0.4vh, 0.25rem); }
    .fi-main-sidebar .fi-sidebar-item-btn, .fi-page-sub-navigation-sidebar .fi-sidebar-item-btn { min-height: 28px; padding-block: clamp(0.1rem, 0.9vh, 0.5rem); }
    .fi-main-sidebar .fi-sidebar-item-icon { width: clamp(1.15rem, 3vh, 1.5rem); height: clamp(1.15rem, 3vh, 1.5rem); }
    .fi-main-sidebar .fi-sidebar-item-label, .fi-page-sub-navigation-sidebar .fi-sidebar-item-label { font-size: clamp(0.8rem, 2vh, 0.875rem); }
    .fi-page-sub-navigation-sidebar .fi-sidebar-group-items, .fi-page-sub-navigation-sidebar { gap: clamp(0rem, 0.4vh, 0.25rem); }
    /* La barra lateral interna, a la vista mientras se desplaza el contenido, y nunca más alta que la ventana. */
    @media (min-width: 1024px) { .fi-page-sub-navigation-sidebar { position: sticky; top: 4.5rem; max-height: calc(100vh - 5.5rem); overflow-y: auto; } }
    .fi-main-sidebar .fi-sidebar-nav, .fi-page-sub-navigation-sidebar { scrollbar-width: none; }
    .fi-main-sidebar .fi-sidebar-nav::-webkit-scrollbar, .fi-page-sub-navigation-sidebar::-webkit-scrollbar { display: none; }
    [data-tq-hay-mas="abajo"] { mask-image: linear-gradient(to bottom, #000 calc(100% - 2.5rem), transparent); }
    [data-tq-hay-mas="arriba"] { mask-image: linear-gradient(to top, #000 calc(100% - 2.5rem), transparent); }
    [data-tq-hay-mas="ambos"] { mask-image: linear-gradient(to bottom, transparent, #000 2.5rem, #000 calc(100% - 2.5rem), transparent); }

    /* MV-046: sin ruta (miga de pan) en ninguna pantalla; sin título grande en la pantalla de una caja de proceso (lo dicen
       la caja elegida y el nombre del módulo). */
    .fi-breadcrumbs { display: none !important; }
    /* MV-053: en la barra lateral, Techquk con el nombre del logo: TECHQUK en Audiowide (comprimida, solo el símbolo). */
    .fi-main-sidebar [data-modulo="techquk"] .fi-sidebar-item-label { font-family: 'Audiowide', ui-sans-serif, sans-serif; text-transform: uppercase; letter-spacing: 0.04em; }
    .fi-main-sidebar [data-modulo="techquk"] .fi-sidebar-item-icon { transform: scaleY(.85); }
    html[data-tq-en-caja] .fi-header-heading { display: none; }
    @font-face { font-family: 'Audiowide'; src: url('{{ asset('fonts/audiowide/audiowide-latin.woff2') }}') format('woff2'); font-display: swap; }
    /* La marca propia de las barras laterales internas reemplaza el fondo de la opción activa de Filament. */
    .fi-page-sub-navigation-sidebar { position: relative; }
    .fi-page-sub-navigation-sidebar .fi-sidebar-item { position: relative; z-index: 1; }
    .fi-page-sub-navigation-sidebar.tq-con-marca .fi-sidebar-item.fi-active > .fi-sidebar-item-btn { background: transparent !important; }
    .tq-subnav-marca { position: absolute; left: 0; right: 0; top: 0; z-index: 0; pointer-events: none; border-radius: 0.5rem;
        background: color-mix(in oklab, var(--primary-600) 9%, transparent); box-shadow: inset 3px 0 0 var(--primary-600);
        transition: transform 250ms cubic-bezier(.2, .7, .2, 1), height 250ms cubic-bezier(.2, .7, .2, 1); }
    .dark .tq-subnav-marca { background: color-mix(in oklab, var(--primary-400) 14%, transparent); box-shadow: inset 3px 0 0 var(--primary-400); }
    @media (prefers-reduced-motion: reduce) { .tq-subnav-marca { transition: none; } }
    html.tq-sin-animaciones .tq-subnav-marca { transition: none; }
</style>
<script>
    (() => {
        if (window.tqNavegacionInterna) return
        window.tqNavegacionInterna = true

        const sinMovimiento = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches || document.documentElement.classList.contains('tq-sin-animaciones')
        const sinBarra = (ruta) => '/' + ruta.replace(/^\/+|\/+$/g, '') + '/'

        // La barra de cajas del proceso: qué caja corresponde a la pantalla y la marca hasta ella.
        window.tqMarcarProceso = (animar = true) => {
            const barra = document.querySelector('.tq-proceso')
            if (! barra) { delete document.documentElement.dataset.tqEnCaja; return }
            const ruta = sinBarra(location.pathname)
            let elegida = null, largo = 0, exacta = false, prefijoElegido = '/'
            barra.querySelectorAll('[data-caja]').forEach((caja) => {
                JSON.parse(caja.dataset.rutas || '[]').forEach((r) => {
                    const prefijo = sinBarra(r)
                    if (ruta.startsWith(prefijo) && prefijo.length > largo) { elegida = caja; largo = prefijo.length; exacta = ruta === prefijo; prefijoElegido = prefijo }
                })
            })
            barra.querySelectorAll('[data-caja]').forEach((caja) => caja === elegida ? caja.setAttribute('aria-current', 'page') : caja.removeAttribute('aria-current'))
            // MV-046: en la pantalla de una caja (no en una ficha), sin título grande. MV-048: también en las opciones de
            // una barra lateral interna (Configuración, Listas…), que viven un nivel debajo de su caja.
            const conBarraInterna = !! document.querySelector('.fi-page-sub-navigation-sidebar, .fi-page-sub-navigation-select, .fi-page-sub-navigation-dropdown')
            const tramos = (r) => r.split('/').filter(Boolean).length
            const unNivel = elegida !== null && tramos(ruta) - tramos(prefijoElegido) <= 1
            exacta || (conBarraInterna && unNivel) ? (document.documentElement.dataset.tqEnCaja = '1') : delete document.documentElement.dataset.tqEnCaja
            const marca = barra.querySelector('.tq-proceso-marca')
            if (! marca || ! elegida) { marca?.classList.remove('tq-lista'); return }
            const quieta = ! animar || sinMovimiento() || ! marca.classList.contains('tq-lista')
            if (quieta) marca.style.transition = 'none'
            Object.assign(marca.style, { width: elegida.offsetWidth + 'px', height: elegida.offsetHeight + 'px', transform: `translate(${elegida.offsetLeft}px, ${elegida.offsetTop}px)` })
            marca.classList.add('tq-lista')
            if (quieta) { void marca.offsetWidth; marca.style.transition = '' }
        }

        // Las barras laterales internas: dónde estaba la marca antes de navegar.
        let subnavAntes = null
        const subnav = () => document.querySelector('.fi-page-sub-navigation-sidebar')
        const activa = (nav) => nav?.querySelector('.fi-sidebar-item.fi-active')
        const marcarSubnav = () => {
            const nav = subnav(), item = activa(nav)
            if (! nav || ! item) { subnavAntes = null; return }
            let marca = nav.querySelector(':scope > .tq-subnav-marca')
            if (! marca) { marca = document.createElement('span'); marca.className = 'tq-subnav-marca'; marca.setAttribute('aria-hidden', 'true'); nav.prepend(marca) }
            nav.classList.add('tq-con-marca')
            const destino = item.getBoundingClientRect().top - nav.getBoundingClientRect().top + nav.scrollTop
            const animar = subnavAntes !== null && ! sinMovimiento()
            if (subnavAntes !== null) nav.scrollTop = subnavAntes.scroll
            marca.style.transition = 'none'
            marca.style.height = (animar ? subnavAntes.alto : item.offsetHeight) + 'px'
            marca.style.transform = `translateY(${animar ? subnavAntes.top : destino}px)`
            void marca.offsetWidth
            marca.style.transition = ''
            if (animar) requestAnimationFrame(() => { marca.style.height = item.offsetHeight + 'px'; marca.style.transform = `translateY(${destino}px)` })
            subnavAntes = null
        }

        document.addEventListener('livewire:navigate', () => {
            const nav = subnav(), item = activa(nav)
            subnavAntes = nav && item ? { top: item.getBoundingClientRect().top - nav.getBoundingClientRect().top + nav.scrollTop, alto: item.offsetHeight, scroll: nav.scrollTop } : null
        })

        document.addEventListener('livewire:navigated', () => {
            window.tqMarcarProceso(true)
            marcarSubnav()
            // El contenido nuevo aparece desvaneciéndose y subiendo unos px; la barra y la frase no se tocan.
            if (! sinMovimiento()) {
                document.querySelectorAll('.fi-page-content, .fi-header').forEach((el) => el.animate([{ opacity: 0, transform: 'translateY(6px)' }, { opacity: 1, transform: 'none' }], { duration: 200, easing: 'cubic-bezier(.2, .7, .2, 1)' }))
            }
        })
        document.addEventListener('DOMContentLoaded', () => { window.tqMarcarProceso(false); marcarSubnav() })
        window.addEventListener('resize', () => window.tqMarcarProceso(false))

        // MV-052: la estrella de Mien de la barra lateral principal abre su globo; Ctrl, Cmd, Shift o la rueda abren Mien
        // en otra pestaña, como cualquier enlace.
        // Livewire empieza a navegar al presionar el botón (mousedown) y suelta al levantarlo, y con Enter: por eso se
        // detienen esos eventos además del clic, antes de que lleguen al enlace.
        const estrellaMien = (e) => e.target.closest?.('.fi-main-sidebar [data-modulo="mien"] a, .fi-main-sidebar a[data-modulo="mien"]')
        const conModificador = (e) => e.ctrlKey || e.metaKey || e.shiftKey || e.altKey || (e.button !== undefined && e.button !== 0)
        const abrirGlobo = (enlace) => window.dispatchEvent(new CustomEvent('tq-globo-mien', { detail: { rect: (enlace.querySelector('.fi-sidebar-item-icon') ?? enlace).getBoundingClientRect() } }))
        for (const tipo of ['mousedown', 'mouseup', 'pointerdown', 'pointerup']) {
            document.addEventListener(tipo, (e) => { if (estrellaMien(e) && ! conModificador(e)) e.stopImmediatePropagation() }, true)
        }
        document.addEventListener('click', (e) => {
            const enlace = estrellaMien(e)
            if (! enlace || conModificador(e)) return
            e.preventDefault()
            e.stopImmediatePropagation()
            abrirGlobo(enlace)
        }, true)
        document.addEventListener('keydown', (e) => {
            const enlace = estrellaMien(e)
            if (! enlace || e.key !== 'Enter' || conModificador(e)) return
            e.preventDefault()
            e.stopImmediatePropagation()
            abrirGlobo(enlace)
        }, true)

        // MV-051: el degradado que avisa que hay más, y el elemento enfocado con teclado siempre a la vista.
        const desplazables = () => document.querySelectorAll('.fi-main-sidebar .fi-sidebar-nav, .fi-page-sub-navigation-sidebar')
        const avisar = (el) => {
            const arriba = el.scrollTop > 2, abajo = el.scrollTop + el.clientHeight < el.scrollHeight - 2
            arriba && abajo ? (el.dataset.tqHayMas = 'ambos') : arriba ? (el.dataset.tqHayMas = 'arriba') : abajo ? (el.dataset.tqHayMas = 'abajo') : delete el.dataset.tqHayMas
        }
        const preparar = () => desplazables().forEach((el) => {
            avisar(el)
            if (el.tqPreparado) return
            el.tqPreparado = true
            el.addEventListener('scroll', () => avisar(el), { passive: true })
            el.addEventListener('focusin', (e) => e.target.scrollIntoView?.({ block: 'nearest' }))
        })
        document.addEventListener('DOMContentLoaded', preparar)
        document.addEventListener('livewire:navigated', () => requestAnimationFrame(preparar))
        window.addEventListener('resize', () => desplazables().forEach(avisar))
    })()
</script>
