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
            let elegida = null, largo = 0, exacta = false
            barra.querySelectorAll('[data-caja]').forEach((caja) => {
                JSON.parse(caja.dataset.rutas || '[]').forEach((r) => {
                    const prefijo = sinBarra(r)
                    if (ruta.startsWith(prefijo) && prefijo.length > largo) { elegida = caja; largo = prefijo.length; exacta = ruta === prefijo }
                })
            })
            barra.querySelectorAll('[data-caja]').forEach((caja) => caja === elegida ? caja.setAttribute('aria-current', 'page') : caja.removeAttribute('aria-current'))
            // MV-046: en la pantalla de una caja (no en una ficha), sin título grande.
            exacta ? (document.documentElement.dataset.tqEnCaja = '1') : delete document.documentElement.dataset.tqEnCaja
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
    })()
</script>
