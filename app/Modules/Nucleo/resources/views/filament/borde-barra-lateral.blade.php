{{--
    El borde de la barra lateral que se arrastra para cambiar su ancho (plan 1.3d, D6). Entre el mínimo y el máximo
    cambia el ancho; por debajo del umbral la barra se pliega a solo íconos (el plegado de Filament) y, desde ahí,
    arrastrar hacia afuera la vuelve a abrir. Se guarda al soltar (al cargar la página no se
    guarda nada: el estado inicial es el de la base). No hay botón de plegar: la flecha de Filament está escondida. Solo
    en pantallas anchas: en el celular la barra se abre encima y no tiene borde.
--}}
@php use App\Modules\Nucleo\Navegacion\BarraLateral; @endphp
<div
    x-data="{
        minimo: {{ BarraLateral::ANCHO_MINIMO }},
        maximo: {{ BarraLateral::ANCHO_MAXIMO }},
        umbral: {{ BarraLateral::UMBRAL_PLEGADO }},
        guardado: {{ $ancho }},
        arrastrando: false,
        iniciado: false,
        esEscritorio() { return window.innerWidth >= @js(\App\Modules\Nucleo\Navegacion\BarraLateral::CORTE_ESCRITORIO) },
        anchoActual() {
            return parseInt(getComputedStyle(document.documentElement).getPropertyValue('--sidebar-width')) || {{ BarraLateral::ANCHO_INICIAL }}
        },
        empezar(evento) {
            if (! this.esEscritorio()) return
            this.arrastrando = true
            this.$el.setPointerCapture(evento.pointerId)
            document.documentElement.classList.add('tq-arrastrando-barra')
        },
        mover(evento) {
            if (! this.arrastrando) return
            const ancho = evento.clientX - this.$el.parentElement.getBoundingClientRect().left
            if (ancho < this.umbral) {
                if ($store.sidebar.isOpen) $store.sidebar.close()
                return
            }
            document.documentElement.style.setProperty('--sidebar-width', Math.min(this.maximo, Math.max(this.minimo, Math.round(ancho))) + 'px')
            if (! $store.sidebar.isOpen) $store.sidebar.open()
        },
        soltar() {
            if (! this.arrastrando) return
            this.arrastrando = false
            document.documentElement.classList.remove('tq-arrastrando-barra')
            this.guardar()
        },
        guardar() {
            const ancho = $store.sidebar.isOpen ? this.anchoActual() : 0
            if (ancho === this.guardado) return
            this.guardado = ancho
            $wire.guardar(ancho)
        },
    }"
    x-effect="$store.sidebar.isOpen; if (! iniciado) { iniciado = true; return } if (! arrastrando && esEscritorio()) $nextTick(() => guardar())"
    x-on:pointerdown="empezar($event)"
    x-on:pointermove="mover($event)"
    x-on:pointerup="soltar()"
    x-on:pointercancel="soltar()"
    x-bind:class="{ 'tq-arrastrando': arrastrando }"
    class="tq-borde-barra"
    role="separator"
    aria-orientation="vertical"
    aria-label="Ancho de la barra lateral"
></div>
