@php
    $secciones = $this->secciones();
    $estacion = $this->estacion();
@endphp

<x-filament-widgets::widget>
    {{-- Hoy no sale: «Mien» la ve todo el mundo (plan 1.4l). Queda como defensa si alguna vez no hubiera cajas. --}}
    @if ($secciones === [])
        <x-filament::empty-state
            heading="Aún no hay módulos"
            description="Cuando se instale un módulo, aparecerá aquí como una caja."
            icon="heroicon-o-squares-2x2"
        />
    @else
        {{--
            Todas las cajas miden lo mismo (docs/convenciones/interfaz.md, «Inicio»): ancho y alto fijos,
            justos para el ícono y el nombre. La descripción va en el tooltip; el nombre largo se corta con «…».
            Las cajas se agrupan por área (Personal, Núcleo, CRM, ERP), cada una con su color de marca y separadas por una
            línea. El color sale de los nombres declarados en el proveedor del panel, nunca de un código a mano.
        --}}
        <style>
            .tq-area[data-area="personal"] { --tq-area-color: var(--dorado-600); }
            .tq-area[data-area="nucleo"] { --tq-area-color: var(--primary-600); }
            .tq-area[data-area="crm"] { --tq-area-color: var(--morado-600); }
            .tq-area[data-area="erp"] { --tq-area-color: var(--info-600); }
            .dark .tq-area[data-area="personal"] { --tq-area-color: var(--dorado-400); }
            .dark .tq-area[data-area="nucleo"] { --tq-area-color: var(--primary-400); }
            .dark .tq-area[data-area="crm"] { --tq-area-color: var(--morado-400); }
            .dark .tq-area[data-area="erp"] { --tq-area-color: var(--info-400); }
            .tq-area + .tq-area { margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px solid var(--gray-200); }
            .dark .tq-area + .tq-area { border-top-color: var(--gray-700); }
            .tq-area-encabezado { margin-bottom: 1rem; padding-left: 0.75rem; border-left: 4px solid var(--tq-area-color); }
            .tq-area-titulo { font-size: 1rem; font-weight: 700; line-height: 1.5rem; color: var(--tq-area-color); }
            .tq-area-descripcion { font-size: 0.875rem; line-height: 1.25rem; color: var(--gray-500); }
            .dark .tq-area-descripcion { color: var(--gray-400); }
            /* Plan 1.4q: filas que se acomodan solas, de celdas de 9 × 6,5 rem. Con mouse, cada fila reserva lo que crece una
               caja con preguntas (2,5 rem), así que al crecer empuja a sus vecinas sin que ninguna salte de fila; cuando la
               caja vuelve a su tamaño, la reserva vuelve después (si no, la fila se partiría un instante). */
            /* Las vecinas de una caja crecida quedan a la altura de su centro (pedido del desarrollador, 2026-10-27). */
            .tq-cajas-modulos { display: flex; flex-wrap: wrap; align-items: center; gap: 1rem; }
            @media (hover: hover) and (pointer: fine) {
                .tq-cajas-modulos { max-width: calc(100% - 2.5rem); transition: max-width 0s linear 300ms; }
                .tq-cajas-modulos:has(.tq-activa) { max-width: 100%; transition: none; }
            }
            .tq-celda-modulo { position: relative; flex: none; width: 9rem; height: 6.5rem; }
            .tq-celda-modulo:has(> [data-tq-preguntas]) { transition: width 260ms ease-out, height 260ms ease-out; }
            .tq-celda-modulo:has(> .tq-activa) { width: 11.5rem; height: 7.75rem; }
            .tq-caja-modulo { position: absolute; inset: 0; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 0.5rem; height: 100%; min-width: 0; overflow: hidden; padding: 0.75rem; text-align: center; border-top: 3px solid var(--tq-area-color); --tw-ring-color: color-mix(in oklab, var(--tq-area-color) 45%, transparent); background-image: linear-gradient(color-mix(in oklab, var(--tq-area-color) 8%, transparent), color-mix(in oklab, var(--tq-area-color) 8%, transparent)); }
            .tq-caja-modulo:hover { --tw-ring-color: var(--tq-area-color); }
            .tq-caja-modulo .tq-caja-modulo-icono { flex: none; width: 2rem; height: 2rem; color: var(--tq-area-color); }
            /* Modo claro (pedido del desarrollador, 2026-10-26): el interior de la caja con el color de su área más intenso,
               de arriba hacia abajo, y un reflejo de sol: un destello blanco en la esquina de arriba a la izquierda y una
               línea de luz en el borde superior. Sin exagerar. En modo oscuro, como estaba. */
            .tq-caja-modulo {
                background-image:
                    radial-gradient(130% 85% at 12% 0%, rgb(255 255 255 / 0.85) 0%, rgb(255 255 255 / 0.35) 22%, rgb(255 255 255 / 0) 48%),
                    linear-gradient(165deg, color-mix(in oklab, var(--tq-area-color) 20%, white) 0%, color-mix(in oklab, var(--tq-area-color) 10%, white) 100%);
                box-shadow: inset 0 1px 0 rgb(255 255 255 / 0.9), 0 1px 3px color-mix(in oklab, var(--tq-area-color) 18%, transparent);
            }
            /* La estrella de Mien iluminada desde adentro, en los dos modos (pedido del desarrollador, 2026-10-26): un relleno
               dorado y un halo suave del mismo dorado. En el oscuro, el relleno es translúcido para no tapar el contorno. */
            .tq-caja-modulo[data-modulo="mien"] .tq-caja-modulo-icono { fill: var(--dorado-200); filter: drop-shadow(0 0 3px color-mix(in oklab, var(--dorado-400) 75%, transparent)) drop-shadow(0 0 7px color-mix(in oklab, var(--dorado-400) 35%, transparent)); }
            .dark .tq-caja-modulo[data-modulo="mien"] .tq-caja-modulo-icono { fill: color-mix(in oklab, var(--dorado-400) 45%, transparent); filter: drop-shadow(0 0 3px color-mix(in oklab, var(--dorado-400) 80%, transparent)) drop-shadow(0 0 8px color-mix(in oklab, var(--dorado-400) 40%, transparent)); }
            .dark .tq-caja-modulo { background-image: linear-gradient(color-mix(in oklab, var(--tq-area-color) 8%, transparent), color-mix(in oklab, var(--tq-area-color) 8%, transparent)); box-shadow: none; }
            /* Al pasar el mouse, la caja entera crece (y su ícono adentro), más que las de una subparte (`tq-caja-sub`,
               1,04): queda encima de sus vecinas, sin empujarlas. */
            .tq-caja-modulo, .tq-caja-modulo .tq-caja-modulo-icono { transition: transform 180ms ease-out; }
            .tq-caja-modulo:hover, .tq-caja-modulo:focus-visible { z-index: 1; transform: scale(1.08); }
            .tq-caja-modulo:hover .tq-caja-modulo-icono, .tq-caja-modulo:focus-visible .tq-caja-modulo-icono { transform: scale(1.2); }
            @media (prefers-reduced-motion: reduce) { .tq-caja-modulo, .tq-caja-modulo .tq-caja-modulo-icono { transition: none; transform: none !important; } }
            html.tq-sin-animaciones .tq-caja-modulo, html.tq-sin-animaciones .tq-caja-modulo .tq-caja-modulo-icono { transition: none; transform: none !important; }
            .tq-caja-modulo-nombre { display: -webkit-box; -webkit-box-orient: vertical; -webkit-line-clamp: 2; line-clamp: 2; overflow: hidden; font-size: 0.875rem; font-weight: 600; line-height: 1.25rem; }
            .tq-caja-modulo-marco { display: flex; flex: none; }

            /* Plan 1.4q: la caja con preguntas no se agranda con escala: al dejar el mouse crece su celda (11,5 × 7,75 rem) y
               empuja a sus vecinas. */
            .tq-caja-modulo[data-tq-preguntas]:hover, .tq-caja-modulo[data-tq-preguntas]:focus-visible { transform: none; }
            .tq-caja-modulo[data-tq-preguntas]:hover .tq-caja-modulo-icono, .tq-caja-modulo[data-tq-preguntas]:focus-visible .tq-caja-modulo-icono { transform: none; }
            .tq-caja-modulo[data-tq-preguntas] { transition: box-shadow 260ms ease-out; }
            .tq-caja-modulo[data-tq-preguntas] .tq-caja-modulo-icono { transition: width 260ms ease-out, height 260ms ease-out; }
            .tq-caja-modulo.tq-activa { z-index: 1; justify-content: flex-start; gap: 0.35rem; padding-top: 0.75rem; padding-bottom: 0.6rem; --tw-ring-color: var(--tq-area-color); box-shadow: 0 12px 28px color-mix(in oklab, var(--tq-area-color) 28%, transparent); }
            /* Mientras pasan las preguntas, sin ícono: solo el nombre y la pregunta (pedido del desarrollador, 2026-10-27); así la
               caja crece poco. El ícono vuelve al final, con su animación. */
            .tq-caja-modulo.tq-activa:not(.tq-final) .tq-caja-modulo-marco { display: none; }
            .tq-caja-preguntas { position: relative; flex: 1; width: 100%; }
            .tq-caja-pregunta { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; padding-inline: 0.5rem; font-size: 0.875rem; line-height: 1.3; text-wrap: balance; opacity: 0; transform: translateY(6px); transition: opacity 400ms ease, transform 400ms ease; }
            .tq-caja-pregunta.tq-visible { opacity: 1; transform: none; }
            .tq-caja-contador { display: flex; justify-content: center; gap: 0.3rem; padding-bottom: 0.1rem; }
            .tq-caja-contador i { width: 0.35rem; height: 0.35rem; border-radius: 9999px; background: color-mix(in oklab, var(--tq-area-color) 30%, transparent); }
            .tq-caja-contador i.tq-hecho { background: var(--tq-area-color); }
            /* Al terminar, el ícono ocupa el centro, hace su animación (D6) y queda quieto (D3). */
            .tq-caja-modulo.tq-final { justify-content: center; padding-top: 0.75rem; }
            .tq-caja-modulo.tq-final .tq-caja-modulo-icono { width: 3rem; height: 3rem; }
            /* Al pasar al final, la cámara sube (pedido del desarrollador, 2026-10-27): el ícono entra desde un poco más
               arriba y el nombre baja suave a su lugar. Sutil, pero se nota; después, la animación propia del ícono. */
            .tq-caja-modulo.tq-final .tq-caja-modulo-marco { animation: tq-sube-camara-icono 700ms cubic-bezier(.2, .7, .2, 1) both; }
            .tq-caja-modulo.tq-final .tq-caja-modulo-nombre { animation: tq-sube-camara-nombre 700ms cubic-bezier(.2, .7, .2, 1) both; }
            @keyframes tq-sube-camara-icono { from { transform: translateY(-1.25rem); opacity: 0; } to { transform: none; opacity: 1; } }
            @keyframes tq-sube-camara-nombre { from { transform: translateY(-1.75rem); } to { transform: none; } }
            .tq-caja-modulo.tq-final .tq-caja-modulo-marco { overflow: hidden; padding: 0.3rem; }
            .tq-final[data-tq-animacion="pulso"] .tq-caja-modulo-icono { animation: tq-pulso 1.2s ease-in-out 2 both; }
            @keyframes tq-pulso { 50% { transform: scale(1.15); } }
            .tq-final[data-tq-animacion="camara"] .tq-caja-modulo-icono { animation: tq-camara 1.2s cubic-bezier(.2, .8, .2, 1) both, tq-brillo 1.2s 1.2s ease-in-out both; }
            @keyframes tq-camara { 0% { transform: translateY(-60%); opacity: 0; } 50% { opacity: 1; } 80% { transform: translateY(4%); } 100% { transform: none; opacity: 1; } }
            @keyframes tq-brillo { 50% { filter: drop-shadow(0 0 8px var(--tq-area-color)); } }
            .tq-final[data-tq-animacion="apreton"] .tq-mano-izq { animation: tq-llega-izq 1.6s ease-out both; }
            .tq-final[data-tq-animacion="apreton"] .tq-mano-der { animation: tq-llega-der 1.6s ease-out both; }
            @keyframes tq-llega-izq { 0% { transform: translateX(-7px); opacity: 0; } 45% { transform: none; opacity: 1; } 60% { transform: translateY(-1.2px); } 75% { transform: translateY(1.2px); } 90% { transform: translateY(-0.6px); } 100% { transform: none; opacity: 1; } }
            @keyframes tq-llega-der { 0% { transform: translateX(7px); opacity: 0; } 45% { transform: none; opacity: 1; } 60% { transform: translateY(-1.2px); } 75% { transform: translateY(1.2px); } 90% { transform: translateY(-0.6px); } 100% { transform: none; opacity: 1; } }
            .tq-caja-modulo .tq-persona { transform-box: fill-box; transform-origin: 50% 100%; }
            .tq-final[data-tq-animacion="personas"] .tq-persona { animation: tq-aparece 600ms cubic-bezier(.2, .8, .2, 1.2) both; }
            .tq-final[data-tq-animacion="personas"] .tq-persona-der { animation-delay: 550ms; }
            .tq-final[data-tq-animacion="personas"] .tq-persona-izq { animation-delay: 1100ms; }
            @keyframes tq-aparece { from { opacity: 0; transform: scale(.4) translateY(3px); } to { opacity: 1; transform: none; } }
            .tq-final[data-tq-animacion="rueda"] .tq-caja-modulo-icono { animation: tq-rueda 2s cubic-bezier(.3, .1, .2, 1) both; }
            @keyframes tq-rueda { to { transform: rotate(360deg); } }
            /* D8: con animaciones reducidas, las preguntas cambian sin movimiento y el ícono no se anima. */
            @media (prefers-reduced-motion: reduce) {
                .tq-celda-modulo, .tq-caja-modulo[data-tq-preguntas], .tq-caja-modulo[data-tq-preguntas] .tq-caja-modulo-icono, .tq-caja-pregunta { transition: none; }
                .tq-caja-pregunta { transform: none; }
                .tq-caja-modulo.tq-final * { animation: none !important; }
            }
            html.tq-sin-animaciones .tq-celda-modulo, html.tq-sin-animaciones .tq-caja-modulo[data-tq-preguntas], html.tq-sin-animaciones .tq-caja-modulo[data-tq-preguntas] .tq-caja-modulo-icono, html.tq-sin-animaciones .tq-caja-pregunta { transition: none; }
            html.tq-sin-animaciones .tq-caja-pregunta { transform: none; }
            html.tq-sin-animaciones .tq-caja-modulo.tq-final * { animation: none !important; }

            /* Plan 1.4r: la caja entera según la estación, solo en el inicio (D1) y con la preferencia prendida (D5). El matiz
               es siempre el del área; claridad e intensidad salen de EstiloEstacion (`--tq-e-*`, por área, también para una
               caja nueva). Estático: nada se mueve, igual con animaciones reducidas. La caja mide lo mismo: la ficha del
               ícono es de 2,75 rem para que un nombre de dos líneas quepa (D7). */
            .tq-inicio-cajas[data-tq-estacion] .tq-caja-modulo { color: var(--tq-e-texto); border-top: 0; }
            .tq-inicio-cajas[data-tq-estacion] .tq-caja-modulo-nombre, .tq-inicio-cajas[data-tq-estacion] .tq-caja-pregunta { color: var(--tq-e-texto); }
            .tq-caja-marca-agua { display: none; }
            .tq-caja-degradados { position: absolute; width: 0; height: 0; overflow: hidden; }
            .tq-inicio-cajas[data-tq-estacion] .tq-caja-modulo[data-modulo="mien"] .tq-caja-modulo-icono { fill: var(--tq-e-estrella); filter: drop-shadow(0 0 3px color-mix(in oklab, var(--tq-e-estrella) 85%, transparent)) drop-shadow(0 0 8px color-mix(in oklab, var(--tq-e-estrella) 45%, transparent)); }
            /* La franja de arriba, degradada (primavera y otoño). */
            .tq-inicio-cajas:is([data-tq-estacion="primavera"], [data-tq-estacion="otono"]) .tq-caja-modulo::before { content: ''; position: absolute; inset: 0 0 auto 0; height: 3px; background-image: linear-gradient(90deg, var(--tq-e-franja1), var(--tq-e-franja2)); }
            /* La ficha redonda del ícono (primavera e invierno; el verano sin ella, 1.4t). */
            .tq-inicio-cajas:is([data-tq-estacion="primavera"], [data-tq-estacion="invierno"]) .tq-caja-modulo-marco { width: 2.75rem; height: 2.75rem; align-items: center; justify-content: center; border-radius: 9999px; }
            .tq-inicio-cajas:is([data-tq-estacion="primavera"], [data-tq-estacion="invierno"]) .tq-caja-modulo:not(.tq-final) .tq-caja-modulo-icono { width: 1.5rem; height: 1.5rem; }
            /* Con la ficha, menos aire arriba y abajo: 2,75 rem de ficha y dos líneas de nombre caben en los 6,5 rem de siempre. */
            .tq-inicio-cajas:is([data-tq-estacion="primavera"], [data-tq-estacion="invierno"]) .tq-caja-modulo:not(.tq-activa) { padding-block: 0.5rem; gap: 0.25rem; }
            .tq-inicio-cajas:is([data-tq-estacion="primavera"], [data-tq-estacion="invierno"]) .tq-caja-modulo.tq-final .tq-caja-modulo-marco { width: 3.5rem; height: 3.5rem; padding: 0; }
            .tq-inicio-cajas:is([data-tq-estacion="primavera"], [data-tq-estacion="invierno"]) .tq-caja-modulo.tq-final .tq-caja-modulo-icono { width: 2.25rem; height: 2.25rem; }

            /* Primavera, fresca y liviana: la ficha en pastel y el ícono en el color del área (1.4t). */
            .tq-inicio-cajas[data-tq-estacion="primavera"] .tq-caja-modulo { background-image: linear-gradient(160deg, var(--tq-e-fondo1), var(--tq-e-fondo2)); box-shadow: 0 4px 14px color-mix(in oklab, var(--tq-e-sombra) 22%, transparent); }
            .tq-inicio-cajas[data-tq-estacion="primavera"] .tq-caja-modulo-marco { background-image: linear-gradient(145deg, var(--tq-e-ficha1), var(--tq-e-ficha2)); }
            .tq-inicio-cajas[data-tq-estacion="primavera"] .tq-caja-modulo .tq-caja-modulo-icono { color: var(--tq-e-texto); }

            /* Verano, la más intensa (sin exagerar, 1.4t): la caja entera en el color del área, con un brillo arriba que no llega al
               nombre y el ícono suelto, sin ficha. */
            .tq-inicio-cajas[data-tq-estacion="verano"] .tq-caja-modulo { background-image: linear-gradient(180deg, rgb(255 255 255 / 0.22) 0%, rgb(255 255 255 / 0) 45%), linear-gradient(135deg, var(--tq-e-fondo1), var(--tq-e-fondo2)); box-shadow: 0 6px 16px color-mix(in oklab, var(--tq-e-sombra) 25%, transparent); }
            .tq-inicio-cajas[data-tq-estacion="verano"] .tq-caja-modulo .tq-caja-modulo-icono { color: white; stroke-width: 1.8; }
            .tq-inicio-cajas[data-tq-estacion="verano"] .tq-caja-contador i { background: rgb(255 255 255 / 0.35); }
            .tq-inicio-cajas[data-tq-estacion="verano"] .tq-caja-contador i.tq-hecho { background: white; }
            /* Verano claro, de playa (1.4t): cada área con su color de playa (EstiloEstacion::MATIZ_DE_ESTACION), claro, y el
               nombre y el ícono en su tono oscuro. */
            /* El sol le pega a la caja (1.4t), en claro: un destello en la esquina de arriba a la izquierda y un reflejo en
               diagonal; en verano, más grande y más brillante que en primavera. Sobre fondo claro y nombre oscuro, el blanco
               solo le suma contraste. */
            html:not(.dark) .tq-inicio-cajas[data-tq-estacion="verano"] .tq-caja-modulo { background-image: radial-gradient(130% 90% at 8% 0%, rgb(255 255 255 / calc(0.95 * var(--tq-sol))) 0%, rgb(255 255 255 / calc(0.5 * var(--tq-sol))) 28%, rgb(255 255 255 / 0) 55%), linear-gradient(118deg, rgb(255 255 255 / 0) 48%, rgb(255 255 255 / calc(0.6 * var(--tq-sol))) 56%, rgb(255 255 255 / 0) 64%, rgb(255 255 255 / 0) 67%, rgb(255 255 255 / calc(0.35 * var(--tq-sol))) 71%, rgb(255 255 255 / 0) 75%), linear-gradient(160deg, var(--tq-e-fondo1), var(--tq-e-fondo2)); }
            html:not(.dark) .tq-inicio-cajas[data-tq-estacion="primavera"] .tq-caja-modulo { background-image: radial-gradient(80% 55% at 10% 0%, rgb(255 255 255 / calc(0.75 * var(--tq-sol))) 0%, rgb(255 255 255 / calc(0.3 * var(--tq-sol))) 25%, rgb(255 255 255 / 0) 50%), linear-gradient(118deg, rgb(255 255 255 / 0) 56%, rgb(255 255 255 / calc(0.35 * var(--tq-sol))) 61%, rgb(255 255 255 / 0) 66%), linear-gradient(160deg, var(--tq-e-fondo1), var(--tq-e-fondo2)); }
            html:not(.dark) .tq-inicio-cajas[data-tq-estacion="verano"] .tq-caja-modulo .tq-caja-modulo-icono { color: var(--tq-e-texto); }
            html:not(.dark) .tq-inicio-cajas[data-tq-estacion="verano"] .tq-caja-contador i { background: color-mix(in oklab, var(--tq-e-texto) 25%, transparent); }
            html:not(.dark) .tq-inicio-cajas[data-tq-estacion="verano"] .tq-caja-contador i.tq-hecho { background: var(--tq-e-texto); }

            /* Otoño, cálida y profunda: sin ficha, el ícono con degradado y su marca de agua en la esquina. */
            .tq-inicio-cajas[data-tq-estacion="otono"] .tq-caja-modulo { background-image: linear-gradient(180deg, var(--tq-e-fondo1), var(--tq-e-fondo2)); box-shadow: 0 3px 10px color-mix(in oklab, var(--tq-e-sombra) 18%, transparent); }
            .tq-inicio-cajas[data-tq-estacion="otono"] .tq-caja-modulo .tq-caja-modulo-icono { stroke: var(--tq-trazo); }
            .tq-inicio-cajas[data-tq-estacion="otono"] .tq-caja-marca-agua { display: block; position: absolute; right: -0.75rem; bottom: -0.9rem; width: 84px; height: 84px; opacity: 0.13; color: var(--tq-e-icono1); stroke-width: 2.1; pointer-events: none; }
            .tq-inicio-cajas[data-tq-estacion="otono"] .tq-caja-modulo[data-modulo="mien"] .tq-caja-marca-agua { fill: var(--tq-e-estrella); }

            /* Invierno, escarchada: borde fino, luz interior arriba y una ficha de hielo; el ícono se desvanece hacia blanco. */
            .tq-inicio-cajas[data-tq-estacion="invierno"] .tq-caja-modulo { border: 1px solid var(--tq-e-borde); background-image: linear-gradient(200deg, var(--tq-e-fondo1), var(--tq-e-fondo2)); box-shadow: inset 0 1px 0 rgb(255 255 255 / 0.95), 0 3px 10px color-mix(in oklab, var(--tq-e-sombra) 16%, transparent); }
            .dark .tq-inicio-cajas[data-tq-estacion="invierno"] .tq-caja-modulo { box-shadow: inset 0 1px 0 rgb(255 255 255 / 0.12), 0 3px 10px color-mix(in oklab, var(--tq-e-sombra) 40%, transparent); }
            .tq-inicio-cajas[data-tq-estacion="invierno"] .tq-caja-modulo-marco { background-image: radial-gradient(circle at 35% 30%, var(--tq-e-ficha1) 0%, var(--tq-e-ficha2) 75%); box-shadow: inset 0 0 0 1px rgb(255 255 255 / 0.6); }
            .tq-inicio-cajas[data-tq-estacion="invierno"] .tq-caja-modulo .tq-caja-modulo-icono { stroke: var(--tq-trazo); }

            /* Hojas de otoño en la esquina de abajo a la izquierda y escarcha de invierno (1.4t), distintas en cada caja
               (AdornosDeEstacion). El nombre va encima, con un halo del color de su fondo para leerse aunque pase sobre
               ellas. */
            .tq-caja-adornos { position: absolute; inset: 0; overflow: hidden; border-radius: inherit; pointer-events: none; }
            .tq-caja-hojas, .tq-caja-hielo { display: none; }
            .tq-inicio-cajas[data-tq-estacion="otono"] .tq-caja-hojas { display: block; position: absolute; left: -3px; bottom: -4px; width: 48px; height: 41px; }
            .tq-caja-hojas .tq-hoja-vena { fill: none; stroke: rgb(0 0 0 / 0.28); stroke-width: 0.7; stroke-linecap: round; }
            .dark .tq-inicio-cajas[data-tq-estacion="otono"] .tq-caja-hojas { opacity: 0.85; }
            .tq-inicio-cajas[data-tq-estacion="invierno"] .tq-caja-hielo { display: block; position: absolute; fill: none; stroke: currentColor; stroke-width: 1; stroke-linecap: round; color: var(--tq-e-icono2); opacity: 0.55; }
            .tq-hielo-arriba { top: 0; right: 0; width: 58px; height: 58px; }
            .tq-hielo-abajo { bottom: 0; left: 0; width: 36px; height: 36px; transform: rotate(180deg); }
            /* Con frío (plan 1.4u, D3), un poco de escarcha en cualquier estación; la de invierno ya la tiene. */
            html[data-tq-temperatura="frio"] .tq-inicio-cajas[data-tq-estacion]:not([data-tq-estacion="invierno"]) .tq-caja-hielo { display: block; position: absolute; fill: none; stroke: currentColor; stroke-width: 1; stroke-linecap: round; color: var(--tq-e-texto); opacity: 0.22; }
            .dark .tq-inicio-cajas[data-tq-estacion="invierno"] .tq-caja-hielo { color: var(--tq-e-icono1); opacity: 0.4; }
            .tq-inicio-cajas[data-tq-estacion="invierno"] .tq-caja-modulo { background-image: linear-gradient(135deg, rgb(255 255 255 / 0) 30%, rgb(255 255 255 / 0.35) 38%, rgb(255 255 255 / 0) 46%), linear-gradient(200deg, var(--tq-e-fondo1), var(--tq-e-fondo2)); }
            .dark .tq-inicio-cajas[data-tq-estacion="invierno"] .tq-caja-modulo { background-image: linear-gradient(135deg, rgb(255 255 255 / 0) 30%, rgb(255 255 255 / 0.06) 38%, rgb(255 255 255 / 0) 46%), linear-gradient(200deg, var(--tq-e-fondo1), var(--tq-e-fondo2)); }
            .tq-inicio-cajas:is([data-tq-estacion="otono"], [data-tq-estacion="invierno"]) .tq-caja-modulo-nombre { position: relative; text-shadow: 0 0 3px var(--tq-e-fondo2), 0 0 6px var(--tq-e-fondo2); }
            .tq-inicio-cajas:is([data-tq-estacion="otono"], [data-tq-estacion="invierno"]) .tq-caja-modulo-marco { position: relative; }
            /* Íconos con más contraste (1.4t): trazo más grueso en todas las estaciones. */
            .tq-inicio-cajas[data-tq-estacion] .tq-caja-modulo:not([data-modulo="mien"]) .tq-caja-modulo-icono { stroke-width: 1.9; }

            /* Claro con relieve (1.4t; pedido del desarrollador: «que se pueda distinguir y que haya el efecto 3D, pero tampoco
               exagerado»): un borde fino, luz arriba, una sombra corta al pie por dentro y dos sombras debajo, del color de
               cada caja. Con el mouse, se levanta un poco más. */
            html:not(.dark) .tq-inicio-cajas[data-tq-estacion] .tq-caja-modulo { border: 1px solid color-mix(in oklab, var(--tq-e-sombra) 40%, white); box-shadow: inset 0 1px 0 rgb(255 255 255 / 0.9), inset 0 -2px 0 color-mix(in oklab, var(--tq-e-sombra) 16%, transparent), 0 1px 2px color-mix(in oklab, var(--tq-e-sombra) 30%, transparent), 0 6px 14px -4px color-mix(in oklab, var(--tq-e-sombra) 40%, transparent); }
            html:not(.dark) .tq-inicio-cajas[data-tq-estacion] .tq-caja-modulo:is(:hover, :focus-visible, .tq-activa) { box-shadow: inset 0 1px 0 rgb(255 255 255 / 0.9), inset 0 -2px 0 color-mix(in oklab, var(--tq-e-sombra) 16%, transparent), 0 2px 4px color-mix(in oklab, var(--tq-e-sombra) 30%, transparent), 0 12px 24px -6px color-mix(in oklab, var(--tq-e-sombra) 45%, transparent); }

            /* Oscuro fosforescente (plan 1.4t; pedido del desarrollador: «tampoco exagerado, pero que sí se note»): un borde
               fino y un halo con el color del área, y el ícono que brilla en ese color. Al pasar el mouse, algo más. */
            .dark .tq-inicio-cajas[data-tq-estacion] .tq-caja-modulo { border: 1px solid color-mix(in oklab, var(--tq-e-neon) 65%, transparent); box-shadow: 0 0 16px color-mix(in oklab, var(--tq-e-neon) 30%, transparent), inset 0 0 14px color-mix(in oklab, var(--tq-e-neon) 13%, transparent); }
            .dark .tq-inicio-cajas[data-tq-estacion] .tq-caja-modulo:is(:hover, :focus-visible, .tq-activa) { border-color: color-mix(in oklab, var(--tq-e-neon) 90%, transparent); box-shadow: 0 0 24px color-mix(in oklab, var(--tq-e-neon) 42%, transparent), inset 0 0 16px color-mix(in oklab, var(--tq-e-neon) 17%, transparent); }
            .dark .tq-inicio-cajas[data-tq-estacion] .tq-caja-modulo .tq-caja-modulo-icono { color: var(--tq-e-neon); }
            .dark .tq-inicio-cajas[data-tq-estacion] .tq-caja-modulo:not([data-modulo="mien"]) .tq-caja-modulo-icono { filter: drop-shadow(0 0 4px color-mix(in oklab, var(--tq-e-neon) 75%, transparent)); }

            /* Plan 1.4ad (imagen del desarrollador, 2026-11-16): el ícono va en una baldosa como la de una aplicación, con el
               color de su área (la paleta de siempre), en todas las cajas y en todas las estaciones: un degradado de más
               claro arriba a la izquierda a más hondo abajo, un brillo arriba a la derecha, un filo de luz y una sombra suave
               del mismo color; el ícono, en blanco. Va al final y con `!important` porque cada estación pinta su ficha. */
            .tq-caja-modulo .tq-caja-modulo-marco { display: flex; align-items: center; justify-content: center; width: 3rem !important; height: 3rem !important; padding: 0 !important; border-radius: 0.9rem !important; transition: transform 180ms ease-out;
                background-image: radial-gradient(120% 70% at 85% 0%, rgb(255 255 255 / 0.55) 0%, rgb(255 255 255 / 0.15) 35%, rgb(255 255 255 / 0) 60%), linear-gradient(150deg, color-mix(in oklab, var(--tq-area-color) 55%, white) 0%, var(--tq-area-color) 62%, color-mix(in oklab, var(--tq-area-color) 85%, black) 100%) !important;
                box-shadow: inset 0 1px 0 rgb(255 255 255 / 0.55), inset 0 -2px 4px rgb(0 0 0 / 0.12), 0 4px 10px -2px color-mix(in oklab, var(--tq-area-color) 55%, transparent), 0 1px 2px color-mix(in oklab, var(--tq-area-color) 40%, transparent) !important; }
            .dark .tq-caja-modulo .tq-caja-modulo-marco { background-image: radial-gradient(120% 70% at 85% 0%, rgb(255 255 255 / 0.35) 0%, rgb(255 255 255 / 0.08) 35%, rgb(255 255 255 / 0) 60%), linear-gradient(150deg, color-mix(in oklab, var(--tq-area-color) 70%, white) 0%, var(--tq-area-color) 62%, color-mix(in oklab, var(--tq-area-color) 75%, black) 100%) !important; }
            .tq-caja-modulo .tq-caja-modulo-marco .tq-caja-modulo-icono { width: 1.75rem !important; height: 1.75rem !important; color: white !important; stroke: currentColor !important; stroke-width: 1.6 !important; filter: drop-shadow(0 1px 1px rgb(0 0 0 / 0.18)) !important; }
            .tq-caja-modulo[data-modulo="mien"] .tq-caja-modulo-marco .tq-caja-modulo-icono { fill: rgb(255 255 255 / 0.35) !important; filter: drop-shadow(0 0 3px rgb(255 255 255 / 0.7)) !important; }
            .tq-caja-modulo.tq-final .tq-caja-modulo-marco { width: 3.5rem !important; height: 3.5rem !important; border-radius: 1.05rem !important; }
            .tq-caja-modulo.tq-final .tq-caja-modulo-marco .tq-caja-modulo-icono { width: 2.1rem !important; height: 2.1rem !important; }
            /* Al pasar el mouse crece la baldosa entera, no el ícono dentro de ella. */
            .tq-caja-modulo:is(:hover, :focus-visible):not([data-tq-preguntas]) .tq-caja-modulo-marco { transform: scale(1.1); }
            .tq-caja-modulo:is(:hover, :focus-visible) .tq-caja-modulo-marco .tq-caja-modulo-icono { transform: none; }
            @media (prefers-reduced-motion: reduce) { .tq-caja-modulo .tq-caja-modulo-marco { transition: none; transform: none !important; } }
            html.tq-sin-animaciones .tq-caja-modulo .tq-caja-modulo-marco { transition: none; transform: none !important; }
        </style>
        @if ($estacion !== null)
            <style>{!! \App\Modules\Nucleo\Navegacion\EstiloEstacion::css($estacion) !!}</style>
        @endif
        <script>
            // Plan 1.4q: las preguntas de una caja, una cada 3 segundos (D2), y al final la animación del ícono (D3).
            // Solo con mouse (D4); al sacarlo se corta y la caja vuelve a como estaba.
            window.tqCajaConPreguntas = (preguntas) => ({
                preguntas,
                indice: -1,
                visible: false,
                activa: false,
                final: false,
                relojes: [],
                empezar() {
                    if (! window.matchMedia('(hover: hover) and (pointer: fine)').matches) {
                        return
                    }

                    this.parar()
                    this.activa = true
                    this.preguntas.forEach((pregunta, n) => {
                        this.despues(n * 3000 + 150, () => {
                            this.indice = n
                            this.visible = false
                            requestAnimationFrame(() => requestAnimationFrame(() => { this.visible = true }))
                        })
                        this.despues((n + 1) * 3000 - 250, () => { this.visible = false })
                    })
                    this.despues(this.preguntas.length * 3000 + 150, () => { this.final = true })
                },
                parar() {
                    this.relojes.forEach(clearTimeout)
                    this.relojes = []
                    this.activa = false
                    this.final = false
                    this.visible = false
                    this.indice = -1
                },
                despues(ms, accion) {
                    this.relojes.push(setTimeout(accion, ms))
                },
            })
        </script>

        <div class="tq-inicio-cajas" @if ($estacion !== null) data-tq-estacion="{{ $estacion->value }}" @endif>
        @foreach ($secciones as $seccion)
            <section class="tq-area" data-area="{{ $seccion['area']->value }}">
                <header class="tq-area-encabezado">
                    <h2 class="tq-area-titulo">{{ $seccion['area']->etiqueta() }}</h2>
                    <p class="tq-area-descripcion">{{ $seccion['area']->descripcion() }}</p>
                </header>

                <div class="tq-cajas-modulos">
                    @foreach ($seccion['cajas'] as $caja)
                        @include('nucleo::filament.widgets.caja-modulo', ['caja' => $caja])
                    @endforeach
                </div>
            </section>
        @endforeach
        </div>
    @endif
</x-filament-widgets::widget>
