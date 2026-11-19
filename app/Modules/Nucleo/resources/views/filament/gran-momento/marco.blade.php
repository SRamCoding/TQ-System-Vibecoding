{{--
    El gran momento de una fecha (plan 1.4d, D5): una capa a pantalla completa de 30 segundos con su propia escena y su
    fondo, el título y el versículo en texto (para el lector de pantalla). Es un diálogo: el foco queda atrapado dentro,
    Esc o «Cerrar» lo cierran y a los 30 segundos se cierra solo. Animado, la escena y el texto entran por tiempos;
    quieto (animaciones reducidas: el sistema o la preferencia), todo se ve desde el principio, sin movimiento.
    Nada parpadea más de tres veces por segundo ni da destellos grandes (WCAG 2.3.1).
    Recibe `$escena` (EscenaGranMomento), `$animado` y `$esperar`: si espera, no se muestra hasta recibir el evento
    `tq-gran-momento-empezar` (lo manda GranMomentoEnPantalla cuando el servidor confirma la primera marca, D5). Avisa
    con `tq-gran-momento-abierto` al empezar y `tq-gran-momento-cerrado` al cerrarse: la ambientación se pausa y se
    reanuda. Al cerrarse, el foco vuelve a donde estaba (`x-trap`).
--}}
@php /** @var \App\Modules\Nucleo\Saludo\EscenaGranMomento $escena */ @endphp
<style>
    .tq-gm { position: fixed; inset: 0; z-index: 60; overflow: hidden; background: #05070f; color: #fff6e0; }
    /* La escena arriba; el texto en su franja de abajo, del color del suelo de la escena: nunca tapa las figuras. */
    .tq-gm { display: flex; flex-direction: column; }
    .tq-gm-escena { position: relative; flex: 1 1 auto; min-block-size: 0; }
    .tq-gm-escena svg { position: absolute; inset: 0; inline-size: 100%; block-size: 100%; }
    /* El texto se achica también con la altura: en una pantalla baja, la escena conserva su lugar. */
    .tq-gm-texto { flex: 0 0 auto; padding: 0.25rem 1.5rem min(2.5rem, 4vh); text-align: center; background: #05070f; }
    .tq-gm-titulo { margin: 0; font-size: clamp(1.75rem, min(6vw, 6.5vh), 3.75rem); font-weight: 600; letter-spacing: 0.02em; text-shadow: 0 2px 12px rgb(0 0 0 / 0.6); }
    .tq-gm-versiculo { margin: min(1rem, 1.5vh) auto 0; max-inline-size: 46rem; font-size: clamp(0.95rem, min(2.4vw, 2.5vh), 1.35rem); line-height: 1.55; font-style: italic; text-shadow: 0 1px 8px rgb(0 0 0 / 0.7); }
    .tq-gm-fuente { margin: 0.5rem 0 0; font-size: 0.95rem; opacity: 0.85; }
    .tq-gm-cerrar { position: absolute; inset-block-start: 1rem; inset-inline-end: 1rem; z-index: 1; display: inline-flex; align-items: center; gap: 0.375rem;
        padding: 0.5rem 1rem; border-radius: 9999px; border: 1px solid rgb(255 246 224 / 0.5); background: rgb(5 7 15 / 0.55);
        color: inherit; font-size: 0.95rem; cursor: pointer; }
    .tq-gm-cerrar:hover, .tq-gm-cerrar:focus-visible { background: rgb(255 246 224 / 0.15); outline: 2px solid #fff6e0; outline-offset: 2px; }

    @keyframes tq-gm-entra { from { opacity: 0; } to { opacity: 1; } }
    @keyframes tq-gm-sube { from { opacity: 0; transform: translateY(12px); } to { opacity: 1; transform: none; } }
    @keyframes tq-gm-sale { to { opacity: 0; } }
    .tq-gm-corre { animation: tq-gm-entra 0.8s ease-out both, tq-gm-sale 2s ease-in 28s both; }
    .tq-gm-corre .tq-gm-titulo { animation: tq-gm-sube 1.6s ease-out 8s both; }
    .tq-gm-corre .tq-gm-versiculo { animation: tq-gm-sube 1.6s ease-out 11.5s both; }
    .tq-gm-corre .tq-gm-fuente { animation: tq-gm-entra 1.2s ease-out 13.5s both; }

    /* Quieto: con «reducir movimiento» del sistema o la preferencia «reducir animaciones» (plan 1.4g, D5). */
    @media (prefers-reduced-motion: reduce) {
        .tq-gm, .tq-gm * { animation: none !important; }
    }
    html.tq-sin-animaciones .tq-gm, html.tq-sin-animaciones .tq-gm * { animation: none !important; }
</style>
<div
    x-data="{
        abierto: false,
        empezar() {
            if (this.abierto) return
            this.abierto = true
            $dispatch('tq-gran-momento-abierto')
            setTimeout(() => this.cerrar(), 30000)
        },
        cerrar() {
            if (! this.abierto) return
            this.abierto = false
            $dispatch('tq-gran-momento-cerrado')
        },
    }"
    x-init="{{ ($esperar ?? false) ? '' : 'empezar()' }}"
    x-on:tq-gran-momento-empezar.window="empezar()"
    x-show="abierto"
    x-cloak
    x-trap.noscroll="abierto"
    x-on:keydown.escape.window="abierto && cerrar()"
    role="dialog"
    aria-modal="true"
    aria-labelledby="tq-gm-titulo"
    @class(['tq-gm', 'tq-gm-corre' => $animado, 'tq-gm-quieto' => ! $animado])
    data-tq-gran-momento="{{ $escena->variante->clave }}"
>
    <div class="tq-gm-escena" aria-hidden="true">
        @include($escena->variante->vista, ['animado' => $animado])
    </div>
    <button type="button" class="tq-gm-cerrar" x-on:click="cerrar()">Cerrar</button>
    <div class="tq-gm-texto">
        <h2 id="tq-gm-titulo" class="tq-gm-titulo">{{ $escena->variante->titulo }}</h2>
        @if ($escena->versiculo !== null)
            <p class="tq-gm-versiculo">«{{ $escena->versiculo }}»</p>
            @if ($escena->fuente !== null)
                <p class="tq-gm-fuente">{{ $escena->fuente }}</p>
            @endif
        @endif
    </div>
</div>
