{{--
    MV-043: la señal de carga al cambiar de pantalla. En la franja fina bajo la barra de arriba empieza a pasar una carga
    de energía de izquierda a derecha: la franja se va llenando con un brillo azul y unas chispas recorren lo cargado una
    tras otra; al llegar la pantalla, la carga completa la franja, destella y se apaga. Reemplaza la barra de progreso de
    Livewire. Con «reducir movimiento» o «reducir animaciones», la franja se llena sin chispas ni destello.
    Va fija (se ubica bajo la barra superior al empezar) y se conserva viva entre pantallas (`x-persist`).
--}}
<div class="tq-energia" aria-hidden="true">
    <style>
        .tq-energia { position: fixed; left: 0; right: 0; top: var(--tq-energia-top, 4rem); height: 3px; z-index: 40; pointer-events: none; opacity: 0; transition: opacity 250ms ease; }
        .tq-energia.tq-cargando, .tq-energia.tq-llego { opacity: 1; }
        .tq-energia-llenado { position: absolute; inset: 0; transform-origin: left; transform: scaleX(0);
            background: linear-gradient(90deg, color-mix(in oklab, var(--primary-600) 20%, transparent), var(--primary-600) 55%, #6FC3FF);
            box-shadow: 0 0 6px color-mix(in oklab, #6FC3FF 70%, transparent), 0 0 14px color-mix(in oklab, var(--primary-600) 45%, transparent); }
        .dark .tq-energia-llenado { background: linear-gradient(90deg, color-mix(in oklab, var(--primary-400) 25%, transparent), var(--primary-400) 55%, #B8E4FF);
            box-shadow: 0 0 8px color-mix(in oklab, #8FD3FF 80%, transparent), 0 0 18px color-mix(in oklab, var(--primary-400) 55%, transparent); }
        /* La carga avanza rápido al principio y cada vez más lento, sin llegar al final hasta que la pantalla llega. */
        .tq-energia.tq-cargando .tq-energia-llenado { animation: tq-energia-carga 2.4s cubic-bezier(.1, .7, .2, 1) forwards; }
        @keyframes tq-energia-carga { from { transform: scaleX(0); } to { transform: scaleX(.88); } }
        .tq-energia.tq-llego .tq-energia-llenado { transform: scaleX(1); transition: transform 180ms ease-out; }
        /* Las chispas: un destello que corre por lo cargado, una tras otra, con su estela. */
        .tq-energia-chispa { position: absolute; top: -2px; left: 0; width: 90px; height: 7px; border-radius: 9999px; opacity: 0;
            background: radial-gradient(closest-side, rgb(255 255 255 / .95), #9ED8FF 45%, transparent),
                        linear-gradient(90deg, transparent, color-mix(in oklab, #6FC3FF 60%, transparent));
            filter: blur(.3px) drop-shadow(0 0 6px #6FC3FF); }
        .tq-energia.tq-cargando .tq-energia-chispa { animation: tq-energia-chispa 1.1s cubic-bezier(.4, 0, .2, 1) infinite; }
        .tq-energia.tq-cargando .tq-energia-chispa + .tq-energia-chispa { animation-delay: .55s; }
        @keyframes tq-energia-chispa { 0% { transform: translateX(-90px); opacity: 0; } 15% { opacity: 1; } 85% { opacity: 1; } 100% { transform: translateX(calc(var(--tq-energia-hasta, 80vw) - 40px)); opacity: 0; } }
        /* Al llegar: un último destello que cruza todo y la franja se apaga. */
        .tq-energia.tq-llego .tq-energia-chispa:first-of-type { animation: tq-energia-final 420ms ease-out forwards; }
        @keyframes tq-energia-final { 0% { transform: translateX(-90px); opacity: 1; } 100% { transform: translateX(100vw); opacity: 0; } }
        @media (prefers-reduced-motion: reduce) { .tq-energia-chispa { display: none; } .tq-energia.tq-cargando .tq-energia-llenado { animation: none; transform: scaleX(.88); } }
        html.tq-sin-animaciones .tq-energia-chispa { display: none; }
        html.tq-sin-animaciones .tq-energia.tq-cargando .tq-energia-llenado { animation: none; transform: scaleX(.88); }
    </style>
    <span class="tq-energia-llenado"></span>
    <span class="tq-energia-chispa"></span>
    <span class="tq-energia-chispa"></span>
    <script>
        (() => {
            if (window.tqEnergia) return
            window.tqEnergia = true
            const franja = () => document.querySelector('.tq-energia')
            let apagar = null
            document.addEventListener('livewire:navigate', (e) => {
                if (e.detail?.cached) return
                const f = franja()
                if (! f) return
                clearTimeout(apagar)
                const barra = document.querySelector('.fi-topbar')
                f.style.setProperty('--tq-energia-top', (barra ? Math.round(barra.getBoundingClientRect().bottom) : 64) + 'px')
                f.style.setProperty('--tq-energia-hasta', Math.round(window.innerWidth * .88) + 'px')
                f.classList.remove('tq-llego')
                void f.offsetWidth
                f.classList.add('tq-cargando')
            })
            document.addEventListener('livewire:navigated', () => {
                const f = franja()
                if (! f || ! f.classList.contains('tq-cargando')) return
                f.classList.replace('tq-cargando', 'tq-llego')
                apagar = setTimeout(() => f.classList.remove('tq-llego'), 450)
            })
        })()
    </script>
</div>
