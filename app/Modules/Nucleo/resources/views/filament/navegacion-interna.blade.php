{{--
    MV-044: al cambiar de pantalla sin recargar, lo que navega se queda quieto y solo se desliza la marca de lo
    elegido; el contenido nuevo aparece desvaneciéndose y subiendo unos px.
    - Barra de cajas de un proceso (se conserva con `x-persist`): la marca es un solo elemento que se desliza en
      horizontal (250 ms) hasta la caja de la pantalla actual, también con «atrás».
    Con «reducir movimiento» o «reducir animaciones», la marca salta y el contenido aparece de golpe. Se instala una sola
    vez (en el encabezado).
--}}
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

        document.addEventListener('livewire:navigated', () => {
            window.tqMarcarProceso(true)
            // El contenido nuevo aparece desvaneciéndose y subiendo unos px; la barra y la frase no se tocan.
            if (! sinMovimiento()) {
                document.querySelectorAll('.fi-page-content, .fi-header').forEach((el) => el.animate([{ opacity: 0, transform: 'translateY(6px)' }, { opacity: 1, transform: 'none' }], { duration: 200, easing: 'cubic-bezier(.2, .7, .2, 1)' }))
            }
        })
        document.addEventListener('DOMContentLoaded', () => window.tqMarcarProceso(false))
        window.addEventListener('resize', () => window.tqMarcarProceso(false))
    })()
</script>
