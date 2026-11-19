{{--
    Configuración → Configuración inicial (plan 1.13). Arriba, el avance; debajo, los pasos en orden, cada uno con su
    ícono (el de la pantalla que abre), su resumen y si está listo. Presionar un paso abre su pantalla de siempre en un
    panel que se desliza desde la derecha encima de la lista, sin recargar; dentro, un enlace a otra pantalla del sistema
    (la ficha de una fila) se abre ahí mismo, con una flecha para volver. Con Ctrl, Shift o el botón del medio, otra
    pestaña. La X o Esc cierran el panel. Lo de adentro del panel es común con la ventana de bienvenida (plan 1.13b):
    `paso-abierto`.
--}}
@php
    /** @var array<string, array{definicion: \App\Modules\Nucleo\ConfiguracionInicial\DefinicionPaso, listo: bool, resumen: ?string}> $pasos */
    /** @var ?\App\Modules\Nucleo\ConfiguracionInicial\DefinicionPaso $abierto */
    $listosObligatorios = $obligatorios - $faltan;
@endphp
<div class="tq-catalogos tq-asistente">
    @include('nucleo::filament.partials.cajas-con-panel')
    <style>
        .tq-asistente-avance { display: flex; align-items: center; gap: 1rem; margin-bottom: 1.25rem; font-size: 0.95rem; font-weight: 600; }
        .tq-asistente-barra { flex: 1; max-width: 24rem; height: 0.5rem; overflow: hidden; border-radius: 9999px; background: var(--gray-200); }
        .dark .tq-asistente-barra { background: var(--gray-700); }
        .tq-asistente-barra > span { display: block; height: 100%; border-radius: 9999px; background: var(--primary-600); transition: width 300ms ease-out; }
        .dark .tq-asistente-barra > span { background: var(--primary-400); }
        .tq-asistente-pasos { display: grid; gap: 0.75rem; max-width: 56rem; }
        .tq-asistente-paso { display: grid; grid-template-columns: 1fr auto; align-items: center; gap: 1rem; padding: 0.875rem 1rem; transition: transform 150ms ease-out, box-shadow 150ms ease-out; }
        .tq-asistente-abrir { display: grid; grid-template-columns: auto 1fr; align-items: center; gap: 1rem; min-width: 0; }
        .tq-asistente-paso:hover, .tq-asistente-paso:focus-within { transform: scale(1.01); box-shadow: 0 4px 14px rgb(0 0 0 / 0.08); outline: none; }
        .tq-asistente-paso[data-listo="1"] { border-left: 4px solid var(--success-600); }
        .tq-asistente-paso[data-listo="0"] { border-left: 4px solid var(--gray-300); }
        .dark .tq-asistente-paso[data-listo="0"] { border-left-color: var(--gray-600); }
        .tq-asistente-icono { display: grid; place-items: center; width: 2.5rem; height: 2.5rem; border-radius: 0.625rem; background: var(--primary-50); color: var(--primary-600); }
        .dark .tq-asistente-icono { background: rgb(255 255 255 / 0.06); color: #FFFFFF; }
        .tq-asistente-titulo { display: block; font-weight: 700; }
        .tq-asistente-numero { margin-right: 0.35rem; color: var(--gray-500); font-weight: 600; }
        .tq-asistente-descripcion { display: block; font-size: 0.8125rem; color: var(--gray-500); }
        .tq-asistente-resumen { display: block; margin-top: 0.15rem; font-size: 0.8125rem; font-weight: 600; color: var(--gray-700); }
        .dark .tq-asistente-resumen { color: var(--gray-300); }
        .tq-asistente-estado { display: flex; align-items: center; gap: 0.5rem; justify-self: end; white-space: nowrap; }
        .tq-asistente-panel-botones { display: flex; flex-wrap: wrap; justify-content: flex-end; align-items: center; gap: 0.5rem; }
        .tq-asistente-panel-botones .fi-btn { white-space: nowrap; }
        .tq-asistente-panel .tq-catalogo-panel-titulo { min-width: 0; }
        /* El título recibe el foco al abrir (para el lector de pantalla), sin marco a la vista. */
        #tq-asistente-panel-titulo:focus { outline: none; }
        @media (max-width: 640px) { .tq-asistente-paso { grid-template-columns: 1fr; } .tq-asistente-estado { justify-self: start; } }
        @media (prefers-reduced-motion: reduce) { .tq-asistente-paso, .tq-asistente-barra > span { transition: none; } .tq-asistente-paso:hover { transform: none; } }
        html.tq-sin-animaciones .tq-asistente-paso, html.tq-sin-animaciones .tq-asistente-barra > span { transition: none; }
        html.tq-sin-animaciones .tq-asistente-paso:hover { transform: none; }
    </style>

    <div @class(['tq-catalogos-detras' => $abierto !== null])>
        <div class="tq-asistente-avance" data-tq-avance="{{ $listosObligatorios }}/{{ $obligatorios }}">
            <span>{{ $faltan === 0 ? 'Todo listo' : "{$listosObligatorios} de {$obligatorios} listos" }}</span>
            <div class="tq-asistente-barra" role="progressbar" aria-valuemin="0" aria-valuemax="{{ $obligatorios }}" aria-valuenow="{{ $listosObligatorios }}" aria-label="Avance de la configuración inicial">
                <span style="width: {{ $obligatorios === 0 ? 100 : round($listosObligatorios * 100 / $obligatorios) }}%"></span>
            </div>
        </div>

        <ol class="tq-asistente-pasos">
            @foreach (array_values($pasos) as $i => ['definicion' => $paso, 'listo' => $listo, 'resumen' => $resumen])
                <li class="fi-section tq-asistente-paso" data-paso="{{ $paso->clave }}" data-listo="{{ $listo ? 1 : 0 }}">
                    {{-- El enlace es el ícono y el texto; los botones van al lado, fuera de él (un botón dentro de un enlace
                         lo seguía). Un clic normal abre el panel sin recargar; con Ctrl, Shift o el botón del medio, el enlace. --}}
                    <a
                        href="{{ \App\Modules\Nucleo\Filament\Pages\ConfiguracionInicial::getUrl(['paso' => $paso->clave]) }}"
                        class="tq-asistente-abrir"
                        x-on:click="if ($event.button === 0 && ! ($event.ctrlKey || $event.metaKey || $event.shiftKey || $event.altKey)) { $event.preventDefault(); $wire.abrir(@js($paso->clave)) }"
                    >
                        <span class="tq-asistente-icono"><x-filament::icon :icon="$paso->icono" class="h-5 w-5" /></span>
                        <span>
                            <span class="tq-asistente-titulo"><span class="tq-asistente-numero">{{ $i + 1 }}.</span>{{ $paso->titulo }}</span>
                            <span class="tq-asistente-descripcion">{{ $paso->descripcion }}</span>
                            @if ($resumen !== null)
                                <span class="tq-asistente-resumen" data-tq-resumen>{{ $resumen }}</span>
                            @endif
                        </span>
                    </a>
                    <span class="tq-asistente-estado">
                        @if ($listo)
                            <x-filament::badge color="success" icon="heroicon-m-check-circle">Listo</x-filament::badge>
                            {{ ($this->reabrirAction)(['paso' => $paso->clave]) }}
                        @else
                            @unless ($paso->obligatorio)
                                <x-filament::badge color="gray">Opcional</x-filament::badge>
                            @endunless
                            {{ ($this->marcarListoAction)(['paso' => $paso->clave]) }}
                        @endif
                    </span>
                </li>
            @endforeach
        </ol>
    </div>

    @if ($abierto !== null && $pantalla !== null)
        <section
            class="tq-catalogo-panel tq-asistente-panel"
            wire:key="panel-{{ $abierto->clave }}"
            aria-labelledby="tq-asistente-panel-titulo"
            x-data="{
                saliendo: false,
                cerrar() {
                    if (this.saliendo) return
                    this.saliendo = true
                    const quieto = window.matchMedia('(prefers-reduced-motion: reduce)').matches || document.documentElement.classList.contains('tq-sin-animaciones')
                    setTimeout(() => $wire.cerrar(), quieto ? 0 : 190)
                },
            }"
            x-bind:class="{ 'tq-saliendo': saliendo }"
            x-init="$nextTick(() => $el.querySelector('#tq-asistente-panel-titulo')?.focus())"
            x-on:keydown.escape.window="if (! $event.defaultPrevented && ! $el.querySelector('.tq-catalogo-panel') && ! $event.target.closest('input, textarea, select, [contenteditable]') && ! [...document.querySelectorAll('.fi-modal-open, [aria-modal=true], .fi-dropdown-panel')].some((capa) => capa.getClientRects().length)) cerrar()"
        >
            <header class="tq-catalogo-panel-encabezado">
                <h2 id="tq-asistente-panel-titulo" class="tq-catalogo-panel-titulo" tabindex="-1">
                    @if ($enFicha)
                        <x-filament::icon-button icon="heroicon-o-arrow-left" color="gray" label="Volver a {{ $abierto->titulo }}" wire:click="volver" data-volver />
                    @endif
                    <x-filament::icon :icon="$abierto->icono" class="h-6 w-6" />
                    {{ $abierto->titulo }}
                </h2>
                <div class="tq-asistente-panel-botones">
                    @if ($abierto->clave === 'nucleo.empresa')
                        {{ $this->elegirFichaAction }}
                    @endif
                    @if ($abiertoListo)
                        <x-filament::badge color="success" icon="heroicon-m-check-circle">Listo</x-filament::badge>
                        {{ ($this->reabrirAction)(['paso' => $abierto->clave]) }}
                    @else
                        {{ ($this->marcarListoAction)(['paso' => $abierto->clave]) }}
                    @endif
                    <x-filament::icon-button icon="heroicon-o-x-mark" color="gray" label="Cerrar y volver a la lista" x-on:click="cerrar()" />
                </div>
            </header>
            @include('nucleo::filament.configuracion-inicial.paso-abierto')
        </section>
    @endif
</div>
