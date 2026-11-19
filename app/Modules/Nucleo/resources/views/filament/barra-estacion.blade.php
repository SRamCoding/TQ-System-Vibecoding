{{--
    La barra superior según la estación (plan 1.4t). La luz del sol o la luna la pinta la vista de navegación
    (LuzDelDia, plan 1.4ac); aquí, en otoño, las hojas que caen cada tanto. Se quedan dentro de la barra y detrás
    de lo que tiene (`isolation` en la barra y `z-index: -1` aquí): nunca encima del contenido ni de un botón.
    Cada INTERVALO segundos caen unas pocas, con la pestaña a la vista; con «reducir movimiento» o «reducir animaciones»,
    ninguna. Es la única animación que se repite (excepción a «ningún bucle», pedida por el desarrollador).
--}}
@php
    $usuario = filament()->auth()->user();
    $estacion = $usuario instanceof \App\Modules\Nucleo\Models\User ? \App\Modules\Nucleo\Navegacion\EstacionDeLaApariencia::para($usuario) : null;
@endphp
@if ($estacion === \App\Modules\Nucleo\Enums\Temporada::Otono)
    {{-- La faja de Oportunidades oculta esta clase mientras se ve (plan 2.21b; FajaTecladoYMedidaTest): si cambia, cámbiala allá. --}}
    <span class="tq-barra-hojas" aria-hidden="true" data-tq-intervalo="45">
        <template>@foreach (\App\Modules\Nucleo\Navegacion\AdornosDeEstacion::hojasQueCaen() as $hoja){!! $hoja !!}@endforeach</template>
    </span>
    <style>
        html .fi-topbar { position: relative; isolation: isolate; }
        .tq-barra-hojas { position: absolute; inset: 0; z-index: -1; overflow: hidden; pointer-events: none; }
        .tq-barra-hojas .tq-hoja-cae { position: absolute; top: -22px; width: 18px; height: 18px; opacity: 0; animation: tqHojaCae var(--tq-dura, 6s) ease-in var(--tq-espera, 0s) 1 forwards; }
        .tq-barra-hojas .tq-hoja-vena { fill: none; stroke: rgb(0 0 0 / 0.28); stroke-width: 0.7; stroke-linecap: round; }
        .dark .tq-barra-hojas .tq-hoja-cae { filter: brightness(0.9); }
        @keyframes tqHojaCae {
            0% { opacity: 0; transform: translate(0, 0) rotate(var(--tq-giro, 0deg)); }
            12% { opacity: 0.9; }
            35% { transform: translate(var(--tq-vaiven, 14px), 26px) rotate(calc(var(--tq-giro, 0deg) + 70deg)); }
            65% { transform: translate(calc(var(--tq-vaiven, 14px) * -0.6), 52px) rotate(calc(var(--tq-giro, 0deg) + 150deg)); }
            85% { opacity: 0.8; }
            100% { opacity: 0; transform: translate(calc(var(--tq-vaiven, 14px) * 0.4), 82px) rotate(calc(var(--tq-giro, 0deg) + 220deg)); }
        }
        @media (prefers-reduced-motion: reduce) { .tq-barra-hojas { display: none; } }
        html.tq-sin-animaciones .tq-barra-hojas { display: none; }
    </style>
    <script>
        (() => {
            if (window.tqHojasBarra) return;
            window.tqHojasBarra = true;
            const quieto = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches || document.documentElement.classList.contains('tq-sin-animaciones');
            const caer = () => {
                const barra = document.querySelector('.tq-barra-hojas');
                if (! barra || document.hidden || quieto()) return;
                const hojas = barra.querySelector('template').content.children;
                const cantidad = 3 + Math.floor(Math.random() * 2);
                for (let n = 0; n < cantidad; n++) {
                    const hoja = hojas[Math.floor(Math.random() * hojas.length)].cloneNode(true);
                    hoja.style.left = (8 + Math.random() * 84) + '%';
                    hoja.style.setProperty('--tq-espera', (Math.random() * 1.5).toFixed(2) + 's');
                    hoja.style.setProperty('--tq-dura', (5 + Math.random() * 2.5).toFixed(2) + 's');
                    hoja.style.setProperty('--tq-giro', Math.floor(Math.random() * 360) + 'deg');
                    hoja.style.setProperty('--tq-vaiven', (8 + Math.random() * 14) * (Math.random() < 0.5 ? -1 : 1) + 'px');
                    hoja.addEventListener('animationend', () => hoja.remove());
                    barra.appendChild(hoja);
                }
            };
            const intervalo = Number(document.querySelector('.tq-barra-hojas')?.dataset.tqIntervalo ?? 45) * 1000;
            setTimeout(caer, 6000);
            setInterval(caer, intervalo);
        })();
    </script>
@endif
