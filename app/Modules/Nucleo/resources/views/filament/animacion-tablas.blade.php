{{--
    MV-041: al filtrar u ordenar una tabla o lista (pestaña, filtro, buscador, orden), las filas se mueven: las que siguen
    se deslizan de su lugar anterior al nuevo, las que salen se desvanecen y se recogen, y las que entran suben un poco,
    escalonadas 40 ms. Unos 300 ms, suave al final. Sin animación con «reducir movimiento» o «reducir animaciones».
    Vale para toda fila de Filament (`.fi-ta-row`, `.fi-ta-record`) y para cualquier lista que marque sus filas con
    `data-tq-fila` y una clave (`wire:key` o `data-tq-fila="…"`). Se instala una sola vez (en el encabezado).
--}}
<script>
    (() => {
        if (window.tqAnimacionFilas) return
        window.tqAnimacionFilas = true

        const FILAS = 'tr.fi-ta-row, .fi-ta-record, [data-tq-fila]'
        const DURACION = 300
        const sinMovimiento = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches || document.documentElement.classList.contains('tq-sin-animaciones')
        const clave = (el) => el.getAttribute('wire:key') || el.getAttribute('data-tq-fila') || el.querySelector('[wire\\:key]')?.getAttribute('wire:key') || null

        // Antes de que Livewire cambie la pantalla: dónde estaba cada fila (y una copia, por si sale).
        const capturar = (raiz) => {
            const filas = new Map()
            raiz.querySelectorAll(FILAS).forEach((el) => {
                const k = clave(el)
                if (k) filas.set(k, { rect: el.getBoundingClientRect(), el })
            })
            return filas
        }

        const animar = (raiz, antes) => {
            const ahora = new Map()
            raiz.querySelectorAll(FILAS).forEach((el) => { const k = clave(el); if (k) ahora.set(k, el) })
            let entrada = 0
            ahora.forEach((el, k) => {
                const previa = antes.get(k)
                if (previa) {
                    const r = el.getBoundingClientRect(), dx = previa.rect.left - r.left, dy = previa.rect.top - r.top
                    if (dx || dy) el.animate([{ transform: `translate(${dx}px, ${dy}px)` }, { transform: 'none' }], { duration: DURACION, easing: 'cubic-bezier(.2, .7, .2, 1)' })
                } else {
                    el.animate([{ opacity: 0, transform: 'translateY(8px)' }, { opacity: 1, transform: 'none' }], { duration: DURACION - 60, delay: Math.min(entrada++, 8) * 40, easing: 'cubic-bezier(.2, .7, .2, 1)', fill: 'backwards' })
                }
            })
            // Las que salieron: una copia en su lugar que se desvanece y se recoge hacia arriba.
            antes.forEach((previa, k) => {
                if (ahora.has(k) || previa.rect.height === 0) return
                const copia = previa.el.cloneNode(true)
                const tabla = previa.el.closest('table')
                const caja = document.createElement(tabla ? 'table' : 'div')
                if (tabla) { caja.className = tabla.className; const cuerpo = document.createElement('tbody'); cuerpo.appendChild(copia); caja.appendChild(cuerpo) } else { caja.appendChild(copia) }
                Object.assign(caja.style, { position: 'fixed', left: previa.rect.left + 'px', top: previa.rect.top + 'px', width: previa.rect.width + 'px', margin: 0, pointerEvents: 'none', zIndex: 5, transformOrigin: 'top' })
                caja.setAttribute('aria-hidden', 'true')
                document.body.appendChild(caja)
                caja.animate([{ opacity: 1, transform: 'none' }, { opacity: 0, transform: 'translateY(-6px) scaleY(.6)' }], { duration: DURACION - 80, easing: 'ease-in' }).finished.finally(() => caja.remove())
            })
        }

        document.addEventListener('livewire:init', () => {
            Livewire.hook('commit', ({ component, succeed }) => {
                if (sinMovimiento() || ! component.el.querySelector(FILAS)) return
                const antes = capturar(component.el)
                succeed(() => requestAnimationFrame(() => animar(component.el, antes)))
            })
        })
    })()
</script>
